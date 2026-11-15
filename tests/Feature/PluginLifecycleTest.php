<?php

namespace Tests\Feature;

use App\Models\PluginInstallation;
use App\Services\Plugins\PluginLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

class PluginLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function plugin(array $overrides = []): PluginInstallation
    {
        return PluginInstallation::query()->create(array_merge([
            'plugin_key' => 'aec.course-management',
            'name' => 'Course Management',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'discovered',
            'manifest' => ['schema_version' => 1, 'key' => 'aec.course-management'],
        ], $overrides));
    }

    public function test_registry_persists_manifest_and_lifecycle_status(): void
    {
        $this->plugin();

        $updated = app(PluginLifecycle::class)->transition('aec.course-management', 'validated');

        $this->assertSame('validated', $updated->status);
        $this->assertSame('aec.course-management', $updated->manifest['key']);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'validated',
        ]);
    }

    public function test_lifecycle_rejects_skipped_or_unknown_states(): void
    {
        $this->plugin();

        try {
            app(PluginLifecycle::class)->transition('aec.course-management', 'active');
            $this->fail('Activation must not skip validation, staging and installation.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Invalid plugin lifecycle transition', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        app(PluginLifecycle::class)->transition('aec.course-management', 'loaded');
    }

    public function test_activation_requires_signature_metadata_and_private_package_path(): void
    {
        $this->plugin(['status' => 'installed']);

        try {
            app(PluginLifecycle::class)->transition('aec.course-management', 'active');
            $this->fail('Unsigned packages must never activate.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('verified signed package', $exception->getMessage());
        }

        $plugin = PluginInstallation::query()->firstOrFail();
        $plugin->forceFill([
            'signature_verified_at' => Carbon::now(),
            'package_sha256' => str_repeat('a', 64),
            'signature_key_id' => 'release-key-2026',
            'package_path' => 'plugins/aec/aec.course-management/1.0.0',
        ])->save();

        $active = app(PluginLifecycle::class)->transition('aec.course-management', 'active');

        $this->assertSame('active', $active->status);
        $this->assertNotNull($active->activated_at);
    }

    public function test_activation_rejects_paths_outside_private_plugins_directory(): void
    {
        $this->plugin([
            'status' => 'installed',
            'signature_verified_at' => Carbon::now(),
            'package_sha256' => str_repeat('b', 64),
            'signature_key_id' => 'release-key-2026',
            'package_path' => 'public/plugins/aec/course/1.0.0',
        ]);

        $this->expectException(LogicException::class);
        app(PluginLifecycle::class)->transition('aec.course-management', 'active');
    }

    public function test_active_plugin_can_be_deactivated_without_deleting_its_registry_record(): void
    {
        $this->plugin([
            'status' => 'active',
            'signature_verified_at' => Carbon::now(),
            'package_sha256' => str_repeat('c', 64),
            'signature_key_id' => 'release-key-2026',
            'package_path' => 'plugins/aec/aec.course-management/1.0.0',
        ]);

        $inactive = app(PluginLifecycle::class)->transition('aec.course-management', 'inactive');

        $this->assertSame('inactive', $inactive->status);
        $this->assertDatabaseHas('plugin_registry', [
            'plugin_key' => 'aec.course-management',
            'status' => 'inactive',
        ]);
    }
}
