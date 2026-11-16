<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_authenticated_changes_are_logged_without_request_payloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'home_title' => 'Alom English Center',
            ])
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'method' => 'POST',
            'route' => 'admin.settings.update',
        ]);

        $this->assertSame(1, ActivityLog::count());
        $this->assertDatabaseMissing('activity_logs', [
            'description' => 'Alom English Center',
        ]);
    }

    public function test_only_admin_can_view_activity_logs(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();
    }
}
