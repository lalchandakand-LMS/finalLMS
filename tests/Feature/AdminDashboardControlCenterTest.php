<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_control_center(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Homepage')
            ->assertSee('Users')
            ->assertSee('Courses')
            ->assertSee('Settings')
            ->assertSee('analytics');
    }
}
