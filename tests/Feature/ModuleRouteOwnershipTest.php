<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class ModuleRouteOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_named_route_is_hidden(): void
    {
        Route::middleware('web')->get('/_test/owned-slider', fn () => response('slider'))
            ->name('admin.sliders.owned-test');
        Setting::setValue('module_registry', json_encode(['slider_builder' => false]));

        $this->get('/_test/owned-slider')->assertNotFound();
    }

    public function test_enabled_named_route_remains_available(): void
    {
        Route::middleware('web')->get('/_test/owned-slider-enabled', fn () => response('slider'))
            ->name('admin.sliders.enabled-test');
        Setting::setValue('module_registry', json_encode(['slider_builder' => true]));

        $this->get('/_test/owned-slider-enabled')->assertOk();
    }

    public function test_every_route_owner_exists_in_module_catalog(): void
    {
        $owners = array_unique(array_merge(
            array_keys(config('module_routes', [])),
            array_keys(config('module_route_uris', [])),
        ));

        foreach ($owners as $owner) {
            $this->assertArrayHasKey($owner, config('modules', []), "Missing module owner: {$owner}");
        }
    }

    public function test_api_routes_are_gated_by_mobile_api_owner(): void
    {
        Route::middleware('api')->get('/api/v1/_test/owned-api', fn () => response('api'))
            ->name('test.owned-api');
        Setting::setValue('module_registry', json_encode(['mobile_api' => false]));

        $this->get('/api/v1/_test/owned-api')->assertNotFound();
    }

    public function test_api_course_routes_are_gated_by_course_management_owner(): void
    {
        Route::middleware('api')->get('/api/v1/courses/_test-owned-course', fn () => response()->json(['ok' => true]))
            ->name('test.owned-course-api');
        Setting::setValue('module_registry', json_encode([
            'mobile_api' => true,
            'course_management' => false,
        ]));

        $this->get('/api/v1/courses/_test-owned-course')->assertNotFound();
    }

    public function test_academic_levels_are_required_by_course_batch_exam_and_page_builders(): void
    {
        $modules = config('modules');
        $this->assertSame(['core', 'identity'], $modules['academic_levels']['dependencies']);
        foreach (['course_management', 'batch_management', 'exam_management', 'page_builder'] as $consumer) {
            $this->assertContains('academic_levels', $modules[$consumer]['dependencies']);
        }
    }

    public function test_only_service_foundations_without_http_endpoints_are_unmapped(): void
    {
        $owners = array_unique(array_merge(
            array_keys(config('module_routes', [])),
            array_keys(config('module_route_uris', [])),
        ));
        $serviceOnly = ['core', 'communications', 'otp_verification', 'sms_otp', 'email_otp', 'gmail'];
        $unmapped = array_values(array_diff(array_keys(config('modules', [])), $owners));

        sort($serviceOnly);
        sort($unmapped);
        $this->assertSame($serviceOnly, $unmapped);
    }

    public function test_all_module_dependencies_exist(): void
    {
        foreach (config('modules', []) as $module => $definition) {
            foreach ($definition['dependencies'] ?? [] as $dependency) {
                $this->assertArrayHasKey($dependency, config('modules', []), "{$module} depends on missing {$dependency}");
            }
        }
    }

    public function test_foundation_cannot_be_disabled_while_consumers_are_enabled(): void
    {
        Setting::setValue('module_registry', json_encode(['academic_levels' => true]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Disable dependent module first: course_management');
        app(ModuleRegistry::class)->setEnabled('academic_levels', false);
    }
}
