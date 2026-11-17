<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use InvalidArgumentException;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PluginIntegrityVerifier
{
    /**
     * Verify every installed file and reject missing, modified, extra or linked files.
     * This check must run before a plugin entrypoint is ever loaded.
     */
    public function verify(PluginInstallation $plugin): void
    {
        if (! in_array($plugin->status, ['installed', 'active', 'inactive', 'failed', 'update_staged'], true)
            || ! is_array($plugin->metadata)
            || ($plugin->metadata['integrity_version'] ?? null) !== 1) {
            throw new RuntimeException('The plugin has no supported installed-file integrity record.');
        }

        $expected = $plugin->metadata['installed_file_hashes'] ?? null;
        if (! is_array($expected) || $expected === []) {
            throw new RuntimeException('The plugin installed-file checksum manifest is missing.');
        }

        $relativeRoot = $this->canonicalRelativePath($plugin);
        $pluginsDirectory = base_path('plugins');
        if (is_link($pluginsDirectory)) {
            throw new RuntimeException('The private plugins directory must not be a symbolic link.');
        }

        $pluginsRoot = realpath($pluginsDirectory);
        if ($pluginsRoot === false) {
            throw new RuntimeException('The private plugins directory is unavailable.');
        }

        // Reject symlinks in every parent segment, not only at the final package path.
        $currentPath = $pluginsRoot;
        foreach (explode('/', substr($relativeRoot, strlen('plugins/'))) as $segment) {
            $currentPath .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($currentPath)) {
                throw new RuntimeException('Symbolic links are forbidden in plugin installation path segments.');
            }
        }

        $pluginDirectory = $pluginsRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($relativeRoot, strlen('plugins/')));
        if (is_link($pluginDirectory) || ! is_dir($pluginDirectory)) {
            throw new RuntimeException('The installed plugin directory is missing or unsafe.');
        }

        $realRoot = realpath($pluginDirectory);
        $prefix = rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if ($realRoot === false || ! str_starts_with($realRoot, $prefix)) {
            throw new RuntimeException('The installed plugin directory resolves outside the private plugins folder.');
        }

        $normalizedExpected = [];
        foreach ($expected as $path => $hash) {
            if (! is_string($path) || ! $this->isSafeRelativeFilePath($path)
                || ! is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new RuntimeException('The installed plugin checksum manifest is malformed.');
            }
            $normalizedExpected[$path] = $hash;
        }

        $actualPaths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink()) {
                throw new RuntimeException('Symbolic links are forbidden in installed plugin packages.');
            }

            if ($item->isDir()) {
                continue;
            }

            if (! $item->isFile()) {
                throw new RuntimeException('Special filesystem entries are forbidden in installed plugin packages.');
            }

            $realFile = realpath($path);
            if ($realFile === false || ! str_starts_with($realFile, rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('An installed plugin file resolves outside its package directory.');
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(rtrim($realRoot, DIRECTORY_SEPARATOR)) + 1));
            if (! isset($normalizedExpected[$relative])) {
                throw new RuntimeException('An unregistered file was added to the installed plugin package.');
            }

            $actualHash = hash_file('sha256', $realFile);
            if (! is_string($actualHash) || ! hash_equals($normalizedExpected[$relative], $actualHash)) {
                throw new RuntimeException('An installed plugin file failed its integrity check.');
            }

            $actualPaths[$relative] = true;
        }

        if (count($actualPaths) !== count($normalizedExpected)) {
            throw new RuntimeException('One or more signed plugin files are missing from the installed package.');
        }
    }

    private function canonicalRelativePath(PluginInstallation $plugin): string
    {
        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid plugin identity.');
        }

        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! is_string($plugin->package_path) || ! hash_equals($expected, $plugin->package_path)) {
            throw new RuntimeException('The plugin registry path does not match its immutable identity.');
        }

        return $expected;
    }

    private function isSafeRelativeFilePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 240
            || str_contains($path, '\\')
            || str_contains($path, ':')
            || str_contains($path, "\0")
            || str_starts_with($path, '/')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9_.@+-]{1,120}$/D', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }
}
