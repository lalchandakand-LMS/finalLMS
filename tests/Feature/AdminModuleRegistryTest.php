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


    public function test_only_platform_foundations_are_protected_and_every_business_feature_is_optional(): void
    {
        $definitions = config('modules');
        $this->assertSame(['core', 'identity'], array_keys(array_filter(
            $definitions,
            fn (array $definition): bool => (bool) ($definition['protected'] ?? false)
        )));

        foreach ($definitions as $key => $definition) {
            if ($key === 'core' || $key === 'identity') {
                $this->assertSame('core', $definition['type'], $key.' must remain a protected platform foundation.');
                continue;
            }

            $this->assertSame('module', $definition['type'], $key.' must be an independently managed business module.');
            $this->assertFalse((bool) ($definition['protected'] ?? false), $key.' must not be marked as protected core.');
        }
    }

    public function test_every_module_dependency_resolves_to_a_declared_core_or_business_module(): void
    {
        $definitions = config('modules');
        $keys = array_keys($definitions);

        foreach ($definitions as $key => $definition) {
            foreach ($definition['dependencies'] ?? [] as $dependency) {
                $this->assertContains(
                    $dependency,
                    $keys,
                    "Module [{$key}] declares missing dependency [{$dependency}]."
                );
            }
        }
    }

    public function test_required_business_features_are_independent_catalogue_entries(): void
    {
        $catalog = app(ModuleRegistry::class)->catalog();

        foreach ([
            'branch_management',
            'student_management',
            'teacher_management',
            'guardian_management',
            'course_management',
            'lesson_management',
            'live_classes',
            'notice_board',
            'sms',
            'email',
            'mobile_verification',
            'otp_verification',
            'sms_otp',
            'email_otp',
            'gmail',
            'course_builder',
            'course_content_studio',
            'blog_post_builder',
            'page_builder',
            'ai_builder',
            'ai_developer',
        ] as $key) {
            $this->assertArrayHasKey($key, $catalog, "Expected independent module [{$key}].");
            $this->assertSame('module', $catalog[$key]['type']);
        }
    }

    public function test_admin_can_view_module_catalog(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('LMS Module Manager')
            ->assertSee('Course Management')
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
    public function test_runtime_middleware_blocks_a_disabled_module_and_allows_an_enabled_module(): void
    {
        Route::middleware('module.enabled:slider')->get('/_test/module-slider', fn () => response('enabled'));

        Setting::setValue('module_registry', json_encode(['slider' => false]));
        $this->get('/_test/module-slider')->assertNotFound();

        Setting::setValue('module_registry', json_encode(['slider' => true]));
        $this->get('/_test/module-slider')->assertOk()->assertSeeText('enabled');
    }

}
