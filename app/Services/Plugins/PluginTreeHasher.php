<?php

namespace App\Services\Plugins;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class PluginTreeHasher
{
    public function hash(string $directory): string
    {
        if (is_link($directory) || ! is_dir($directory)) {
            throw new RuntimeException('The installed plugin directory is missing or unsafe.');
        }

        $root = realpath($directory);
        if ($root === false) {
            throw new RuntimeException('Unable to resolve the installed plugin directory.');
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Symbolic links are forbidden in installed plugin packages.');
            }

            if ($item->isDir()) {
                continue;
            }

            if (! $item->isFile()) {
                throw new RuntimeException('Special filesystem entries are forbidden in installed plugin packages.');
            }

            $real = realpath($item->getPathname());
            if ($real === false || ! str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('An installed plugin file resolves outside its package directory.');
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root) + 1));
            $files[] = ['path' => $relative, 'absolute' => $real];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        $context = hash_init('sha256');
        hash_update($context, "aloms-plugin-tree-v1\0");

        foreach ($files as $file) {
            $size = filesize($file['absolute']);
            $checksum = hash_file('sha256', $file['absolute']);

            if ($size === false || ! is_string($checksum)) {
                throw new RuntimeException('Unable to calculate an installed plugin file checksum.');
            }

            $path = $file['path'];
            hash_update($context, strlen($path).':'.$path.':'.$size.':'.$checksum."\n");
        }

        return hash_final($context);
    }
}
