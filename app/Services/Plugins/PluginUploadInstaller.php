<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Http\UploadedFile;
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
            $retryableState = in_array($existing->status, ['discovered', 'validated', 'staged', 'failed'], true)
                && $existing->installed_at === null
                && $existing->activated_at === null
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

            return $this->archiveInstaller->installVerifiedPackage($verified, $archivePath);
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
