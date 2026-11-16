<?php

namespace Tests\\Feature;

use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class FirstPartyPluginManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_party_module_manifests_are_declarative_and_dependency_safe(): void
    {
        $files = glob(base_path('plugins/*/plugin.json')) ?: [];
        $this->assertCount(14, $files, 'Every first-party module in this migration tranche must have a manifest in plugins/.');

        foreach ($files as $file) {
            $manifest = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            $directory = basename(dirname($file));

            $this->assertIsArray($manifest);
            $this->assertSame($directory, $manifest['key'] ?? null);
            $this->assertSame('metadata_only', $manifest['status'] ?? null);
            $this->assertFalse($manifest['safety']['execute_uploaded_code'] ?? true);
            $this->assertArrayHasKey($directory, config('modules', []));

            foreach ($manifest['dependencies'] ?? [] as $dependency) {
                $this->assertArrayHasKey($dependency, config('modules', []), "{$directory} depends on missing module {$dependency}.");
            }
        }
    }
}
