<?php

namespace App\Services;

use App\Models\SystemUpdatePreparation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class ReleasePackageStager
{
    public function __construct(private readonly ReleaseManifestChecker $manifestChecker)
    {
    }

    public function prepare(User $admin): SystemUpdatePreparation
    {
        $this->expireStalePreparations();
        $release = $this->manifestChecker->check();
        if (! $release['available']) {
            throw new RuntimeException('There is no newer signed release to prepare.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required before a release package can be validated.');
        }

        $details = $release['details'];
        $version = $details['version'];
        $expectedHash = $details['package_sha256'];
        $maxBytes = max(1048576, (int) config('services.lms_updates.max_package_bytes', 104857600));
        $disk = Storage::disk('local');
        $relativePath = 'updates/staging/'.$version.'-'.bin2hex(random_bytes(16)).'.zip';
        $absolutePath = $disk->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private update staging directory could not be created.');
        }

        $privateRoot = realpath($disk->path(''));
        $realDirectory = realpath($directory);
        if ($privateRoot === false
            || $realDirectory === false
            || ! str_starts_with($realDirectory, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The private update staging path resolves outside Laravel private storage.');
        }

        try {
            $this->downloadPackage($details['artifact_url'], $absolutePath, $maxBytes);

            $actualHash = hash_file('sha256', $absolutePath);
            if (! is_string($actualHash) || ! hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException('The downloaded package SHA-256 does not match the signed release manifest.');
            }

            $actualMigrationDigest = $this->validateArchive($absolutePath, $version);
            if (! hash_equals($details['migration_set_sha256'], $actualMigrationDigest)) {
                throw new RuntimeException('The package migration set does not match the signed release manifest.');
            }
            $fileSize = filesize($absolutePath);
            if (! is_int($fileSize) || $fileSize < 1 || $fileSize > $maxBytes) {
                throw new RuntimeException('The verified release package has an invalid file size.');
            }

            @chmod($absolutePath, 0600);

            $olderPreparations = SystemUpdatePreparation::query()
                ->whereIn('status', ['verified', 'staged'])
                ->get();

            foreach ($olderPreparations as $older) {
                if ($older->package_path !== $relativePath) {
                    $disk->delete($older->package_path);
                    if ($older->release_path) {
                        $disk->deleteDirectory($older->release_path);
                    }
                    $older->update([
                        'status' => 'superseded',
                        'message' => 'A newer verified package replaced this staged release.',
                    ]);
                }
            }

            return SystemUpdatePreparation::create([
                'prepared_by' => $admin->id,
                'version' => $version,
                'package_sha256' => $actualHash,
                'manifest_sha256' => $release['manifest_sha256'],
                'migration_set_sha256' => $actualMigrationDigest,
                'package_path' => $relativePath,
                'file_size' => $fileSize,
                'status' => 'verified',
                'message' => 'The signed release package is downloaded and structurally verified. It has not been installed.',
                'expires_at' => now()->addDay(),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($relativePath);
            throw $exception;
        }
    }


    public function extract(SystemUpdatePreparation $preparation): SystemUpdatePreparation
    {
        $this->expireStalePreparations();
        $preparation->refresh();

        if ($preparation->status !== 'verified'
            || ! $preparation->expires_at
            || $preparation->expires_at->isPast()) {
            throw new RuntimeException('Only a current, verified package can be extracted to private staging.');
        }

        if (! is_string($preparation->migration_set_sha256) || $preparation->migration_set_sha256 === '') {
            throw new RuntimeException('This prepared package predates migration-digest tracking. Download and verify it again before extraction.');
        }

        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        $packageAbsolute = $disk->path($preparation->package_path);
        $realPackage = realpath($packageAbsolute);

        if ($privateRoot === false
            || $realPackage === false
            || is_link($packageAbsolute)
            || ! is_file($realPackage)
            || ! str_starts_with($realPackage, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The verified package is missing or no longer resides in private storage.');
        }

        $actualHash = hash_file('sha256', $realPackage);
        if (! is_string($actualHash) || ! hash_equals($preparation->package_sha256, $actualHash)) {
            throw new RuntimeException('The staged package changed after verification. Download and verify it again.');
        }

        $actualMigrationDigest = $this->validateArchive($realPackage, $preparation->version);
        if (! hash_equals($preparation->migration_set_sha256, $actualMigrationDigest)) {
            throw new RuntimeException('The package migration set no longer matches its verified digest.');
        }

        $fileSize = filesize($realPackage);
        $uncompressedBytes = $this->archiveUncompressedSize($realPackage);
        $availableBytes = disk_free_space($privateRoot);
        $requiredBytes = $uncompressedBytes + (is_int($fileSize) ? $fileSize : 0) + 67108864;

        if (! is_int($fileSize)
            || $availableBytes === false
            || $availableBytes < $requiredBytes) {
            throw new RuntimeException('There is not enough measurable free disk space to extract this release safely.');
        }

        $releasePath = 'updates/releases/'.$preparation->version.'-'.bin2hex(random_bytes(16));
        $releaseAbsolute = $disk->path($releasePath);
        $releaseParent = dirname($releaseAbsolute);

        if (! is_dir($releaseParent) && ! mkdir($releaseParent, 0700, true) && ! is_dir($releaseParent)) {
            throw new RuntimeException('The private release staging directory could not be created.');
        }

        $realParent = realpath($releaseParent);
        if ($realParent === false
            || ! str_starts_with($realParent, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || file_exists($releaseAbsolute)
            || is_link($releaseAbsolute)
            || ! mkdir($releaseAbsolute, 0700)) {
            throw new RuntimeException('The release extraction path is not a safe private directory.');
        }

        try {
            $archive = new ZipArchive();
            if ($archive->open($realPackage) !== true) {
                throw new RuntimeException('The verified release archive could not be reopened.');
            }

            try {
                if (! $archive->extractTo($releaseAbsolute)) {
                    throw new RuntimeException('The release archive could not be extracted to private staging.');
                }
            } finally {
                $archive->close();
            }

            $this->secureExtractedTree($releaseAbsolute);

            foreach (['artisan', 'public/index.php', 'vendor/autoload.php', 'VERSION'] as $requiredFile) {
                if (! is_file($releaseAbsolute.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $requiredFile))) {
                    throw new RuntimeException('The extracted release is missing a required application file.');
                }
            }

            $versionContents = file_get_contents($releaseAbsolute.DIRECTORY_SEPARATOR.'VERSION');
            if (! is_string($versionContents) || trim($versionContents) !== $preparation->version) {
                throw new RuntimeException('The extracted release version marker does not match the verified version.');
            }

            $preparation->update([
                'status' => 'staged',
                'release_path' => $releasePath,
                'staged_at' => now(),
                'message' => 'The verified release has been extracted into isolated private staging. It has not been activated or installed.',
            ]);

            return $preparation->refresh();
        } catch (Throwable $exception) {
            $disk->deleteDirectory($releasePath);
            throw $exception;
        }
    }

    private function archiveUncompressedSize(string $path): int
    {
        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new RuntimeException('The verified release archive could not be inspected for disk-space requirements.');
        }

        try {
            $totalBytes = 0;
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry) || ! is_numeric($entry['size'] ?? null)) {
                    throw new RuntimeException('The release archive contains an unreadable size entry.');
                }

                $totalBytes += (int) $entry['size'];
                if ($totalBytes > 2147483648) {
                    throw new RuntimeException('The release archive exceeds the permitted total uncompressed size.');
                }
            }

            return $totalBytes;
        } finally {
            $archive->close();
        }
    }

    private function secureExtractedTree(string $root): void
    {
        $realRoot = realpath($root);
        if ($realRoot === false || is_link($root)) {
            throw new RuntimeException('The extracted release directory is not a real private directory.');
        }

        @chmod($realRoot, 0700);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink()) {
                throw new RuntimeException('The extracted release contains a symbolic link.');
            }

            if ($entry->isDir()) {
                if (! @chmod($path, 0700)) {
                    throw new RuntimeException('A private release directory permission could not be secured.');
                }
            } elseif ($entry->isFile()) {
                if (! @chmod($path, 0600)) {
                    throw new RuntimeException('A private release file permission could not be secured.');
                }
            } else {
                throw new RuntimeException('The extracted release contains an unsupported filesystem entry.');
            }
        }
    }

    private function expireStalePreparations(): void
    {
        $disk = Storage::disk('local');
        $expired = SystemUpdatePreparation::query()
            ->whereIn('status', ['verified', 'staged'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $preparation) {
            $disk->delete($preparation->package_path);
            if ($preparation->release_path) {
                $disk->deleteDirectory($preparation->release_path);
            }
            $preparation->update([
                'status' => 'expired',
                'message' => 'The verified package expired and was removed from private staging storage.',
            ]);
        }
    }

    private function downloadPackage(string $url, string $destination, int $maxBytes): void
    {
        $allowedHosts = [
            'github.com',
            'objects.githubusercontent.com',
            'release-assets.githubusercontent.com',
        ];

        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $response = Http::connectTimeout(5)
                ->timeout(180)
                ->withoutRedirecting()
                ->withOptions([
                    'sink' => $destination,
                    'on_headers' => static function ($response) use ($maxBytes): void {
                        $length = $response->getHeaderLine('Content-Length');
                        if ($length !== '' && ctype_digit($length) && (int) $length > $maxBytes) {
                            throw new RuntimeException('The release package exceeds the configured maximum download size.');
                        }
                    },
                    'progress' => static function ($downloadTotal, $downloadedBytes) use ($maxBytes): void {
                        if ($downloadedBytes > $maxBytes || ($downloadTotal > 0 && $downloadTotal > $maxBytes)) {
                            throw new RuntimeException('The release package exceeds the configured maximum download size.');
                        }
                    },
                ])
                ->get($url);

            if ($response->status() >= 300 && $response->status() < 400) {
                @unlink($destination);
                $location = $response->header('Location');
                $parts = is_string($location) ? parse_url($location) : false;
                if (! is_array($parts)
                    || ($parts['scheme'] ?? null) !== 'https'
                    || ! in_array(strtolower((string) ($parts['host'] ?? '')), $allowedHosts, true)
                    || isset($parts['user'])
                    || isset($parts['pass'])
                    || (isset($parts['port']) && $parts['port'] !== 443)) {
                    throw new RuntimeException('The release package redirected to an untrusted destination.');
                }

                if ($redirect === 3) {
                    throw new RuntimeException('The release package exceeded the permitted redirect count.');
                }

                $url = $location;
                continue;
            }

            $response->throw();
            $fileSize = is_file($destination) ? filesize($destination) : false;
            if (! is_int($fileSize) || $fileSize < 1 || $fileSize > $maxBytes) {
                throw new RuntimeException('The release package is empty or exceeds the configured maximum download size.');
            }

            return;
        }

        throw new RuntimeException('The release package could not be downloaded safely.');
    }

    private function validateArchive(string $path, string $expectedVersion): string
    {
        $archive = new ZipArchive();
        $opened = $archive->open($path);
        if ($opened !== true) {
            throw new RuntimeException('The downloaded release is not a valid ZIP archive.');
        }

        try {
            if ($archive->numFiles < 4 || $archive->numFiles > 50000) {
                throw new RuntimeException('The release archive contains an unexpected number of entries.');
            }

            $allowedRoots = [
                'app', 'artisan', 'bootstrap', 'config', 'database', 'deployment',
                'public', 'resources', 'routes', 'storage', 'vendor', 'composer.json',
                'composer.lock', 'README.md', 'VERSION',
            ];
            $seen = [];
            $entryKinds = [];
            $pathHasDescendants = [];
            $required = [
                'artisan' => false,
                'public/index.php' => false,
                'vendor/autoload.php' => false,
                'VERSION' => false,
            ];
            $totalBytes = 0;
            $migrationHashes = [];

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry) || ! is_string($entry['name'] ?? null)) {
                    throw new RuntimeException('The release archive contains an unreadable entry.');
                }

                $name = $entry['name'];
                if ($name === '' || strlen($name) > 512 || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name)) {
                    throw new RuntimeException('The release archive contains an unsafe path.');
                }

                $directoryEntry = str_ends_with($name, '/');
                $normalized = rtrim($name, '/');
                $segments = explode('/', $normalized);
                if ($normalized === '' || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
                    throw new RuntimeException('The release archive contains a path traversal entry.');
                }

                if (isset($seen[$normalized])) {
                    throw new RuntimeException('The release archive contains duplicate paths.');
                }
                $seen[$normalized] = true;

                $parent = '';
                foreach (array_slice($segments, 0, -1) as $segment) {
                    $parent = $parent === '' ? $segment : $parent.'/'.$segment;
                    if (($entryKinds[$parent] ?? null) === 'file') {
                        throw new RuntimeException('The release archive contains a file used as a parent directory.');
                    }
                    $pathHasDescendants[$parent] = true;
                }
                if (! $directoryEntry && ! empty($pathHasDescendants[$normalized])) {
                    throw new RuntimeException('The release archive contains a file that conflicts with nested paths.');
                }
                $entryKinds[$normalized] = $directoryEntry ? 'directory' : 'file';

                $root = $segments[0];
                if (! in_array($root, $allowedRoots, true)) {
                    throw new RuntimeException('The release archive contains a file outside the approved application directories.');
                }

                if ((str_starts_with($normalized, 'public/')
                        && preg_match('/\\.(?:php[0-9]*|phtml|phar|cgi|pl|sh|exe|dll|so)$/i', $normalized)
                        && $normalized !== 'public/index.php')
                    || in_array('.git', $segments, true)
                    || in_array('.github', $segments, true)
                    || in_array('tests', $segments, true)
                    || in_array('node_modules', $segments, true)
                    || in_array('vendor-bin', $segments, true)
                    || (preg_match('/(^|\/)\.env(?:\.|$)/i', $normalized) && $normalized !== 'deployment/.env.example')
                    || preg_match('/\.(?:pem|key|p12|pfx)$/i', $normalized)
                    || preg_match('/\.sqlite(?:$|\/)/i', $normalized)
                    || str_starts_with($normalized, 'public/uploads/')
                    || str_starts_with($normalized, 'public/storage/')
                    || ($root === 'storage' && (
                        ($directoryEntry && ! in_array($normalized, [
                            'storage', 'storage/app', 'storage/app/private', 'storage/app/public',
                            'storage/framework', 'storage/framework/cache', 'storage/framework/cache/data',
                            'storage/framework/sessions', 'storage/framework/testing', 'storage/framework/views',
                            'storage/logs',
                        ], true))
                        || (! $directoryEntry && basename($normalized) !== '.gitignore')
                    ))
                    || (str_starts_with($normalized, 'bootstrap/cache/') && ! $directoryEntry && basename($normalized) !== '.gitignore')) {
                    throw new RuntimeException('The release archive contains runtime, secret, private or development data.');
                }

                $opsys = 0;
                $attributes = 0;
                if ($archive->getExternalAttributesIndex($index, $opsys, $attributes)
                    && $opsys === ZipArchive::OPSYS_UNIX) {
                    $unixMode = ($attributes >> 16) & 0177777;
                    $fileType = $unixMode & 0170000;
                    if (($fileType !== 0 && ! in_array($fileType, [0100000, 0040000], true))
                        || ($unixMode & 07000) !== 0
                        || ($directoryEntry && $fileType === 0100000)
                        || (! $directoryEntry && $fileType === 0040000)) {
                        throw new RuntimeException('The release archive contains a symbolic link or unsupported special file type.');
                    }
                }

                $entrySize = (int) ($entry['size'] ?? 0);
                if ($entrySize < 0 || $entrySize > 104857600) {
                    throw new RuntimeException('A release archive entry exceeds the permitted uncompressed size.');
                }
                $totalBytes += $entrySize;
                if ($totalBytes > 2147483648) {
                    throw new RuntimeException('The release archive exceeds the permitted total uncompressed size.');
                }

                if (! $directoryEntry && array_key_exists($normalized, $required)) {
                    $required[$normalized] = true;
                }

                if (! $directoryEntry && str_starts_with($normalized, 'database/migrations/') && str_ends_with($normalized, '.php')) {
                    $contents = $archive->getFromIndex($index);
                    if (! is_string($contents)) {
                        throw new RuntimeException('A migration file in the release archive could not be read.');
                    }
                    $migrationHashes[$normalized] = hash('sha256', $contents);
                }
            }

            if (in_array(false, $required, true)) {
                throw new RuntimeException('The release archive is missing a required Laravel application file.');
            }

            $versionContents = $archive->getFromName('VERSION');
            if (! is_string($versionContents) || trim($versionContents) !== $expectedVersion) {
                throw new RuntimeException('The package version marker does not match the signed release manifest.');
            }

            ksort($migrationHashes, SORT_STRING);
            $migrationLines = [];
            foreach ($migrationHashes as $migrationPath => $migrationHash) {
                $migrationLines[] = $migrationHash.'  '.$migrationPath;
            }

            return hash('sha256', implode("\n", $migrationLines).($migrationLines === [] ? '' : "\n"));
        } finally {
            $archive->close();
        }
    }
}
