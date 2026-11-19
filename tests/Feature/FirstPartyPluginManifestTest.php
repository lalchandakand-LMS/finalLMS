<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirstPartyPluginManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_party_module_manifests_are_declarative_and_dependency_safe(): void
    {
        $files = glob(base_path('plugins/*/plugin.json')) ?: [];
        $expectedKeys = collect(config('modules', []))
            ->filter(fn (array $definition): bool => ($definition['type'] ?? null) === 'module' && empty($definition['plugin_key']))
            ->keys()->sort()->values()->all();
        $actualKeys = collect($files)->map(fn (string $file): string => basename(dirname($file)))->sort()->values()->all();
        $this->assertSame($expectedKeys, $actualKeys, 'Every first-party business module without a signed package key must have a manifest in plugins/.');

        foreach ($files as $file) {
            $manifest = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            $directory = basename(dirname($file));

            $this->assertIsArray($manifest);
            $this->assertSame($directory, $manifest['key'] ?? null);
            $this->assertSame('metadata_only', $manifest['status'] ?? null);
            $this->assertFalse($manifest['safety']['execute_uploaded_code'] ?? true);
            $this->assertArrayHasKey($directory, config('modules', []));
            $this->assertSame(config("modules.{$directory}.dependencies", []), $manifest['dependencies'] ?? []);
            $this->assertSame(config("module_routes.{$directory}", []), $manifest['routes'] ?? []);

            foreach ($manifest['dependencies'] ?? [] as $dependency) {
                $this->assertArrayHasKey($dependency, config('modules', []), "{$directory} depends on missing module {$dependency}.");
            }
        }
    }

    public function test_executable_first_party_packages_have_valid_manifests_and_resolvable_dependencies(): void
    {
        $files = glob(base_path('plugins/aec/*/*/plugin.json')) ?: [];
        $this->assertNotEmpty($files, 'Expected executable first-party packages under plugins/aec/.');

        $validator = app(\\App\\Services\\Plugins\\PluginManifestValidator::class);
        $manifests = [];

        foreach ($files as $file) {
            $manifest = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            $this->assertIsArray($manifest);
            $this->assertSame([], $validator->errors($manifest), basename(dirname(dirname($file)).'/'.basename(dirname($file))).' manifest must satisfy the installable plugin contract.');

            $packageDirectory = basename(dirname($file));
            $versionDirectory = basename(dirname(dirname($file)));
            $this->assertSame($manifest['version'], $packageDirectory);
            $this->assertSame('aec.'.str_replace('-', '.', basename(dirname(dirname(dirname($file))))), $manifest['key'] ?? null);
            $manifests[$manifest['key']] = $manifest;
        }

        foreach ($manifests as $key => $manifest) {
            foreach ($manifest['dependencies'] as $dependency) {
                $this->assertArrayHasKey($dependency, $manifests, $key.' has an unresolved first-party plugin dependency: '.$dependency);
            }
        }
    }

}
