<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_first_party_modules_are_enabled_by_default_for_backward_compatibility(): void
    {
        $catalog = app(ModuleRegistry::class)->catalog();

        $this->assertNotEmpty($catalog);
        $this->assertContains('slider', array_keys($catalog));
        foreach ($catalog as $module) {
            $this->assertTrue($module['enabled'], $module['key'].' should remain enabled by default.');
        }
    }

    public function test_admin_can_view_module_catalog(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('LMS Module Manager')
            ->assertSee('Academic Management')
            ->assertSee('Payment Services')
            ->assertSee('Website Slider & Banners')
            ->assertSee('Existing routes are not all gated yet');
    }

    public function test_admin_can_disable_leaf_module_and_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'slider'), ['enabled' => false])
            ->assertRedirect(route('admin.modules.index'));

        $saved = json_decode((string) Setting::value('module_registry'), true);
        $this->assertFalse($saved['slider']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'event' => 'admin.modules.updated',
            'description' => 'Disabled LMS module: slider',
        ]);
    }

    public function test_protected_core_modules_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'core'), ['enabled' => false])
            ->assertStatus(422);

        $this->assertTrue(app(ModuleRegistry::class)->isEnabled('core'));
    }

    public function test_module_cannot_be_disabled_while_an_enabled_module_depends_on_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'website_cms'), ['enabled' => false])
            ->assertStatus(422);
    }

    public function test_module_cannot_be_enabled_until_dependencies_are_enabled(): void
    {
        Setting::setValue('module_registry', json_encode(['website_cms' => false, 'storage' => false, 'slider' => false]));
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'slider'), ['enabled' => true])
            ->assertStatus(422);
    }

    public function test_unknown_module_key_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.modules.update', 'unknown_module'), ['enabled' => false])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_view_or_change_module_registry(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)->get(route('admin.modules.index'))->assertForbidden();
        $this->patch(route('admin.modules.update', 'slider'), ['enabled' => false])->assertForbidden();
        $this->assertDatabaseCount('activity_logs', 0);
    }
}
