<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class PluginArchiveInstaller
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginLifecycle $lifecycle,
    ) {
    }

    /**
     * Extract a previously signature-verified ZIP into the canonical private
     * plugins/vendor/plugin/version directory and mark it installed.
     *
     * No plugin PHP code is loaded or executed by this service.
     */
    public function installVerifiedPackage(PluginInstallation $plugin, string $stagedArchivePath): PluginInstallation
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for plugin installation.');
        }

        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());
        $this->assertVerifiedRegistryRecord($plugin);

        $archive = $this->resolveStagedArchive($stagedArchivePath);
        $maxPackageBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $archiveSize = filesize($archive);
        if ($archiveSize === false || $archiveSize < 1 || $archiveSize > $maxPackageBytes) {
            throw new RuntimeException('The staged plugin archive is empty or exceeds the configured package limit.');
        }

        $checksum = hash_file('sha256', $archive);
        if (! is_string($checksum) || ! hash_equals((string) $plugin->package_sha256, $checksum)) {
            throw new RuntimeException('The staged plugin archive checksum does not match its verified registry record.');
        }

        $finalRelativePath = (string) $plugin->package_path;
        $finalAbsolutePath = $this->resolveFinalPackagePath($plugin, $finalRelativePath);
        if (file_exists($finalAbsolutePath) || is_link($finalAbsolutePath)) {
            throw new RuntimeException('The target plugin version already exists; installation will not overwrite it.');
        }

        $stagingRoot = realpath((string) config('plugins.staging_directory'));
        if ($stagingRoot === false) {
            throw new RuntimeException('The private plugin staging directory is unavailable.');
        }

        $extractDirectory = $stagingRoot.DIRECTORY_SEPARATOR.'extract-'.bin2hex(random_bytes(16));
        if (! mkdir($extractDirectory, 0700)) {
            throw new RuntimeException('Unable to create a private plugin extraction directory.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($archive, ZipArchive::RDONLY);
        if ($opened !== true) {
            File::deleteDirectory($extractDirectory);
            throw new RuntimeException('The staged plugin archive is not a readable ZIP file.');
        }

        try {
            $entries = $this->inspectArchive($zip);
            $fileHashes = $this->extractEntries($zip, $entries, $extractDirectory);

            $postExtractionChecksum = hash_file('sha256', $archive);
            if (! is_string($postExtractionChecksum) || ! hash_equals($checksum, $postExtractionChecksum)) {
                throw new RuntimeException('The staged plugin archive changed during extraction.');
            }

            $entrypoint = $extractDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $plugin->manifest['entrypoint']);
            if (! is_file($entrypoint) || is_link($entrypoint)) {
                throw new RuntimeException('The signed plugin entrypoint is missing from the package.');
            }

            // Recheck mutable registry state immediately before the filesystem move.
            $current = PluginInstallation::query()->findOrFail($plugin->getKey());
            $this->assertVerifiedRegistryRecord($current);
            if (! hash_equals((string) $current->package_sha256, $checksum)
                || ! hash_equals((string) $current->package_path, $finalRelativePath)) {
                throw new RuntimeException('The verified plugin registry record changed during extraction.');
            }

            $this->ensurePrivateParentDirectory(dirname($finalAbsolutePath));
            if (file_exists($finalAbsolutePath) || is_link($finalAbsolutePath)) {
                throw new RuntimeException('The target plugin version appeared during installation; refusing to overwrite it.');
            }

            if (! rename($extractDirectory, $finalAbsolutePath)) {
                throw new RuntimeException('Unable to atomically move the verified plugin into its private installation directory.');
            }

            try {
                $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
                $metadata['integrity_version'] = 1;
                $metadata['installed_file_hashes'] = $fileHashes;

                return $this->lifecycle->transition((string) $plugin->plugin_key, 'installed', [
                    'metadata' => $metadata,
                ]);
            } catch (\Throwable $exception) {
                $freshStatus = PluginInstallation::query()->find($plugin->getKey())?->status;
                if (! in_array($freshStatus, ['installed', 'active'], true)) {
                    File::deleteDirectory($finalAbsolutePath);
                }

                throw $exception;
            }
        } catch (\Throwable $exception) {
            if (is_dir($extractDirectory)) {
                File::deleteDirectory($extractDirectory);
            }

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{index:int,name:string,path:string,directory:bool,size:int}>
     */
    private function inspectArchive(ZipArchive $zip): array
    {
        $maxEntries = max(1, (int) config('plugins.max_archive_entries', 1000));
        $maxUncompressed = max(1, (int) config('plugins.max_uncompressed_bytes', 104857600));
        $maxFileBytes = max(1, (int) config('plugins.max_file_bytes', 20971520));
        $maxRatio = max(1, (int) config('plugins.max_compression_ratio', 100));

        if ($zip->numFiles < 1 || $zip->numFiles > $maxEntries) {
            throw new RuntimeException('The plugin archive has no files or exceeds the maximum entry count.');
        }

        $entries = [];
        $seen = [];
        $files = [];
        $directories = [];
        $totalBytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $stat = $zip->statIndex($index);

            if (! is_string($name) || ! is_array($stat)) {
                throw new RuntimeException('The plugin archive contains an unreadable entry.');
            }

            $directory = str_ends_with($name, '/');
            $path = $this->normalizeArchivePath($name, $directory);
            $collisionKey = strtolower($path);

            if (isset($seen[$collisionKey])) {
                throw new RuntimeException('The plugin archive contains duplicate or case-colliding paths.');
            }
            $seen[$collisionKey] = true;

            $size = (int) ($stat['size'] ?? -1);
            $compressedSize = (int) ($stat['comp_size'] ?? -1);
            if ($size < 0 || $compressedSize < 0 || ($directory && $size !== 0)) {
                throw new RuntimeException('The plugin archive contains invalid entry sizes.');
            }

            if (! $directory) {
                if ($size > $maxFileBytes) {
                    throw new RuntimeException('A plugin archive file exceeds the per-file extraction limit.');
                }

                if ($size > 0 && ($compressedSize < 1 || ($size / $compressedSize) > $maxRatio)) {
                    throw new RuntimeException('The plugin archive exceeds the maximum compression ratio.');
                }

                $totalBytes += $size;
                if ($totalBytes > $maxUncompressed) {
                    throw new RuntimeException('The plugin archive exceeds the total uncompressed size limit.');
                }

                $files[] = $path;
            } else {
                $directories[] = $path;
            }

            if (isset($stat['encryption_method']) && (int) $stat['encryption_method'] !== ZipArchive::EM_NONE) {
                throw new RuntimeException('Encrypted plugin archive entries are not supported.');
            }

            $this->assertRegularZipEntry($zip, $index, $directory);

            $entries[] = [
                'index' => $index,
                'name' => $name,
                'path' => $path,
                'directory' => $directory,
                'size' => $size,
            ];
        }

        $directorySet = array_fill_keys($directories, true);
        $fileSet = array_fill_keys($files, true);

        foreach ($files as $file) {
            if (isset($directorySet[$file])) {
                throw new RuntimeException('The plugin archive contains a file/directory path collision.');
            }

            $segments = explode('/', $file);
            array_pop($segments);
            $parent = '';
            foreach ($segments as $segment) {
                $parent = $parent === '' ? $segment : $parent.'/'.$segment;
                if (isset($fileSet[$parent])) {
                    throw new RuntimeException('A plugin archive file is used as a parent directory.');
                }
            }
        }

        return $entries;
    }

    private function normalizeArchivePath(string $name, bool $directory): string
    {
        if ($name === '' || strlen($name) > 240
            || str_contains($name, '\\')
            || str_contains($name, "\0")
            || str_contains($name, ':')
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name) === 1) {
            throw new InvalidArgumentException('The plugin archive contains an absolute or unsafe path.');
        }

        $path = $directory ? rtrim($name, '/') : $name;
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9_.@+-]{1,120}$/D', $segment) !== 1) {
                throw new InvalidArgumentException('The plugin archive contains a traversal or unsupported path segment.');
            }
        }

        if (strtolower($path) === '.env' || str_contains(strtolower($path), '/.env')) {
            throw new InvalidArgumentException('Environment files are forbidden inside plugin packages.');
        }

        return $path;
    }

    private function assertRegularZipEntry(ZipArchive $zip, int $index, bool $directory): void
    {
        $operatingSystem = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)
            || $operatingSystem !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $type = ($attributes >> 16) & 0170000;
        if ($type !== 0 && ! in_array($type, [0100000, 0040000], true)) {
            throw new RuntimeException('Symbolic links and special files are forbidden in plugin archives.');
        }

        if (($directory && $type === 0100000) || (! $directory && $type === 0040000)) {
            throw new RuntimeException('The plugin archive entry type does not match its path.');
        }
    }

    /**
     * @param list<array{index:int,name:string,path:string,directory:bool,size:int}> $entries
     * @return array<string, string>
     */
    private function extractEntries(ZipArchive $zip, array $entries, string $root): array
    {
        $maxFileBytes = max(1, (int) config('plugins.max_file_bytes', 20971520));
        $hashes = [];

        foreach ($entries as $entry) {
            $destination = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry['path']);

            if ($entry['directory']) {
                $this->ensureDirectoryWithinRoot($destination, $root);
                continue;
            }

            $this->ensureDirectoryWithinRoot(dirname($destination), $root);

            $input = $zip->getStream($entry['name']);
            if (! is_resource($input)) {
                throw new RuntimeException('Unable to read a plugin archive entry.');
            }

            $output = @fopen($destination, 'xb');
            if (! is_resource($output)) {
                fclose($input);
                throw new RuntimeException('Unable to create a plugin file during extraction.');
            }

            $written = 0;
            try {
                while (! feof($input)) {
                    $chunk = fread($input, 8192);
                    if ($chunk === false) {
                        throw new RuntimeException('Unable to read plugin archive data.');
                    }

                    if ($chunk === '') {
                        if (feof($input)) {
                            break;
                        }
                        continue;
                    }

                    $written += strlen($chunk);
                    if ($written > $entry['size'] || $written > $maxFileBytes) {
                        throw new RuntimeException('A plugin archive entry expanded beyond its declared size.');
                    }

                    $offset = 0;
                    $length = strlen($chunk);
                    while ($offset < $length) {
                        $count = fwrite($output, substr($chunk, $offset));
                        if ($count === false || $count === 0) {
                            throw new RuntimeException('Unable to write a plugin file during extraction.');
                        }
                        $offset += $count;
                    }
                }

                if ($written !== $entry['size']) {
                    throw new RuntimeException('The extracted plugin file size does not match its ZIP directory record.');
                }
            } finally {
                fclose($input);
                fclose($output);
            }

            chmod($destination, 0640);

            $hash = hash_file('sha256', $destination);
            if (! is_string($hash)) {
                throw new RuntimeException('Unable to calculate an installed plugin file checksum.');
            }
            $hashes[$entry['path']] = $hash;
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    private function assertVerifiedRegistryRecord(PluginInstallation $plugin): void
    {
        if ($plugin->status !== 'staged' || $plugin->signature_verified_at === null
            || ! is_array($plugin->manifest) || ! is_string($plugin->package_sha256)
            || preg_match('/^[a-f0-9]{64}$/D', $plugin->package_sha256) !== 1
            || ! is_string($plugin->signature_key_id) || trim($plugin->signature_key_id) === '') {
            throw new RuntimeException('Only a staged plugin with verified signature metadata can be installed.');
        }

        if (! hash_equals((string) $plugin->plugin_key, (string) ($plugin->manifest['key'] ?? ''))
            || (string) $plugin->version !== (string) ($plugin->manifest['version'] ?? '')
            || (string) $plugin->name !== (string) ($plugin->manifest['name'] ?? '')
            || (string) $plugin->type !== (string) ($plugin->manifest['type'] ?? '')) {
            throw new RuntimeException('The verified plugin manifest does not match its registry identity.');
        }

        $errors = $this->manifestValidator->errors($plugin->manifest);
        if ($errors !== []) {
            throw new RuntimeException('The registered plugin manifest is invalid: '.implode(' ', $errors));
        }

        $trustedKeys = config('plugins.trusted_public_keys', []);
        if (! is_array($trustedKeys) || ! array_key_exists($plugin->signature_key_id, $trustedKeys)) {
            throw new RuntimeException('The plugin signing key is no longer trusted.');
        }
    }

    private function resolveStagedArchive(string $path): string
    {
        if ($path === '' || is_link($path) || ! is_file($path) || str_contains($path, "\0")) {
            throw new InvalidArgumentException('The staged plugin archive must be a regular private file.');
        }

        $pluginsDirectory = base_path('plugins');
        $stagingDirectory = (string) config('plugins.staging_directory');
        if (is_link($pluginsDirectory) || is_link($stagingDirectory)) {
            throw new InvalidArgumentException('The private plugins and staging directories must not be symbolic links.');
        }

        $pluginsRoot = realpath($pluginsDirectory);
        $stagingRoot = realpath($stagingDirectory);
        $real = realpath($path);

        if ($pluginsRoot === false || $stagingRoot === false || $real === false
            || ! str_starts_with($stagingRoot, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! str_starts_with($real, rtrim($stagingRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! is_readable($real)) {
            throw new InvalidArgumentException('Plugin archives must be stored inside the private plugins/.staging directory.');
        }

        return $real;
    }

    private function resolveFinalPackagePath(PluginInstallation $plugin, string $relativePath): string
    {
        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('The registered plugin key does not contain a vendor and plugin segment.');
        }

        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! hash_equals($expected, $relativePath)) {
            throw new RuntimeException('The registered plugin path does not match its signed identity.');
        }

        $pluginsRoot = realpath(base_path('plugins'));
        if ($pluginsRoot === false || is_link(base_path('plugins'))) {
            throw new RuntimeException('The private plugins directory is unavailable or unsafe.');
        }

        return $pluginsRoot.DIRECTORY_SEPARATOR.$parts[0].DIRECTORY_SEPARATOR.str_replace('.', '-', $parts[1]).DIRECTORY_SEPARATOR.$plugin->version;
    }

    private function ensurePrivateParentDirectory(string $directory): void
    {
        $pluginsRoot = realpath(base_path('plugins'));
        if ($pluginsRoot === false) {
            throw new RuntimeException('The private plugins directory is unavailable.');
        }

        $relative = substr($directory, strlen($pluginsRoot));
        $relative = trim($relative, DIRECTORY_SEPARATOR);
        $current = $pluginsRoot;

        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Unsafe plugin installation parent path.');
            }

            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Symbolic links are forbidden in plugin installation directories.');
            }

            if (! is_dir($current) && ! mkdir($current, 0750)) {
                throw new RuntimeException('Unable to create a private plugin installation directory.');
            }
        }
    }

    private function ensureDirectoryWithinRoot(string $directory, string $root): void
    {
        $root = realpath($root);
        if ($root === false) {
            throw new RuntimeException('The temporary plugin extraction directory is unavailable.');
        }

        $relative = substr($directory, strlen($root));
        $relative = trim($relative, DIRECTORY_SEPARATOR);
        $current = $root;

        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException('Unsafe plugin extraction directory.');
            }

            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Symbolic links are forbidden during plugin extraction.');
            }

            if (! is_dir($current) && ! mkdir($current, 0750)) {
                throw new RuntimeException('Unable to create a plugin extraction directory.');
            }
        }
    }
}
