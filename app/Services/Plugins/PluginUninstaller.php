<?php

namespace App\\Services\\Plugins;

use App\\Models\\PluginInstallation;
use Illuminate\\Support\\Facades\\DB;
use RuntimeException;
use Throwable;

final class PluginUninstaller
{
    public function __construct(
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginLifecycle $lifecycle,
    ) {
    }

    public function uninstall(PluginInstallation $plugin): PluginInstallation
    {
        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());
        if ($plugin->status !== 'inactive') {
            throw new RuntimeException('Deactivate the plugin before uninstalling it.');
        }

        $this->assertNoInstalledDependents($plugin);
        $this->integrityVerifier->verify($plugin);

        $pluginsRoot = realpath(base_path('plugins'));
        if ($pluginsRoot === false || is_link(base_path('plugins'))) {
            throw new RuntimeException('The private plugins directory is unavailable or unsafe.');
        }

        $source = $pluginsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr((string) $plugin->package_path, strlen('plugins/')));
        if (is_link($source) || ! is_dir($source)) {
            throw new RuntimeException('The installed plugin directory is missing or unsafe.');
        }

        $backupRelative = 'plugins/.backups/'.explode('.', $plugin->plugin_key, 2)[0].'/'.str_replace('.', '-', explode('.', $plugin->plugin_key, 2)[1]).'/'.$plugin->version.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(6));
        $backup = $pluginsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($backupRelative, strlen('plugins/')));
        $this->ensurePrivateDirectory(dirname($backup), $pluginsRoot);

        if (file_exists($backup) || is_link($backup) || ! rename($source, $backup)) {
            throw new RuntimeException('Unable to move the plugin into its private uninstall backup.');
        }

        try {
            return DB::transaction(function () use ($plugin, $backupRelative): PluginInstallation {
                $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
                $metadata['uninstall_backup_path'] = $backupRelative;
                $metadata['uninstalled_at'] = now()->toISOString();

                return $this->lifecycle->transition((string) $plugin->plugin_key, 'uninstalled', [
                    'metadata' => $metadata,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_dir($backup) && ! is_link($backup) && ! file_exists($source)) {
                @rename($backup, $source);
            }

            throw $exception;
        }
    }

    private function assertNoInstalledDependents(PluginInstallation $plugin): void
    {
        $dependents = PluginInstallation::query()
            ->whereIn('status', ['installed', 'active', 'inactive'])
            ->where('id', '!=', $plugin->getKey())
            ->get(['plugin_key', 'manifest']);

        foreach ($dependents as $dependent) {
            $dependencies = is_array($dependent->manifest) ? ($dependent->manifest['dependencies'] ?? []) : [];
            if (is_array($dependencies) && in_array($plugin->plugin_key, $dependencies, true)) {
                throw new RuntimeException('Uninstall blocked: dependent plugin is still installed: '.$dependent->plugin_key.'.');
            }
        }
    }

    private function ensurePrivateDirectory(string $directory, string $root): void
    {
        $relative = substr($directory, strlen($root));
        $current = $root;

        foreach (array_filter(explode(DIRECTORY_SEPARATOR, trim($relative, DIRECTORY_SEPARATOR)), 'strlen') as $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, '\\\\')) {
                throw new RuntimeException('Unsafe plugin backup directory path.');
            }

            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Symbolic links are forbidden in plugin backup paths.');
            }

            if (! is_dir($current) && ! mkdir($current, 0700)) {
                throw new RuntimeException('Unable to create a private plugin backup directory.');
            }
        }
    }
}
