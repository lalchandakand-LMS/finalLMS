<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreNativeModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_native_core_module_workspaces(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $routes = [
            'admin.admissions.index',
            'admin.hr.index',
            'admin.library.index',
            'admin.referrals.index',
            'admin.carousels.index',
            'admin.integrations.index',
            'admin.marketing.index',
            'admin.ai-assistant.settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }
}
