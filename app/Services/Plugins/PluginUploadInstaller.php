<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    public function update(string $pluginKey, UploadedFile $upload, string $manifestJson, string $signatureBase64, string $keyId): PluginInstallation
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

        if (! hash_equals($pluginKey, (string) $manifest['key'])) {
            throw new InvalidArgumentException('The update manifest must target the selected plugin identity.');
        }
        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            throw new InvalidArgumentException('Upload a ZIP plugin package.');
        }

        $maxPackageBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $uploadSize = $upload->getSize();
        if ($uploadSize === false || $uploadSize < 1 || $uploadSize > $maxPackageBytes) {
            throw new InvalidArgumentException('The plugin ZIP is empty or exceeds the configured package size limit.');
        }

        $stagingDirectory = $this->privateStagingDirectory();
        $archivePath = null;

        try {
            $stored = $upload->move($stagingDirectory, 'update-'.bin2hex(random_bytes(24)).'.zip');
            $archivePath = $stored->getPathname();

            $plugin = DB::transaction(function () use ($pluginKey, $manifest): PluginInstallation {
                $existing = PluginInstallation::query()->where('plugin_key', $pluginKey)->lockForUpdate()->firstOrFail();

                if ($existing->status !== 'inactive') {
                    throw new RuntimeException('Deactivate the installed plugin before applying an update.');
                }
                if ((string) $existing->type !== (string) $manifest['type']) {
                    throw new RuntimeException('A plugin update cannot change the registered plugin type.');
                }
                if (version_compare((string) $manifest['version'], (string) $existing->version, '<=')) {
                    throw new RuntimeException('Plugin updates must use a strictly newer semantic version.');
                }

                [$vendor, $slug] = explode('.', $pluginKey, 2);
                $newRelativePath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];
                $newAbsolutePath = base_path($newRelativePath);
                if (file_exists($newAbsolutePath) || is_link($newAbsolutePath)) {
                    throw new RuntimeException('The target version directory already exists; updates never overwrite installed packages.');
                }

                $snapshot = [
                    'name' => $existing->name,
                    'version' => $existing->version,
                    'type' => $existing->type,
                    'status' => 'inactive',
                    'manifest' => $existing->manifest,
                    'package_sha256' => $existing->package_sha256,
                    'signature_key_id' => $existing->signature_key_id,
                    'signature_verified_at' => $existing->getRawOriginal('signature_verified_at'),
                    'package_path' => $existing->package_path,
                    'health_status' => $existing->health_status,
                    'health_message' => $existing->health_message,
                    'last_health_check_at' => $existing->getRawOriginal('last_health_check_at'),
                    'installed_at' => $existing->getRawOriginal('installed_at'),
                    'activated_at' => $existing->getRawOriginal('activated_at'),
                    'metadata' => $existing->metadata ?? [],
                ];

                $existing->forceFill([
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'type' => $manifest['type'],
                    'status' => 'update_staged',
                    'manifest' => $manifest,
                    'package_sha256' => null,
                    'signature_key_id' => null,
                    'signature_verified_at' => null,
                    'package_path' => null,
                    'health_status' => 'unknown',
                    'health_message' => null,
                    'last_health_check_at' => null,
                    'installed_at' => now(),
                    'activated_at' => null,
                    'metadata' => ['previous_version_snapshot' => $snapshot],
                ])->save();

                return $existing->refresh();
            });

            $verified = $this->signatureVerifier->verifyAndRecord(
                $plugin,
                $manifest,
                $archivePath,
                $signatureBase64,
                $keyId,
                'plugins/'.explode('.', $pluginKey, 2)[0].'/'.str_replace('.', '-', explode('.', $pluginKey, 2)[1]).'/'.$manifest['version'],
            );

            return $this->archiveInstaller->installVerifiedPackage($verified, $archivePath);
        } catch (Throwable $exception) {
            $current = PluginInstallation::query()->where('plugin_key', $pluginKey)->first();
            $snapshot = is_array($current?->metadata) ? ($current->metadata['previous_version_snapshot'] ?? null) : null;
            if ($current && $current->status === 'update_staged' && is_array($snapshot)) {
                $this->restoreSnapshot($current, $snapshot);
            }

            throw $exception;
        } finally {
            if (is_string($archivePath) && is_file($archivePath) && ! is_link($archivePath)) {
                @unlink($archivePath);
            }
        }
    }

    public function restoreSnapshot(PluginInstallation $plugin, array $snapshot): PluginInstallation
    {
        $required = ['name', 'version', 'type', 'manifest', 'package_sha256', 'signature_key_id',
            'signature_verified_at', 'package_path', 'health_status', 'metadata'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $snapshot)) {
                throw new RuntimeException('The saved previous plugin version snapshot is incomplete.');
            }
        }

        $plugin->forceFill([
            'name' => $snapshot['name'],
            'version' => $snapshot['version'],
            'type' => $snapshot['type'],
            'status' => 'inactive',
            'manifest' => $snapshot['manifest'],
            'package_sha256' => $snapshot['package_sha256'],
            'signature_key_id' => $snapshot['signature_key_id'],
            'signature_verified_at' => $snapshot['signature_verified_at'],
            'package_path' => $snapshot['package_path'],
            'health_status' => $snapshot['health_status'],
            'health_message' => $snapshot['health_message'] ?? null,
            'last_health_check_at' => $snapshot['last_health_check_at'] ?? null,
            'installed_at' => $snapshot['installed_at'] ?? null,
            'activated_at' => $snapshot['activated_at'] ?? null,
            'metadata' => $snapshot['metadata'],
        ])->save();

        return $plugin->refresh();
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
