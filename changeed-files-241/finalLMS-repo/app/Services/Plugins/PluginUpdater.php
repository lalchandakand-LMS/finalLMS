<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

final class PluginUpdater
{
    public function __construct(
        private readonly PluginManifestValidator $manifestValidator,
        private readonly PluginCompatibilityChecker $compatibilityChecker,
        private readonly PluginLifecycle $lifecycle,
        private readonly PluginSignatureVerifier $signatureVerifier,
        private readonly PluginArchiveInstaller $archiveInstaller,
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginMigrationRunner $migrationRunner,
    ) {
    }

    public function update(
        string $pluginKey,
        UploadedFile $upload,
        string $manifestJson,
        string $signatureBase64,
        string $keyId,
    ): PluginInstallation {
        try {
            $manifest = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('The plugin update manifest must be valid JSON.', previous: $exception);
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new InvalidArgumentException('The plugin update manifest must be a JSON object.');
        }

        $errors = $this->manifestValidator->errors($manifest);
        if ($errors !== []) {
            throw new InvalidArgumentException('The plugin update manifest is invalid: '.implode(' ', $errors));
        }

        $plugin = PluginInstallation::query()->where('plugin_key', $pluginKey)->firstOrFail();
        if (! in_array($plugin->status, ['installed', 'active', 'inactive'], true)) {
            throw new LogicException('Only an installed, active, or inactive plugin can be upgraded.');
        }

        if (! hash_equals((string) $plugin->plugin_key, (string) $manifest['key'])
            || (string) $plugin->type !== (string) $manifest['type']) {
            throw new InvalidArgumentException('The upgrade package key or type does not match the installed plugin.');
        }

        if (! version_compare((string) $manifest['version'], (string) $plugin->version, '>')) {
            throw new InvalidArgumentException(sprintf(
                'Upgrade version (%s) must be newer than the currently installed version (%s).',
                (string) $manifest['version'],
                (string) $plugin->version
            ));
        }

        $this->compatibilityChecker->assertCompatible($manifest);

        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            throw new InvalidArgumentException('Upload a ZIP plugin update package.');
        }

        $maxPackageBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $uploadSize = $upload->getSize();
        if ($uploadSize === false || $uploadSize < 1 || $uploadSize > $maxPackageBytes) {
            throw new InvalidArgumentException('The plugin update ZIP is empty or exceeds the configured package size limit.');
        }

        [$vendor, $slug] = explode('.', $pluginKey, 2);
        $finalRelativePath = 'plugins/'.$vendor.'/'.str_replace('.', '-', $slug).'/'.$manifest['version'];

        $stagingDirectory = $this->privateStagingDirectory();
        $archivePath = null;
        $previousStatus = $plugin->status;
        $previousSnapshot = [
            'version' => (string) $plugin->version,
            'name' => (string) $plugin->name,
            'manifest' => $plugin->manifest,
            'package_sha256' => $plugin->package_sha256,
            'signature_key_id' => $plugin->signature_key_id,
            'signature_verified_at' => $plugin->signature_verified_at,
            'package_path' => $plugin->package_path,
            'metadata' => is_array($plugin->metadata) ? $plugin->metadata : [],
        ];
        $extractedPath = null;
        $stagedStateEntered = false;

        try {
            $filename = 'update-'.bin2hex(random_bytes(24)).'.zip';
            $stored = $upload->move($stagingDirectory, $filename);
            $archivePath = $stored->getPathname();

            $verified = $this->signatureVerifier->verifyUpdateArchive(
                $plugin,
                $manifest,
                $archivePath,
                $signatureBase64,
                $keyId,
                $finalRelativePath,
            );

            $this->lifecycle->transition($pluginKey, 'update_staged', [
                'health_status' => 'healthy',
                'health_message' => 'Staged signed update verification for version '.$manifest['version'].'.',
                'last_health_check_at' => now(),
            ]);
            $stagedStateEntered = true;

            $fileHashes = $this->archiveInstaller->extractVerifiedUpdatePackage(
                $pluginKey,
                $manifest,
                $verified['package_sha256'],
                $verified['package_path'],
                $archivePath,
            );
            $extractedPath = base_path($verified['package_path']);

            $history = $previousSnapshot['metadata']['version_history'] ?? [];
            if (! is_array($history)) {
                $history = [];
            }
            $history[] = [
                'version' => $previousSnapshot['version'],
                'package_sha256' => $previousSnapshot['package_sha256'],
                'signature_key_id' => $previousSnapshot['signature_key_id'],
                'replaced_at' => now()->toIso8601String(),
                'replaced_by_version' => (string) $manifest['version'],
            ];
            $history = array_slice($history, -10);

            $nextMetadata = $previousSnapshot['metadata'];
            $nextMetadata['integrity_version'] = 1;
            $nextMetadata['installed_file_hashes'] = $fileHashes;
            $nextMetadata['version_history'] = $history;

            $updated = DB::transaction(function () use ($pluginKey, $manifest, $verified, $nextMetadata): PluginInstallation {
                $locked = PluginInstallation::query()->where('plugin_key', $pluginKey)->lockForUpdate()->firstOrFail();
                $locked->forceFill([
                    'name' => (string) $manifest['name'],
                    'version' => (string) $manifest['version'],
                    'manifest' => $manifest,
                    'package_sha256' => $verified['package_sha256'],
                    'signature_key_id' => $verified['signature_key_id'],
                    'signature_verified_at' => now(),
                    'package_path' => $verified['package_path'],
                    'metadata' => $nextMetadata,
                ])->save();

                return $locked->refresh();
            });

            $this->integrityVerifier->verify($updated);
            $this->migrationRunner->migrate($updated);

            $targetStatus = $previousStatus === 'active' ? 'active' : 'inactive';
            if ($targetStatus === 'active') {
                $this->assertDependenciesSatisfied($updated);
            }

            return $this->lifecycle->transition($pluginKey, $targetStatus, [
                'health_status' => 'healthy',
                'health_message' => 'Upgraded cleanly to version '.$manifest['version'].'.',
                'last_health_check_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if (is_string($extractedPath) && is_dir($extractedPath) && ! is_link($extractedPath)) {
                File::deleteDirectory($extractedPath);
            }

            if ($stagedStateEntered) {
                try {
                    DB::transaction(function () use ($pluginKey, $previousSnapshot, $previousStatus): void {
                        $locked = PluginInstallation::query()->where('plugin_key', $pluginKey)->lockForUpdate()->first();
                        if (! $locked) {
                            return;
                        }
                        $restoreStatus = $previousStatus === 'active' ? 'active' : 'inactive';
                        $locked->forceFill([
                            'name' => $previousSnapshot['name'],
                            'version' => $previousSnapshot['version'],
                            'manifest' => $previousSnapshot['manifest'],
                            'package_sha256' => $previousSnapshot['package_sha256'],
                            'signature_key_id' => $previousSnapshot['signature_key_id'],
                            'signature_verified_at' => $previousSnapshot['signature_verified_at'],
                            'package_path' => $previousSnapshot['package_path'],
                            'metadata' => $previousSnapshot['metadata'],
                            'status' => $restoreStatus,
                            'health_status' => 'degraded',
                            'health_message' => 'Plugin upgrade failed and was rolled back to version '.$previousSnapshot['version'].'.',
                            'last_health_check_at' => now(),
                        ])->save();
                    });
                } catch (Throwable) {
                    // Preserve the original failure.
                }
            }

            throw $exception;
        } finally {
            if (is_string($archivePath) && is_file($archivePath) && ! is_link($archivePath)) {
                @unlink($archivePath);
            }
        }
    }

    private function assertDependenciesSatisfied(PluginInstallation $plugin): void
    {
        foreach (($plugin->manifest['dependencies'] ?? []) as $dependency) {
            if (! PluginInstallation::query()->where('plugin_key', $dependency)->where('status', 'active')->exists()) {
                throw new RuntimeException('A required plugin dependency is not active: '.$dependency);
            }
        }
        foreach (($plugin->manifest['conflicts'] ?? []) as $conflict) {
            if (PluginInstallation::query()->where('plugin_key', $conflict)->where('status', 'active')->exists()) {
                throw new RuntimeException('A conflicting plugin is active: '.$conflict);
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
