<?php

namespace Tests\Feature;

use App\Models\Setting;
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

        Setting::setValue('module_registry', json_encode(['slider' => false]));

        $this->get('/_test/slider-route')->assertNotFound();
    }

    public function test_routes_are_available_when_their_owning_plugin_is_enabled(): void
    {
        Route::middleware('web')->get('/_test/slider-route-enabled', fn () => response('slider route'))
            ->name('admin.sliders.enabled-test');

        Setting::setValue('module_registry', json_encode(['slider' => true]));

        $this->get('/_test/slider-route-enabled')->assertOk()->assertSeeText('slider route');
    }

    public function test_core_module_manager_routes_are_not_owned_by_business_plugins(): void
    {
        $this->assertSame([], collect(config('module_routes'))
            ->filter(fn (array $patterns) => in_array('admin.modules.*', $patterns, true))
            ->all());
    }
}
