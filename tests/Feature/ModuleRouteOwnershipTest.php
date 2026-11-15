<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Modules\ModuleRegistry;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleRouteOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_are_blocked_when_their_owning_plugin_is_disabled(): void
    {
        Route::middleware('web')->get('/_test/slider-route', fn () => response('slider route'))
            ->name('admin.sliders.test');

        Setting::setValue('module_registry', json_encode(['slider_builder' => false]));

        $this->get('/_test/slider-route')->assertNotFound();
    }

    public function test_routes_are_available_when_their_owning_plugin_is_enabled(): void
    {
        Route::middleware('web')->get('/_test/slider-route-enabled', fn () => response('slider route'))
            ->name('admin.sliders.enabled-test');

        Setting::setValue('module_registry', json_encode(['slider_builder' => true]));

        $this->get('/_test/slider-route-enabled')->assertOk()->assertSeeText('slider route');
    }

    public function test_every_route_owner_exists_in_the_first_party_module_catalog(): void
    {
        foreach (array_merge(array_keys(config('module_routes')), array_keys(config('module_route_uris'))) as $module) {
            $this->assertArrayHasKey($module, config('modules'), $module.' must have a first-party manifest.');
        }
    }

    public function test_api_routes_are_gated_by_their_feature_and_mobile_app_plugins(): void
    {
        Route::middleware('api')->get('/api/v1/_test/mobile-feature', fn () => response('mobile feature'))
            ->name('test.mobile-feature');

        Setting::setValue('module_registry', json_encode(['mobile_api' => false]));

        $this->get('/api/v1/_test/mobile-feature')->assertNotFound();

        Setting::setValue('module_registry', json_encode(['mobile_api' => true, 'student_mobile_app' => true]));

        $this->get('/api/v1/_test/mobile-feature')->assertOk()->assertSeeText('mobile feature');
    }


    public function test_page_slider_and_carousel_routes_have_independent_plugin_owners(): void
    {
        $routes = config('module_routes');

        $this->assertContains('admin.website-builder.*', $routes['page_builder']);
        $this->assertContains('admin.website-pages.*', $routes['page_builder']);
        $this->assertContains('admin.sliders.*', $routes['slider_builder']);
        $this->assertContains('admin.carousels.*', $routes['carousel_builder']);
        $this->assertArrayHasKey('page_builder', config('modules'));
        $this->assertArrayHasKey('slider_builder', config('modules'));
        $this->assertArrayHasKey('carousel_builder', config('modules'));
        $this->assertContains('admin.academic-levels.*', $routes['academic_levels']);
        $this->assertArrayHasKey('academic_levels', config('modules'));
    }

    public function test_academic_levels_is_a_foundation_for_consumer_plugins_not_their_dependent(): void
    {
        $modules = config('modules');

        $this->assertSame(['core', 'identity'], $modules['academic_levels']['dependencies']);
        foreach (['course_management', 'batch_management', 'exam_management', 'page_builder'] as $consumer) {
            $this->assertContains('academic_levels', $modules[$consumer]['dependencies']);
        }
    }

    public function test_every_declared_plugin_dependency_exists_in_the_module_catalog(): void
    {
        foreach (config('modules', []) as $module => $manifest) {
            foreach ($manifest['dependencies'] ?? [] as $dependency) {
                $this->assertArrayHasKey(
                    $dependency,
                    config('modules', []),
                    "{$module} depends on missing module {$dependency}."
                );
            }
        }
    }

    public function test_every_uri_route_owner_exists_in_the_first_party_module_catalog(): void
    {
        foreach (array_keys(config('module_route_uris', [])) as $module) {
            $this->assertArrayHasKey($module, config('modules', []), $module.' must have a first-party manifest.');
        }
    }

    public function test_a_module_cannot_be_enabled_when_a_required_dependency_is_disabled(): void
    {
        Setting::setValue('module_registry', json_encode(['academic_levels' => false]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enable required module first: academic_levels');

        app(ModuleRegistry::class)->setEnabled('course_management', true);
    }

    public function test_a_foundation_module_cannot_be_disabled_while_consumers_are_enabled(): void
    {
        Setting::setValue('module_registry', json_encode(['academic_levels' => true]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disable dependent module first: course_management');

        app(ModuleRegistry::class)->setEnabled('academic_levels', false);
    }

    public function test_plugin_lifecycle_contract_is_composer_autoloadable(): void
    {
        $this->assertTrue(interface_exists(\App\Plugins\PluginContract::class));
    }

    public function test_core_module_manager_routes_are_not_owned_by_business_plugins(): void
    {
        $this->assertSame([], collect(config('module_routes'))
            ->filter(fn (array $patterns) => in_array('admin.modules.*', $patterns, true))
            ->all());
    }
}
