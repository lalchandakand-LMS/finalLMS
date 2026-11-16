<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_view_system_health(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.system-health.index'))->assertForbidden();
    }

    public function test_admin_can_run_and_view_redacted_health_checks(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.system-health.run'))
            ->assertRedirect(route('admin.system-health.index'));

        $this->assertDatabaseHas('system_health_checks', ['status' => 'healthy']);
        $record = DB::table('system_health_checks')->latest('checked_at')->first();
        $results = json_decode($record->results, true);
        $this->assertArrayHasKey('database', $results);
        $this->assertArrayHasKey('cache', $results);
        $this->assertArrayHasKey('storage', $results);
        $this->assertArrayHasKey('queue', $results);
        $this->actingAs($admin)->get(route('admin.system-health.index'))
            ->assertOk()
            ->assertSee('Recent health checks');
    }

    public function test_scheduled_health_check_records_dependency_results(): void
    {
        $this->artisan('system:health-check')->assertExitCode(0);

        $this->assertDatabaseHas('system_health_checks', ['status' => 'healthy']);
        $record = DB::table('system_health_checks')->latest('checked_at')->first();
        $results = json_decode($record->results, true);
        $this->assertArrayHasKey('database', $results);
        $this->assertArrayHasKey('cache', $results);
        $this->assertArrayHasKey('storage', $results);
        $this->assertArrayHasKey('queue', $results);
    }

    public function test_scheduler_heartbeat_command_records_execution_time(): void
    {
        $this->artisan('system:heartbeat')->assertExitCode(0);

        $this->assertDatabaseHas('system_health_heartbeats', ['name' => 'scheduler']);
    }
}
