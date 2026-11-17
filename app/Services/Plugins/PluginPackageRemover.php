<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use Illuminate\Support\Facades\File;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class PluginPackageRemover
{
    public function remove(PluginInstallation $plugin): void
    {
        if ($plugin->status !== 'uninstalled') {
            throw new RuntimeException('A plugin must be marked uninstalled before package files are removed.');
        }

        $parts = explode('.', (string) $plugin->plugin_key, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid plugin identity.');
        }

        $expectedCurrent = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! is_string($plugin->package_path) || ! hash_equals($expectedCurrent, $plugin->package_path)) {
            throw new RuntimeException('The plugin path does not match its canonical identity.');
        }

        $rootPath = base_path('plugins');
        if (is_link($rootPath)) {
            throw new RuntimeException('The private plugins root must not be a symbolic link.');
        }
        $root = realpath($rootPath);
        if ($root === false) {
            throw new RuntimeException('The private plugins directory is unavailable.');
        }

        $vendorPath = $root.DIRECTORY_SEPARATOR.$parts[0];
        $pluginPath = $vendorPath.DIRECTORY_SEPARATOR.str_replace('.', '-', $parts[1]);
        foreach ([$vendorPath, $pluginPath] as $parent) {
            if (is_link($parent)) {
                throw new RuntimeException('Plugin uninstall stopped because a parent path is a symbolic link.');
            }
        }
        if (! is_dir($pluginPath)) {
            return;
        }

        $realParent = realpath($pluginPath);
        if ($realParent === false
            || ! str_starts_with($realParent, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The plugin version directory resolves outside the private plugins folder.');
        }

        $entries = scandir($realParent);
        if (! is_array($entries)) {
            throw new RuntimeException('Unable to inspect the plugin version directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $target = $realParent.DIRECTORY_SEPARATOR.$entry;
            if (is_link($target)) {
                throw new RuntimeException('Plugin uninstall stopped because a version path is a symbolic link.');
            }
            if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $entry) !== 1) {
                continue;
            }
            if (! is_dir($target)) {
                throw new RuntimeException('A canonical plugin version path is not a directory.');
            }

            $real = realpath($target);
            if ($real === false
                || ! str_starts_with($real, rtrim($realParent, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('A plugin version directory resolves outside its canonical package parent.');
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new RuntimeException('Plugin uninstall stopped because a symbolic link was found.');
                }
                if (! $item->isDir() && ! $item->isFile()) {
                    throw new RuntimeException('Plugin uninstall stopped because a special file was found.');
                }
            }

            File::deleteDirectory($real);
            if (is_dir($real) || file_exists($real)) {
                throw new RuntimeException('A plugin version package could not be completely removed.');
            }
        }
    }
}
