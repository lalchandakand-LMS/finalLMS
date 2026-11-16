<?php

namespace App\Services\Plugins;

use App\Models\PluginInstallation;
use RuntimeException;

final class PluginDiscoveryService
{
    public function __construct(private readonly PluginManifestValidator $manifestValidator)
    {
    }

    /**
     * Safely inspect private plugins/<vendor>/<slug>/<version>/plugin.json manifests without
     * executing any plugin PHP code or auto-activating discovered packages.
     *
     * @return array{scanned: int, discovered: int, already_registered: int, skipped: list<array{path: string, reason: string}>}
     */
    public function discover(): array
    {
        $pluginsRootPath = base_path('plugins');
        if (is_link($pluginsRootPath)) {
            throw new RuntimeException('The private plugins directory must not be a symbolic link.');
        }

        $pluginsRoot = realpath($pluginsRootPath);
        if ($pluginsRoot === false || ! is_dir($pluginsRoot)) {
            return ['scanned' => 0, 'discovered' => 0, 'already_registered' => 0, 'skipped' => []];
        }

        $scanned = 0;
        $discovered = 0;
        $alreadyRegistered = 0;
        $skipped = [];

        foreach ($this->childDirectories($pluginsRoot) as $vendorDir) {
            $vendor = basename($vendorDir);
            if (str_starts_with($vendor, '.')) {
                continue;
            }

            foreach ($this->childDirectories($vendorDir) as $slugDir) {
                $slug = basename($slugDir);
                if (str_starts_with($slug, '.')) {
                    continue;
                }

                foreach ($this->childDirectories($slugDir) as $versionDir) {
                    $version = basename($versionDir);
                    $manifestPath = $versionDir.DIRECTORY_SEPARATOR.'plugin.json';
                    $relativePackagePath = 'plugins/'.$vendor.'/'.$slug.'/'.$version;

                    if (is_link($manifestPath) || ! is_file($manifestPath)) {
                        continue;
                    }

                    $scanned++;
                    $realManifest = realpath($manifestPath);
                    if ($realManifest === false
                        || ! str_starts_with($realManifest, rtrim($pluginsRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                        $skipped[] = ['path' => $relativePackagePath, 'reason' => 'Manifest resolves outside private plugins directory.'];
                        continue;
                    }

                    try {
                        $manifest = json_decode((string) file_get_contents($realManifest), true, 64, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $skipped[] = ['path' => $relativePackagePath, 'reason' => 'Malformed plugin.json.'];
                        continue;
                    }

                    if (! is_array($manifest) || array_is_list($manifest)) {
                        $skipped[] = ['path' => $relativePackagePath, 'reason' => 'Manifest must be a JSON object.'];
                        continue;
                    }

                    $errors = $this->manifestValidator->errors($manifest);
                    if ($errors !== []) {
                        $skipped[] = ['path' => $relativePackagePath, 'reason' => implode(' ', $errors)];
                        continue;
                    }

                    [$manifestVendor, $manifestSlug] = explode('.', (string) $manifest['key'], 2);
                    $expectedRelative = 'plugins/'.$manifestVendor.'/'.str_replace('.', '-', $manifestSlug).'/'.$manifest['version'];
                    if (! hash_equals($expectedRelative, $relativePackagePath)) {
                        $skipped[] = ['path' => $relativePackagePath, 'reason' => 'Directory path does not match canonical manifest key/version.'];
                        continue;
                    }

                    $existing = PluginInstallation::query()->where('plugin_key', (string) $manifest['key'])->first();
                    if ($existing) {
                        $alreadyRegistered++;
                        continue;
                    }

                    $plugin = new PluginInstallation();
                    $plugin->forceFill([
                        'plugin_key' => (string) $manifest['key'],
                        'name' => (string) $manifest['name'],
                        'version' => (string) $manifest['version'],
                        'type' => (string) $manifest['type'],
                        'status' => 'discovered',
                        'manifest' => $manifest,
                        'package_path' => $expectedRelative,
                        'health_status' => 'unknown',
                        'health_message' => 'Discovered in private plugins directory. Upload a signed package archive before activation.',
                    ])->save();

                    $discovered++;
                }
            }
        }

        return [
            'scanned' => $scanned,
            'discovered' => $discovered,
            'already_registered' => $alreadyRegistered,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $parent): array
    {
        $entries = @scandir($parent);
        if (! is_array($entries)) {
            return [];
        }

        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $parent.DIRECTORY_SEPARATOR.$entry;
            if (! is_link($full) && is_dir($full)) {
                $dirs[] = $full;
            }
        }

        sort($dirs, SORT_STRING);

        return $dirs;
    }
}
