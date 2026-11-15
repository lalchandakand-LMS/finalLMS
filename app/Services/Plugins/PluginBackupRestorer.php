<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use RuntimeException;
use Throwable;

final class PluginBackupRestorer
{
    public function __construct(
        private readonly PluginIntegrityVerifier $integrityVerifier,
        private readonly PluginLifecycle $lifecycle,
    ) {
    }

    public function restore(PluginInstallation $plugin): PluginInstallation
    {
        $plugin = PluginInstallation::query()->findOrFail($plugin->getKey());
        if ($plugin->status !== 'uninstalled') {
            throw new RuntimeException('Only an uninstalled plugin with a retained backup can be restored.');
        }

        $metadata = is_array($plugin->metadata) ? $plugin->metadata : [];
        $backupRelative = $metadata['uninstall_backup_path'] ?? null;
        if (! is_string($backupRelative) || ! str_starts_with($backupRelative, 'plugins/.backups/')) {
            throw new RuntimeException('A retained private uninstall backup was not found.');
        }

        $pluginsRoot = realpath(base_path('plugins'));
        if ($pluginsRoot === false || is_link(base_path('plugins'))) {
            throw new RuntimeException('The private plugins directory is unavailable or unsafe.');
        }

        $backup = $this->safePath($pluginsRoot, $backupRelative);
        $targetRelative = (string) $plugin->package_path;
        $target = $this->safePath($pluginsRoot, $targetRelative, false);
        if (! is_dir($backup) || is_link($backup) || file_exists($target) || is_link($target)) {
            throw new RuntimeException('The plugin backup or restore destination is missing or unsafe.');
        }

        $this->ensureDirectory(dirname($target), $pluginsRoot);
        if (! rename($backup, $target)) {
            throw new RuntimeException('Unable to restore the private plugin backup.');
        }

        try {
            $candidate = clone $plugin;
            $candidate->status = 'inactive';
            $this->integrityVerifier->verify($candidate);

            unset($metadata['uninstall_backup_path']);
            $metadata['restored_at'] = now()->toISOString();

            return $this->lifecycle->transition((string) $plugin->plugin_key, 'inactive', [
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            if (is_dir($target) && ! is_link($target) && ! file_exists($backup)) {
                @rename($target, $backup);
            }

            throw $exception;
        }
    }

    private function safePath(string $root, string $relative, bool $mustExist = true): string
    {
        if ($relative === '' || str_starts_with($relative, '/')
            || str_contains($relative, '\\') || str_contains($relative, ':')
            || str_contains($relative, "\0")) {
            throw new RuntimeException('Unsafe plugin backup path.');
        }

        $segments = explode('/', $relative);
        if (array_shift($segments) !== 'plugins' || $segments === []) {
            throw new RuntimeException('Unsafe plugin backup path.');
        }

        $current = $root;
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9_.+-]+$/D', $segment) !== 1) {
                throw new RuntimeException('Unsafe plugin backup path.');
            }
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Symbolic links are forbidden in plugin backup paths.');
            }
        }

        if ($mustExist && ! file_exists($current)) {
            throw new RuntimeException('Plugin backup directory does not exist.');
        }

        return $current;
    }

    private function ensureDirectory(string $directory, string $root): void
    {
        $relative = substr($directory, strlen($root));
        $current = $root;

        foreach (array_filter(explode(DIRECTORY_SEPARATOR, trim($relative, DIRECTORY_SEPARATOR)), 'strlen') as $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, '\\')) {
                throw new RuntimeException('Unsafe plugin restore directory path.');
            }
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('Symbolic links are forbidden in plugin restore paths.');
            }
            if (! is_dir($current) && ! mkdir($current, 0700)) {
                throw new RuntimeException('Unable to create a private plugin restore directory.');
            }
        }
    }
}
