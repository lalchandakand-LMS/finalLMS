<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_health_and_release_guidance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.system.index'))
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Safe release procedure');
    }

    public function test_non_admin_cannot_view_system_health(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.system.index'))
            ->assertForbidden();
    }
}
