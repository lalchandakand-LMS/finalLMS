<?php

namespace App\Services;

use App\Models\SystemUpdateBackup;
use App\Models\SystemUpdatePreparation;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdateBackupCoordinator
{
    private const IGNORED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    public function create(SystemUpdatePreparation $preparation, User $admin): SystemUpdateBackup
    {
        $preparation->refresh();
        if ($preparation->status !== 'staged'
            || ! $preparation->expires_at
            || $preparation->expires_at->isPast()) {
            throw new RuntimeException('A current extracted release is required before creating its pre-activation backup.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to create a private code snapshot.');
        }

        $databaseBackup = $this->findLatestVerifiedDatabaseBackup();
        $codeSnapshot = $this->createCodeSnapshot();

        try {
            return SystemUpdateBackup::create([
                'preparation_id' => $preparation->id,
                'created_by' => $admin->id,
                'code_snapshot_path' => $codeSnapshot['path'],
                'code_snapshot_sha256' => $codeSnapshot['sha256'],
                'code_snapshot_size' => $codeSnapshot['size'],
                'database_backup_path' => $databaseBackup['path'],
                'database_backup_sha256' => $databaseBackup['sha256'],
                'database_backup_created_at' => $databaseBackup['created_at'],
                'status' => 'verified',
                'message' => 'The current application code snapshot and a recent HMAC-verified database/media backup are tied to this staged release. No code was activated.',
                'expires_at' => now()->addDay(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($codeSnapshot['path']);
            throw $exception;
        }
    }

    public function verify(SystemUpdateBackup $backup): bool
    {
        try {
            if ($backup->status !== 'verified' || ! $backup->expires_at || $backup->expires_at->isPast()) {
                return false;
            }

            $disk = Storage::disk('local');
            $privateRoot = realpath($disk->path(''));
            if ($privateRoot === false) {
                return false;
            }

            $codeAbsolute = $disk->path($backup->code_snapshot_path);
            $codeReal = realpath($codeAbsolute);
            if ($codeReal === false
                || is_link($codeAbsolute)
                || ! is_file($codeReal)
                || ! str_starts_with($codeReal, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return false;
            }

            $codeHash = hash_file('sha256', $codeReal);
            if (! is_string($codeHash) || ! hash_equals($backup->code_snapshot_sha256, $codeHash)) {
                return false;
            }

            $database = $this->verifyDatabaseBackup($backup->database_backup_path);
            return hash_equals($backup->database_backup_sha256, $database['sha256'])
                && $database['created_at']->equalTo($backup->database_backup_created_at);
        } catch (Throwable) {
            return false;
        }
    }

    private function findLatestVerifiedDatabaseBackup(): array
    {
        $disk = Storage::disk('local');
        $paths = collect($disk->files('backups'))
            ->filter(fn (string $path) => preg_match('/^backups\/aloms-lms-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{5}\.json\.gz$/', $path))
            ->sortByDesc(fn (string $path) => $disk->lastModified($path));

        foreach ($paths as $path) {
            try {
                $verified = $this->verifyDatabaseBackup($path);
                if ($verified['created_at']->greaterThanOrEqualTo(now()->subDay())) {
                    return $verified;
                }
            } catch (Throwable) {
                // Ignore damaged, incomplete, stale or untrusted backups and continue to the next candidate.
            }
        }

        throw new RuntimeException('Create a fresh database and media backup from Admin → Backups first. No recent complete HMAC-verified backup was found.');
    }

    private function verifyDatabaseBackup(string $relativePath): array
    {
        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        $absolute = $disk->path($relativePath);
        $real = realpath($absolute);
        if ($privateRoot === false
            || $real === false
            || is_link($absolute)
            || ! is_file($real)
            || ! str_starts_with($real, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! preg_match('/^backups\/aloms-lms-backup-[0-9]{8}-[0-9]{6}-[a-z0-9]{5}\.json\.gz$/', $relativePath)) {
            throw new RuntimeException('The database/media backup path is not a trusted private backup file.');
        }

        $size = filesize($real);
        if (! is_int($size) || $size < 1 || $size > 536870912) {
            throw new RuntimeException('The database/media backup has an invalid or excessive compressed size.');
        }

        $compressed = file_get_contents($real);
        $envelopeJson = is_string($compressed) ? @gzdecode($compressed, 536870912) : false;
        if (! is_string($envelopeJson)) {
            throw new RuntimeException('The database/media backup archive is damaged or exceeds the verification limit.');
        }

        $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
        $payloadJson = base64_decode($envelope['payload'] ?? '', true);
        if (! is_string($payloadJson)) {
            throw new RuntimeException('The database/media backup payload is invalid.');
        }

        $expectedSignature = hash_hmac('sha256', $payloadJson, (string) config('app.key'));
        if (! is_string($envelope['signature'] ?? null) || ! hash_equals($expectedSignature, $envelope['signature'])) {
            throw new RuntimeException('The database/media backup HMAC verification failed.');
        }

        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)
            || ($payload['format_version'] ?? null) !== 1
            || ! is_array($payload['tables'] ?? null)
            || ! is_array($payload['public_media'] ?? null)
            || ! is_array($payload['private_files'] ?? null)
            || ! is_string($payload['created_at'] ?? null)) {
            throw new RuntimeException('The database/media backup does not contain the supported complete backup structure.');
        }

        $createdAt = Carbon::parse($payload['created_at']);
        if ($createdAt->isFuture()) {
            throw new RuntimeException('The database/media backup timestamp is invalid.');
        }

        $currentTables = $this->currentBackupTableNames();
        $backupTables = array_keys($payload['tables']);
        sort($currentTables, SORT_STRING);
        sort($backupTables, SORT_STRING);
        if ($currentTables !== $backupTables) {
            throw new RuntimeException('The database/media backup does not contain every current application table.');
        }

        foreach ($payload['tables'] as $rows) {
            if (! is_array($rows)) {
                throw new RuntimeException('A table in the database/media backup has an invalid row collection.');
            }
        }

        foreach ($payload['public_media'] as $mediaPath => $encoded) {
            if (! is_string($mediaPath)
                || ! str_starts_with($mediaPath, 'media/')
                || str_contains($mediaPath, '..')
                || str_contains($mediaPath, '\\')
                || ! is_string($encoded)
                || base64_decode($encoded, true) === false) {
                throw new RuntimeException('The database/media backup contains an unsafe or damaged public media file.');
            }
        }

        foreach ($payload['private_files'] as $privatePath => $encoded) {
            if (! is_string($privatePath)
                || ! str_starts_with($privatePath, 'assignment-submissions/')
                || str_contains($privatePath, '..')
                || str_contains($privatePath, '\\')
                || ! is_string($encoded)
                || base64_decode($encoded, true) === false) {
                throw new RuntimeException('The database/media backup contains an unsafe or damaged private file.');
            }
        }

        foreach (($payload['tables']['media_assets'] ?? []) as $asset) {
            if (! is_array($asset)) {
                throw new RuntimeException('The database/media backup contains an invalid media asset record.');
            }
            $mediaPath = (string) ($asset['path'] ?? '');
            if ($mediaPath !== '' && ! array_key_exists($mediaPath, $payload['public_media'])) {
                throw new RuntimeException('A media asset record is missing its corresponding uploaded file from the backup.');
            }
        }

        $sha256 = hash_file('sha256', $real);
        if (! is_string($sha256)) {
            throw new RuntimeException('The database/media backup SHA-256 could not be calculated.');
        }

        return [
            'path' => $relativePath,
            'sha256' => $sha256,
            'created_at' => $createdAt,
            'size' => $size,
        ];
    }

    private function currentBackupTableNames(): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Verified update backups currently require the configured MySQL/MariaDB database.');
        }

        $rows = DB::select('SHOW TABLES');
        $names = array_map(fn ($row) => (string) array_values((array) $row)[0], $rows);

        return array_values(array_filter($names, fn (string $name) => ! in_array($name, self::IGNORED_TABLES, true)));
    }

    private function createCodeSnapshot(): array
    {
        $disk = Storage::disk('local');
        $privateRoot = realpath($disk->path(''));
        if ($privateRoot === false) {
            throw new RuntimeException('Private Laravel storage is unavailable for the code snapshot.');
        }

        $root = realpath(base_path());
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('The current application root cannot be resolved for the code snapshot.');
        }

        $versionPath = $root.DIRECTORY_SEPARATOR.'VERSION';
        $version = is_file($versionPath) ? trim((string) file_get_contents($versionPath)) : '';
        $configuredVersion = (string) config('services.lms_updates.current_version', '0.0.0');
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version) || $version !== $configuredVersion) {
            throw new RuntimeException('The current application VERSION marker is missing, invalid or does not match the installed LMS version; a code snapshot cannot be trusted.');
        }

        $relativePath = 'updates/backups/code-'.$version.'-'.bin2hex(random_bytes(12)).'.zip';
        $absolutePath = $disk->path($relativePath);
        $directory = dirname($absolutePath);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private code backup directory could not be created.');
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false
            || is_link($directory)
            || ! str_starts_with($realDirectory, rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The code snapshot destination resolves outside private storage.');
        }

        $freeBytes = @disk_free_space($privateRoot);
        if ((! is_float($freeBytes) && ! is_int($freeBytes)) || $freeBytes < 268435456) {
            throw new RuntimeException('At least 256 MB of measurable free private storage is required before creating a code snapshot.');
        }

        $archive = new ZipArchive();
        if ($archive->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The private code snapshot archive could not be opened.');
        }

        $fileCount = 0;
        $totalBytes = 0;
        $required = ['artisan' => false, 'public/index.php' => false, 'vendor/autoload.php' => false, 'composer.lock' => false, 'VERSION' => false];

        try {
            $directoryIterator = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator($directoryIterator, function ($current) use ($root): bool {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($current->getPathname(), strlen($root) + 1));
                if ($relative === '' || $this->isExcludedPath($relative, $current->isDir())) {
                    return false;
                }
                if ($current->isLink()) {
                    throw new RuntimeException('The current application contains an unexpected symbolic link; code snapshot creation was stopped.');
                }

                return true;
            });
            $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($iterator as $entry) {
                if (! $entry->isFile()) {
                    continue;
                }

                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($root) + 1));
                if ($this->isExcludedPath($relative, false)) {
                    continue;
                }

                $size = $entry->getSize();
                if ($size < 0 || $size > 104857600) {
                    throw new RuntimeException('A current application file exceeds the permitted code snapshot size.');
                }

                $totalBytes += $size;
                $fileCount++;
                if ($fileCount > 50000 || $totalBytes > 2147483648 || $totalBytes > $freeBytes - 134217728) {
                    throw new RuntimeException('The current application exceeds the permitted code snapshot file or size limit.');
                }

                if (! $archive->addFile($entry->getPathname(), $relative)) {
                    throw new RuntimeException('A current application file could not be added to the private code snapshot.');
                }

                if (array_key_exists($relative, $required)) {
                    $required[$relative] = true;
                }
            }

            if (in_array(false, $required, true)) {
                throw new RuntimeException('The current code snapshot is missing a required Laravel application file.');
            }

            if (! $archive->close()) {
                throw new RuntimeException('The private code snapshot archive could not be finalized.');
            }
        } catch (Throwable $exception) {
            $archive->close();
            @unlink($absolutePath);
            throw $exception;
        }

        $verificationArchive = new ZipArchive();
        if ($verificationArchive->open($absolutePath) !== true) {
            @unlink($absolutePath);
            throw new RuntimeException('The completed code snapshot ZIP could not be reopened for verification.');
        }
        try {
            foreach (array_keys($required) as $requiredFile) {
                if ($verificationArchive->locateName($requiredFile) === false) {
                    throw new RuntimeException('The completed code snapshot is missing a required application file.');
                }
            }
            $archivedVersion = $verificationArchive->getFromName('VERSION');
            if (! is_string($archivedVersion) || trim($archivedVersion) !== $version) {
                throw new RuntimeException('The completed code snapshot version marker failed verification.');
            }
            if ($verificationArchive->locateName('.env') !== false
                || $verificationArchive->locateName('storage/app/private') !== false
                || $verificationArchive->locateName('public/uploads') !== false
                || $verificationArchive->locateName('public/storage') !== false) {
                throw new RuntimeException('The completed code snapshot contains an excluded secret or runtime path.');
            }
        } catch (Throwable $exception) {
            $verificationArchive->close();
            @unlink($absolutePath);
            throw $exception;
        }
        $verificationArchive->close();

        $hash = hash_file('sha256', $absolutePath);
        $fileSize = filesize($absolutePath);
        if (! is_string($hash) || ! is_int($fileSize) || $fileSize < 1) {
            @unlink($absolutePath);
            throw new RuntimeException('The private code snapshot hash or file size could not be verified.');
        }

        @chmod($absolutePath, 0600);

        return ['path' => $relativePath, 'sha256' => $hash, 'size' => $fileSize];
    }

    private function isExcludedPath(string $relative, bool $isDirectory): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $segments = explode('/', $relative);
        $root = $segments[0] ?? '';
        if (array_intersect($segments, ['.git', '.github', 'node_modules', 'tests', 'vendor-bin', 'backups', 'logs', 'tmp', 'temp']) !== []) {
            return true;
        }
        $allowedRoots = [
            'app', 'artisan', 'bootstrap', 'config', 'database', 'deployment',
            'public', 'resources', 'routes', 'vendor', 'composer.json',
            'composer.lock', 'README.md', 'VERSION',
        ];

        if (! in_array($root, $allowedRoots, true)) {
            return true;
        }

        if (in_array($root, ['.git', '.github', 'node_modules', 'tests', 'storage', 'vendor-bin', 'dist', 'coverage'], true)) {
            return true;
        }

        if ($relative === 'vendor/bin' || str_starts_with($relative, 'vendor/bin/')) {
            return true;
        }

        if (str_starts_with($relative, 'bootstrap/cache/')) {
            return true;
        }

        if (str_starts_with($relative, 'public/uploads/') || $relative === 'public/uploads'
            || str_starts_with($relative, 'public/storage/') || $relative === 'public/storage') {
            return true;
        }

        if (preg_match('/(^|\/)\.env(?:\.|$)/i', $relative) && $relative !== 'deployment/.env.example') {
            return true;
        }

        if (in_array(strtolower(basename($relative)), ['auth.json', '.npmrc', '.pypirc', 'credentials.json'], true)
            || preg_match('/\.(?:pem|key|p12|pfx|sqlite|log|sql|dump|gz|zip|bak|backup|tar)$/i', $relative)
            || str_contains(strtolower($relative), '/laravel.log')) {
            return true;
        }

        return false;
    }
}
