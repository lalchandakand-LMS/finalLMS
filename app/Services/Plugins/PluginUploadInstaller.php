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

            try {
                // Run signed migrations only after extraction and installed-file integrity metadata
                // have been recorded. The plugin remains inactive until the Admin activates it.
                $this->migrationRunner->migrate($installed);
            } catch (Throwable $migrationException) {
                // Keep the verified package available for health checks and safe recovery.
                // Deleting it here could make partially-applied MySQL DDL impossible to inspect or reverse.
                $this->lifecycle->transition($pluginKey, 'failed', [
                    'health_status' => 'failed',
                    'health_message' => 'A signed plugin migration failed. The package was retained for administrator recovery.',
                ]);

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
     * Install a new immutable version beside the current package. The old
     * package and its signed integrity snapshot remain available for restore.
     * Updates are deliberately accepted only while the plugin is inactive.
     */
    public function update(UploadedFile $upload, string $manifestJson, string $signatureBase64, string $keyId): PluginInstallation
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
        if ($errors !== []) throw new InvalidArgumentException('The plugin manifest is invalid: '.implode(' ', $errors));
        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') throw new InvalidArgumentException('Upload a ZIP plugin package.');

        $size = $upload->getSize();
        $limit = max(1, (int) config('plugins.max_package_bytes', 52428800));
        if ($size === false || $size < 1 || $size > $limit) throw new InvalidArgumentException('The plugin ZIP is empty or exceeds the configured package size limit.');

        $plugin = PluginInstallation::query()->where('plugin_key', (string) $manifest['key'])->firstOrFail();
        if (! in_array($plugin->status, ['installed', 'inactive'], true)) {
            throw new RuntimeException('Deactivate the plugin and complete any recovery before updating it.');
        }
        if (version_compare((string) $manifest['version'], (string) $plugin->version, '<=')) {
            throw new InvalidArgumentException('An update must use a strictly newer semantic version.');
        }
        if ((string) $manifest['name'] !== (string) $plugin->name || (string) $manifest['type'] !== (string) $plugin->type) {
            throw new InvalidArgumentException('An update cannot change the registered plugin name or type.');
        }

        // Verify and retain a complete restore point before changing registry metadata.
        if (! $this->signatureVerifier->verifyInstalledSignature($plugin)) {
            throw new RuntimeException('The current plugin release has no re-verifiable detached signature and cannot be updated safely.');
        }
        app(PluginIntegrityVerifier::class)->verify($plugin);
        $snapshot = [
            'name' => $plugin->name,
            'version' => $plugin->version,
            'type' => $plugin->type,
            'manifest' => $plugin->manifest,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'signature_verified_at' => $plugin->signature_verified_at?->toISOString(),
            'package_path' => $plugin->package_path,
            'metadata' => $plugin->metadata,
            'installed_at' => $plugin->installed_at?->toISOString(),
        ];
        app(PluginVersionHistoryRecorder::class)->recordSnapshot($plugin, auth()->user(), $snapshot);

        [$vendor, $slug] = explode('.', (string) $plugin->plugin_key, 2);
        $finalPath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];
        $finalAbsolutePath = base_path($finalPath);
        if (file_exists($finalAbsolutePath) || is_link($finalAbsolutePath)) {
            throw new RuntimeException('This version directory already exists. Existing release files are never overwritten.');
        }

        $oldVersionCandidate = clone $plugin;
        $oldVersionCandidate->status = 'inactive';
        $stagingDirectory = $this->privateStagingDirectory();
        $archivePath = null;
        try {
            $stored = $upload->move($stagingDirectory, 'upload-'.bin2hex(random_bytes(24)).'.zip');
            $archivePath = $stored->getPathname();

            $this->lifecycle->transition((string) $plugin->plugin_key, 'update_staged');
            $plugin = $plugin->fresh();
            $metadata = $plugin->metadata ?? [];
            unset($metadata['integrity_version'], $metadata['installed_file_hashes'], $metadata['release_signature'], $metadata['signature_record_version']);
            $declaredPermissions = is_array($manifest['permissions'] ?? null) ? $manifest['permissions'] : [];
            $metadata['granted_permissions'] = array_values(array_intersect(
                is_array($metadata['granted_permissions'] ?? null) ? $metadata['granted_permissions'] : [],
                $declaredPermissions,
            ));
            $plugin->forceFill([
                'name' => $manifest['name'],
                'version' => $manifest['version'],
                'type' => $manifest['type'],
                'manifest' => $manifest,
                'package_sha256' => null,
                'signature_key_id' => null,
                'signature_verified_at' => null,
                'package_path' => $finalPath,
                'metadata' => $metadata,
                'health_status' => 'unknown',
                'health_message' => null,
            ])->save();

            $this->lifecycle->transition((string) $plugin->plugin_key, 'staged');
            $verified = $this->signatureVerifier->verifyAndRecord(
                $plugin->fresh(), $manifest, $archivePath, $signatureBase64, $keyId, $finalPath
            );
            $installed = $this->archiveInstaller->installVerifiedPackage($verified, $archivePath);

            // The new signed package is now safely installed. Roll back the old
            // version's migrations using its immutable retained package, then
            // apply the new version's migrations. A failure never silently swaps
            // registry pointers or deletes either release.
            $this->migrationRunner->rollback($oldVersionCandidate);

            try {
                $this->migrationRunner->migrate($installed);
            } catch (Throwable $exception) {
                $this->lifecycle->transition((string) $plugin->plugin_key, 'failed', [
                    'health_status' => 'failed',
                    'health_message' => 'The new version migration failed. The previous signed version was retained; inspect database state before restoring it.',
                ]);
                throw $exception;
            }

            return $installed->fresh();
        } catch (Throwable $exception) {
            // Never silently point the registry back after a migration may have
            // partially changed DDL. The failed new package and old restore point
            // remain available for an explicit, verified recovery operation.
            $fresh = PluginInstallation::query()->where('plugin_key', $plugin->plugin_key)->first();
            if ($fresh && in_array($fresh->status, ['update_staged', 'staged', 'installed'], true)) {
                try {
                    $this->lifecycle->transition((string) $fresh->plugin_key, 'failed', [
                        'health_status' => 'failed',
                        'health_message' => 'Plugin update did not complete; previous version snapshot retained.',
                    ]);
                } catch (Throwable) {
                    // Preserve the original failure.
                }
            }
            throw $exception;
        } finally {
            if (is_string($archivePath) && is_file($archivePath) && ! is_link($archivePath)) @unlink($archivePath);
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
