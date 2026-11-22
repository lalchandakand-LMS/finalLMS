<?php

namespace App\\Services\\Plugins;

use App\\Models\\PluginInstallation;
use Illuminate\\Http\\UploadedFile;
use RuntimeException;
use ZipArchive;

final class PluginBundleInstaller
{
    public function install(UploadedFile $bundle, PluginUploadInstaller $installer): PluginInstallation
    {
        if (strtolower($bundle->getClientOriginalExtension()) !== 'aecplugin') {
            throw new RuntimeException('Upload a signed .aecplugin bundle.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to import plugin bundles.');
        }

        $maxBytes = max(1, (int) config('plugins.max_package_bytes', 52428800));
        $size = $bundle->getSize();
        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw new RuntimeException('The plugin bundle is empty or exceeds the configured upload limit.');
        }

        $staging = (string) config('plugins.staging_directory');
        if (is_link(base_path('plugins')) || is_link($staging)) {
            throw new RuntimeException('The private plugin staging directory must not be a symbolic link.');
        }
        if (! is_dir($staging) && ! mkdir($staging, 0700, true) && ! is_dir($staging)) {
            throw new RuntimeException('Unable to create the private plugin staging directory.');
        }
        $root = realpath($staging);
        $pluginsRoot = realpath(base_path('plugins'));
        if ($root === false || $pluginsRoot === false
            || ! str_starts_with($root, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The plugin bundle staging path is not private.');
        }

        $work = $root.DIRECTORY_SEPARATOR.'bundle-'.bin2hex(random_bytes(16));
        if (! mkdir($work, 0700)) {
            throw new RuntimeException('Unable to create a private plugin bundle workspace.');
        }
        $zip = new ZipArchive();
        try {
            if ($zip->open($bundle->getRealPath(), ZipArchive::RDONLY) !== true || $zip->numFiles !== 2) {
                throw new RuntimeException('A plugin bundle must contain exactly release.json and package.zip.');
            }

            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);
                if (! is_string($name) || ! is_array($stat) || ! in_array($name, ['release.json', 'package.zip'], true)
                    || isset($entries[$name]) || (int) ($stat['size'] ?? -1) < 1) {
                    throw new RuntimeException('The plugin bundle contains an unexpected, duplicate or empty entry.');
                }
                $limit = $name === 'release.json' ? 32768 : $maxBytes;
                if ((int) $stat['size'] > $limit) {
                    throw new RuntimeException('A plugin bundle entry exceeds its size limit.');
                }
                $entries[$name] = $i;
            }
            if (count($entries) !== 2) {
                throw new RuntimeException('The plugin bundle is missing release metadata or its ZIP payload.');
            }

            $releaseBytes = $zip->getFromIndex($entries['release.json']);
            $packageBytes = $zip->getFromIndex($entries['package.zip']);
            if (! is_string($releaseBytes) || ! is_string($packageBytes) || strlen($packageBytes) > $maxBytes) {
                throw new RuntimeException('Unable to read the complete plugin bundle contents.');
            }
            try {
                $release = json_decode($releaseBytes, true, 32, JSON_THROW_ON_ERROR);
            } catch (\\JsonException $exception) {
                throw new RuntimeException('The plugin release metadata is not valid JSON.', previous: $exception);
            }
            if (! is_array($release) || array_is_list($release)
                || ! is_array($release['manifest'] ?? null) || array_is_list($release['manifest'])
                || ! is_string($release['signature'] ?? null) || ! is_string($release['key_id'] ?? null)
                || array_diff(array_keys($release), ['manifest', 'signature', 'key_id']) !== []) {
                throw new RuntimeException('The plugin release metadata must contain only manifest, signature and key_id.');
            }

            $packagePath = $work.DIRECTORY_SEPARATOR.'package.zip';
            if (file_put_contents($packagePath, $packageBytes, LOCK_EX) !== strlen($packageBytes)) {
                throw new RuntimeException('Unable to stage the plugin ZIP payload.');
            }
            chmod($packagePath, 0600);
            $innerUpload = new UploadedFile($packagePath, 'plugin.zip', 'application/zip', UPLOAD_ERR_OK, true);

            return $installer->install(
                $innerUpload,
                json_encode($release['manifest'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $release['signature'],
                $release['key_id'],
            );
        } finally {
            $zip->close();
            if (is_dir($work) && ! is_link($work)) {
                foreach (glob($work.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    if (is_file($file) && ! is_link($file)) unlink($file);
                }
                rmdir($work);
            }
        }
    }
}
