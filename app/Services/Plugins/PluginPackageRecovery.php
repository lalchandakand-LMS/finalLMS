<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PluginPackageRecovery
{
    private function recoveryDirectory(): string
    {
        return storage_path('app/private/plugin-recovery');
    }

    private function assertRecoveryDirectory(bool $create = false): string
    {
        $privateRoot = storage_path('app/private');
        $directory = $this->recoveryDirectory();
        if (is_link($privateRoot) || is_link($directory)) {
            throw new RuntimeException('Plugin recovery storage must not contain symbolic links.');
        }
        if ($create && ! is_dir($directory)
            && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Plugin recovery storage could not be created.');
        }
        $realPrivateRoot = realpath($privateRoot);
        $realDirectory = realpath($directory);
        if ($realPrivateRoot === false || $realDirectory === false
            || ! str_starts_with($realDirectory, rtrim($realPrivateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Plugin recovery storage must remain inside private application storage.');
        }
        @chmod($realDirectory, 0700);
        return $realDirectory;
    }

    private function canonicalPackagePath(PluginInstallation $plugin): string
    {
        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2 || ! is_string($plugin->package_path)) {
            throw new RuntimeException('Plugin identity or package path is invalid.');
        }
        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! hash_equals($expected, $plugin->package_path)) {
            throw new RuntimeException('Plugin package path does not match its canonical identity.');
        }
        return $expected;
    }

    private function assertSafeTree(string $root): array
    {
        if (is_link($root) || ! is_dir($root)) {
            throw new RuntimeException('The plugin package directory is unavailable or unsafe.');
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || (! $item->isDir() && ! $item->isFile())) {
                throw new RuntimeException('Plugin recovery refuses symbolic links and special files.');
            }
            if ($item->isFile()) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
                if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '/')) {
                    throw new RuntimeException('Plugin package contains an unsafe path.');
                }
                $hash = hash_file('sha256', $item->getPathname());
                if (! is_string($hash)) throw new RuntimeException('A plugin file checksum could not be calculated.');
                $files[$relative] = $hash;
            }
        }
        ksort($files);
        if ($files === []) throw new RuntimeException('An empty plugin package cannot be backed up.');
        return $files;
    }

    public function createSnapshot(PluginInstallation $plugin): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive is required for plugin recovery backups.');
        }
        $relativePath = $this->canonicalPackagePath($plugin);
        $source = base_path($relativePath);
        if (is_link(base_path('plugins'))) throw new RuntimeException('The private plugins root must not be a symbolic link.');
        $root = realpath(base_path('plugins'));
        $real = realpath($source);
        if ($root === false || $real === false || ! str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Plugin package resolves outside the private plugins directory.');
        }
        $files = $this->assertSafeTree($real);
        $directory = $this->assertRecoveryDirectory(true);

        $filename = bin2hex(random_bytes(20)).'.zip';
        $archivePath = $directory.DIRECTORY_SEPARATOR.$filename;
        $temporaryPath = $archivePath.'.tmp';
        $metadata = [
            'schema' => 1,
            'plugin_key' => $plugin->plugin_key,
            'version' => $plugin->version,
            'package_path' => $relativePath,
            'name' => $plugin->name,
            'type' => $plugin->type,
            'manifest' => $plugin->manifest,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'release_signature' => is_array($plugin->metadata) ? ($plugin->metadata['release_signature'] ?? null) : null,
            'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
            'files' => $files,
            'created_at' => now()->toIso8601String(),
        ];
        $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $envelope = json_encode([
            'metadata' => $metadata,
            'hmac_sha256' => hash_hmac('sha256', $json, (string) config('app.key')),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Plugin recovery archive could not be opened for writing.');
        }
        try {
            foreach ($files as $relative => $hash) {
                if (! $zip->addFile($real.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative), 'files/'.$relative)) {
                    throw new RuntimeException('A plugin package file could not be added to its recovery archive.');
                }
            }
            if (! $zip->addFromString('recovery.json', $envelope) || ! $zip->close()) {
                throw new RuntimeException('Plugin recovery archive could not be finalized.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($temporaryPath);
            throw $exception;
        }

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath) !== true) throw new RuntimeException('Plugin recovery archive failed verification.');
        $stored = $zip->getFromName('recovery.json');
        $zip->close();
        $decoded = is_string($stored) ? json_decode($stored, true) : null;
        $storedMetadata = $decoded['metadata'] ?? null;
        if (! is_array($storedMetadata) || ! is_string($decoded['hmac_sha256'] ?? null)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Plugin recovery metadata is invalid.');
        }
        $storedJson = json_encode($storedMetadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        if (! hash_equals(hash_hmac('sha256', $storedJson, (string) config('app.key')), $decoded['hmac_sha256'])
            || ! hash_equals(hash('sha256', $json), hash('sha256', $storedJson))) {
            @unlink($temporaryPath);
            throw new RuntimeException('Plugin recovery metadata signature verification failed.');
        }

        // Verify every archived file by streaming it from the ZIP; do not load
        // package contents into PHP memory, even for large plugin assets.
        $verifyZip = new ZipArchive();
        if ($verifyZip->open($temporaryPath) !== true) {
            @unlink($temporaryPath);
            throw new RuntimeException('Plugin recovery archive could not be reopened for file verification.');
        }
        try {
            foreach ($files as $relative => $expectedHash) {
                $stream = $verifyZip->getStream('files/'.$relative);
                if (! is_resource($stream)) {
                    throw new RuntimeException('A package file is missing from the recovery archive.');
                }
                $context = hash_init('sha256');
                try {
                    if (hash_update_stream($context, $stream) === false) {
                        throw new RuntimeException('A package file could not be read during recovery verification.');
                    }
                } finally {
                    fclose($stream);
                }
                if (! hash_equals($expectedHash, hash_final($context))) {
                    throw new RuntimeException('A package file failed recovery archive checksum verification.');
                }
            }
        } finally {
            $verifyZip->close();
        }

        $hash = hash_file('sha256', $temporaryPath);
        if (! is_string($hash) || ! chmod($temporaryPath, 0600) || ! rename($temporaryPath, $archivePath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Verified plugin recovery archive could not be published securely.');
        }
        $bytes = filesize($archivePath);
        if ($bytes === false || ! chmod($archivePath, 0600)) {
            @unlink($archivePath);
            throw new RuntimeException('Published plugin recovery archive permissions could not be secured.');
        }
        return ['path' => $filename, 'sha256' => $hash, 'bytes' => $bytes];
    }

    public function deleteSnapshot(string $filename): void
    {
        if (preg_match('/^[a-f0-9]{40}\\.zip$/D', $filename) !== 1) {
            throw new RuntimeException('Plugin recovery reference is invalid.');
        }
        $path = $this->assertRecoveryDirectory().DIRECTORY_SEPARATOR.$filename;
        if (is_link($path)) throw new RuntimeException('Plugin recovery archive must not be a symbolic link.');
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('An unpublished plugin recovery archive could not be removed.');
        }
    }

    public function restoreSnapshot(PluginInstallation $plugin, string $filename, string $expectedArchiveHash): void
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('PHP ZipArchive is required for plugin recovery.');
        if ($plugin->status !== 'uninstalled') throw new RuntimeException('Only an uninstalled plugin can be restored from a recovery snapshot.');
        if (preg_match('/^[a-f0-9]{40}\.zip$/D', $filename) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $expectedArchiveHash) !== 1) {
            throw new RuntimeException('Plugin recovery reference is invalid.');
        }
        $archivePath = $this->assertRecoveryDirectory().DIRECTORY_SEPARATOR.$filename;
        if (is_link($archivePath) || ! is_file($archivePath)) throw new RuntimeException('Plugin recovery archive is unavailable.');
        $actualHash = hash_file('sha256', $archivePath);
        if (! is_string($actualHash) || ! hash_equals($expectedArchiveHash, $actualHash)) {
            throw new RuntimeException('Plugin recovery archive checksum verification failed.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) throw new RuntimeException('Plugin recovery archive is corrupt.');
        try {
            $raw = $zip->getFromName('recovery.json');
            $envelope = is_string($raw) ? json_decode($raw, true) : null;
            $metadata = $envelope['metadata'] ?? null;
            if (! is_array($metadata) || ($metadata['schema'] ?? null) !== 1
                || ! is_string($envelope['hmac_sha256'] ?? null)) {
                throw new RuntimeException('Plugin recovery metadata is invalid.');
            }
            $json = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (! hash_equals(hash_hmac('sha256', $json, (string) config('app.key')), $envelope['hmac_sha256'])) {
                throw new RuntimeException('Plugin recovery metadata signature verification failed.');
            }
            if (! hash_equals((string) $plugin->plugin_key, (string) ($metadata['plugin_key'] ?? ''))
                || ! hash_equals((string) $plugin->version, (string) ($metadata['version'] ?? ''))
                || ! hash_equals($this->canonicalPackagePath($plugin), (string) ($metadata['package_path'] ?? ''))
                || ! hash_equals((string) $plugin->package_sha256, (string) ($metadata['package_sha256'] ?? ''))
                || ! hash_equals((string) $plugin->signature_key_id, (string) ($metadata['signature_key_id'] ?? ''))
                || ! is_array($metadata['manifest'] ?? null)
                || ! hash_equals(
                    hash('sha256', json_encode($plugin->manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    hash('sha256', json_encode($metadata['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
                )
                || ! is_string($metadata['release_signature'] ?? null)
                || ! hash_equals((string) ($plugin->metadata['release_signature'] ?? ''), $metadata['release_signature'])
                || ! is_array($metadata['files'] ?? null) || $metadata['files'] === []) {
                throw new RuntimeException('Recovery archive does not belong to this exact plugin version.');
            }

            $target = base_path($metadata['package_path']);
            $pluginsRootPath = base_path('plugins');
            if (is_link($pluginsRootPath)) throw new RuntimeException('The private plugins root must not be a symbolic link.');
            $pluginsRoot = realpath($pluginsRootPath);
            $parent = dirname($target);
            $realParent = realpath($parent);
            $expectedParent = $pluginsRoot === false ? false : rtrim($pluginsRoot, DIRECTORY_SEPARATOR);
            if ($pluginsRoot === false || $realParent === false
                || ! str_starts_with($realParent, $expectedParent.DIRECTORY_SEPARATOR)
                || is_link($parent) || file_exists($target) || is_link($target)) {
                throw new RuntimeException('Plugin restore destination is unavailable or resolves outside the private plugins directory.');
            }
            $cursor = $pluginsRootPath;
            foreach (explode('/', substr($metadata['package_path'], strlen('plugins/'), strrpos($metadata['package_path'], '/') - strlen('plugins/'))) as $segment) {
                $cursor .= DIRECTORY_SEPARATOR.$segment;
                if (is_link($cursor)) throw new RuntimeException('Plugin restore stopped because a destination path segment is a symbolic link.');
            }
            $staging = $parent.DIRECTORY_SEPARATOR.'.restore-'.Str::lower(Str::random(16));
            if (! mkdir($staging, 0750)) throw new RuntimeException('Plugin restore staging directory could not be created.');
            try {
                foreach ($metadata['files'] as $relative => $expectedHash) {
                    if (! is_string($relative) || $relative === '' || str_starts_with($relative, '/')
                        || str_contains($relative, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $relative)
                        || ! is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1) {
                        throw new RuntimeException('Recovery archive contains an unsafe file entry.');
                    }
                    $entry = 'files/'.$relative;
                    $index = $zip->locateName($entry);
                    if ($index === false) throw new RuntimeException('A required package file is missing from the recovery archive.');
                    $stat = $zip->statIndex($index);
                    if (($stat['name'] ?? null) !== $entry || str_ends_with($entry, '/')) {
                        throw new RuntimeException('Recovery archive contains an invalid file entry.');
                    }
                    $destination = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $destinationDirectory = dirname($destination);
                    if (! is_dir($destinationDirectory) && ! mkdir($destinationDirectory, 0700, true) && ! is_dir($destinationDirectory)) {
                        throw new RuntimeException('Plugin restore directory could not be created.');
                    }
                    $input = $zip->getStream($entry);
                    $output = fopen($destination, 'xb');
                    if (! is_resource($input) || ! is_resource($output)) {
                        if (is_resource($input)) fclose($input);
                        if (is_resource($output)) fclose($output);
                        throw new RuntimeException('A package file could not be streamed from the recovery archive.');
                    }
                    try {
                        if (stream_copy_to_stream($input, $output) === false) throw new RuntimeException('A package file could not be restored.');
                    } finally {
                        fclose($input);
                        fclose($output);
                    }
                    @chmod($destination, 0640);
                    $restoredHash = hash_file('sha256', $destination);
                    if (! is_string($restoredHash) || ! hash_equals($expectedHash, $restoredHash)) {
                        throw new RuntimeException('A restored package file failed checksum verification.');
                    }
                }
                if (! rename($staging, $target)) throw new RuntimeException('Verified plugin package could not be moved into place.');
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\File::deleteDirectory($staging);
                throw $exception;
            }
        } finally {
            $zip->close();
        }
    }
}
