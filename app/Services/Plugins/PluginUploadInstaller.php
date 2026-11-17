<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use App\Models\PluginVersionHistory;
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
                throw new RuntimeException('This plugin is already registered or installed. Use the signed package update action for an existing inactive plugin.');
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

            // Installation verifies and stores the signed package only. Migrations run
            // after an administrator explicitly activates the integrity-checked package.
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
     * Install a newer signed package for an existing inactive plugin.
     * The previous package and registry snapshot are retained for a later rollback.
     */
    public function update(
        UploadedFile $upload,
        string $manifestJson,
        string $signatureBase64,
        string $keyId,
        string $pluginKey,
        PluginIntegrityVerifier $integrityVerifier,
    ): PluginInstallation {
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

        if (! hash_equals($pluginKey, (string) ($manifest['key'] ?? ''))) {
            throw new InvalidArgumentException('An update package must keep the installed plugin identity.');
        }

        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            throw new InvalidArgumentException('Upload a ZIP plugin package.');
        }

        $maxPackageBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $uploadSize = $upload->getSize();
        if ($uploadSize === false || $uploadSize < 1 || $uploadSize > $maxPackageBytes) {
            throw new InvalidArgumentException('The plugin ZIP is empty or exceeds the configured package size limit.');
        }

        $plugin = PluginInstallation::query()->where('plugin_key', $pluginKey)->firstOrFail();
        if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
            throw new RuntimeException('Deactivate the plugin and resolve any failed lifecycle state before updating it.');
        }

        if (version_compare((string) $manifest['version'], (string) $plugin->version, '<=')) {
            throw new InvalidArgumentException('The update package version must be newer than the installed version.');
        }

        if ((string) $manifest['type'] !== (string) $plugin->type) {
            throw new InvalidArgumentException('A package update cannot change the plugin type.');
        }

        $this->signatureVerifier->verifyInstalledSignature($plugin);
        $integrityVerifier->verify($plugin);

        foreach (($manifest['dependencies'] ?? []) as $dependency) {
            if (! PluginInstallation::query()->where('plugin_key', $dependency)->where('status', 'active')->exists()) {
                throw new RuntimeException('A required plugin dependency is not active: '.$dependency);
            }
        }
        foreach (($manifest['conflicts'] ?? []) as $conflict) {
            if (PluginInstallation::query()->where('plugin_key', $conflict)->where('status', 'active')->exists()) {
                throw new RuntimeException('A conflicting plugin is active: '.$conflict);
            }
        }

        [$vendor, $slug] = explode('.', $pluginKey, 2);
        $finalPath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];
        $finalAbsolutePath = base_path($finalPath);
        if (file_exists($finalAbsolutePath) || is_link($finalAbsolutePath)) {
            throw new RuntimeException('This plugin version already exists; package directories are never overwritten.');
        }

        $snapshot = [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'type' => $plugin->type,
            'manifest' => $plugin->manifest,
            'package_path' => $plugin->package_path,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
            'metadata' => $plugin->metadata,
            'installed_at' => $plugin->installed_at?->toISOString(),
            'health_status' => $plugin->health_status,
            'health_message' => $plugin->health_message,
            'last_health_check_at' => $plugin->last_health_check_at?->toISOString(),
        ];

        $stagingDirectory = $this->privateStagingDirectory();
        $archivePath = null;
        $packageInstalled = false;
        $this->lifecycle->transition($pluginKey, 'update_staged');

        try {
            PluginVersionHistory::query()->create([
                'plugin_key' => $plugin->plugin_key,
                'version' => $plugin->version,
                'package_sha256' => $plugin->package_sha256,
                'signature_key_id' => $plugin->signature_key_id,
                'action' => 'package_update_snapshot',
                'status' => $plugin->status,
                'actor_id' => auth()->id(),
                'details' => [
                    'snapshot_schema' => 1,
                    'snapshot' => $snapshot,
                ],
                'created_at' => now(),
            ]);

            $filename = 'update-'.bin2hex(random_bytes(24)).'.zip';
            $stored = $upload->move($stagingDirectory, $filename);
            $archivePath = $stored->getPathname();

            $granted = is_array($plugin->metadata) ? ($plugin->metadata['granted_permissions'] ?? []) : [];
            $newMetadata = [
                'granted_permissions' => array_values(array_intersect(
                    is_array($granted) ? $granted : [],
                    $manifest['permissions'] ?? [],
                )),
            ];

            $plugin->forceFill([
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'type' => $manifest['type'],
                'manifest' => $manifest,
                'package_path' => $finalPath,
                'package_sha256' => null,
                'signature_key_id' => null,
                'signature_verified_at' => null,
                'metadata' => $newMetadata,
                'health_status' => 'unknown',
                'health_message' => null,
                'last_health_check_at' => null,
                'installed_at' => null,
                'activated_at' => null,
            ])->save();

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
            $packageInstalled = true;

            // Keep updates inactive and defer signed migrations until explicit activation.
            // Package rollback can therefore restore an unactivated version without reversing DDL.
            return $installed->fresh();
        } catch (Throwable $exception) {
            if (! $packageInstalled) {
                $current = $plugin->fresh();
                if ($current && in_array($current->status, ['update_staged', 'staged'], true)) {
                    try {
                        if ($current->status === 'staged') {
                            $this->lifecycle->transition($pluginKey, 'failed');
                        }
                        $current = $plugin->fresh();
                        $current->forceFill([
                            'name' => $snapshot['name'],
                            'version' => $snapshot['version'],
                            'type' => $snapshot['type'],
                            'manifest' => $snapshot['manifest'],
                            'package_path' => $snapshot['package_path'],
                            'package_sha256' => $snapshot['package_sha256'],
                            'signature_key_id' => $snapshot['signature_key_id'],
                            'signature_verified_at' => $snapshot['signature_verified_at'],
                            'metadata' => $snapshot['metadata'],
                            'installed_at' => $snapshot['installed_at'],
                            'health_status' => 'failed',
                            'health_message' => 'The attempted package update failed before installation; the previous package was restored and requires a health check.',
                            'last_health_check_at' => null,
                            'activated_at' => null,
                        ])->save();
                        $this->lifecycle->transition($pluginKey, 'inactive');
                    } catch (Throwable $restoreException) {
                        report($restoreException);
                    }
                }
            } elseif ($plugin->fresh()?->status !== 'failed') {
                try {
                    $this->lifecycle->transition($pluginKey, 'failed', [
                        'health_status' => 'failed',
                        'health_message' => 'The package update failed after installation. The new package and previous version were retained for recovery.',
                    ]);
                } catch (Throwable $transitionException) {
                    report($transitionException);
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
