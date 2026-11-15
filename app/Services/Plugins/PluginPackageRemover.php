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
        if (count($parts) !== 2) throw new RuntimeException('Invalid plugin identity.');
        $expected = 'plugins/'.$parts[0].'/'.str_replace('.', '-', $parts[1]).'/'.$plugin->version;
        if (! is_string($plugin->package_path) || ! hash_equals($expected, $plugin->package_path)) {
            throw new RuntimeException('The plugin path does not match its canonical identity.');
        }

        $rootPath = base_path('plugins');
        if (is_link($rootPath)) throw new RuntimeException('The private plugins root must not be a symbolic link.');
        $root = realpath($rootPath);
        $target = base_path($expected);
        if ($root === false || is_link($target) || ! is_dir($target)) return;
        $real = realpath($target);
        if ($real === false || ! str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The plugin directory resolves outside the private plugins folder.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) throw new RuntimeException('Plugin uninstall stopped because a symbolic link was found.');
            if (! $item->isDir() && ! $item->isFile()) throw new RuntimeException('Plugin uninstall stopped because a special file was found.');
        }

        File::deleteDirectory($real);
        if (is_dir($real) || file_exists($real)) throw new RuntimeException('Plugin files could not be completely removed.');
    }
}
