<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PluginUploadInstaller
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginLifecycle $lifecycle,
        private readonly PluginSignatureVerifier $signatureVerifier,
        private readonly PluginArchiveInstaller $archiveInstaller,
        private readonly PluginMigrationRunner $migrationRunner,
    ) {
    }

    public function install(UploadedFile $upload, string $manifestJson, string $signatureBase64, string $keyId): PluginInstallation
    {
        try {
            $manifest = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('The plugin manifest must be valid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new InvalidArgumentException('The plugin manifest must be a JSON object.');
        }

        $errors = $this->manifestValidator->errors($manifest);
        if ($errors !== []) {
            throw new InvalidArgumentException('The plugin manifest is invalid: '.implode(' ', $errors));
        }

        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            throw new InvalidArgumentException('Upload a ZIP plugin package.');
        }

        $maxPackageBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $uploadSize = $upload->getSize();
        if ($uploadSize === false || $uploadSize < 1 || $uploadSize > $maxPackageBytes) {
            throw new InvalidArgumentException('The plugin ZIP is empty or exceeds the configured package size limit.');
        }

        $pluginKey = (string) $manifest['key'];
        [$vendor, $slug] = explode('.', $pluginKey, 2);
        $finalPath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];
        $finalAbsolutePath = base_path($finalPath);
        $existing = PluginInstallation::query()->where('plugin_key', $pluginKey)->first();

        if ($existing) {
            $retryableState = (
                in_array($existing->status, ['discovered', 'validated', 'staged', 'failed'], true)
                    && $existing->installed_at === null
                    && $existing->activated_at === null
                ) || $existing->status === 'uninstalled';
            $retryableState = $retryableState
                && ($existing->package_path === null || hash_equals($finalPath, (string) $existing->package_path))
                && ! file_exists($finalAbsolutePath)
                && ! is_link($finalAbsolutePath);

            if (! $retryableState) {
                throw new RuntimeException('This plugin is already registered or installed. In-place plugin updates are not enabled yet.');
            }

            $existing->delete();
        }

        $stagingDirectory = $this->privateStagingDirectory();
        $archivePath = null;
        $plugin = null;

        try {
            $filename = 'upload-'.bin2hex(random_bytes(24)).'.zip';
            $stored = $upload->move($stagingDirectory, $filename);
            $archivePath = $stored->getPathname();

            $plugin = new PluginInstallation();
            $plugin->forceFill([
                'plugin_key' => $pluginKey,
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'type' => $manifest['type'],
                'status' => 'discovered',
                'manifest' => $manifest,
            ])->save();

            $this->lifecycle->transition($pluginKey, 'validated');
            $this->lifecycle->transition($pluginKey, 'staged');

            $verified = $this->signatureVerifier->verifyAndRecord(
                $plugin->fresh(),
                $manifest,
                $archivePath,
                $signatureBase64,
                $keyId,
                $finalPath,
            );

            $installed = $this->archiveInstaller->installVerifiedPackage($verified, $archivePath);

            // Run signed migrations only after signature verification, safe extraction,
            // canonical installation and installed-file integrity metadata are complete.
            try {
                $this->migrationRunner->migrate($installed);
            } catch (Throwable $migrationException) {
                $failed = $installed->fresh();
                if ($failed && $failed->status === 'installed') {
                    $this->lifecycle->transition($pluginKey, 'failed', [
                        'health_status' => 'failed',
                        'health_message' => 'A signed plugin migration failed during installation. Review server logs.',
                    ]);
                }

                $this->removeFailedPackage($installed);

                throw $migrationException;
            }

            return $installed->fresh();
        } catch (Throwable $exception) {
            if ($plugin instanceof PluginInstallation && $plugin->exists) {
                $current = $plugin->fresh();
                if ($current && in_array($current->status, ['discovered', 'validated', 'staged'], true)) {
                    try {
                        $this->lifecycle->transition((string) $current->plugin_key, 'failed', [
                            'health_status' => 'failed',
                            'health_message' => 'Plugin package installation failed. Review the administrator activity log.',
                        ]);
                    } catch (Throwable) {
                        // Preserve the original installation failure.
                    }
                }
            }

            throw $exception;
        } finally {
            if (is_string($archivePath) && is_file($archivePath) && ! is_link($archivePath)) {
                @unlink($archivePath);
            }
        }
    }


    /**
     * Remove only the canonical package created by this verified installation.
     * Refuse symlinks and paths that resolve outside the private plugin root.
     */
    private function removeFailedPackage(PluginInstallation $plugin): void
    {
        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Cannot safely remove a failed plugin package with an invalid identity.');
        }

        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! is_string($plugin->package_path) || ! hash_equals($expected, $plugin->package_path)) {
            throw new RuntimeException('Cannot safely remove a failed plugin package with a non-canonical path.');
        }

        $rootPath = base_path('plugins');
        if (is_link($rootPath)) {
            throw new RuntimeException('The private plugin root is a symbolic link; failed package cleanup was stopped.');
        }

        $root = realpath($rootPath);
        if ($root === false) {
            throw new RuntimeException('The private plugin root is unavailable; failed package cleanup was stopped.');
        }

        $current = $rootPath;
        foreach (explode('/', substr($expected, strlen('plugins/'))) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('A symbolic link was found in the failed plugin package path.');
            }
        }

        $target = base_path($expected);
        if (! is_dir($target)) {
            return;
        }

        $real = realpath($target);
        if ($real === false || ! str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The failed plugin package resolves outside the private plugin root.');
        }

        File::deleteDirectory($real);
        if (is_dir($real) || file_exists($real)) {
            throw new RuntimeException('The failed plugin package could not be completely removed.');
        }
    }

    private function privateStagingDirectory(): string
    {
        $pluginsDirectory = base_path('plugins');
        $configuredStaging = (string) config('plugins.staging_directory');

        if (is_link($pluginsDirectory) || is_link($configuredStaging)) {
            throw new RuntimeException('The private plugin directories must not be symbolic links.');
        }

        $pluginsRoot = realpath($pluginsDirectory);
        if ($pluginsRoot === false) {
            throw new RuntimeException('The private plugins directory is unavailable.');
        }

        if (! is_dir($configuredStaging) && ! mkdir($configuredStaging, 0700, true) && ! is_dir($configuredStaging)) {
            throw new RuntimeException('Unable to create the private plugin staging directory.');
        }

        $stagingRoot = realpath($configuredStaging);
        if ($stagingRoot === false
            || ! str_starts_with($stagingRoot, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! is_writable($stagingRoot)) {
            throw new RuntimeException('The plugin staging directory is outside the private plugins folder or is not writable.');
        }

        return $stagingRoot;
    }
}
