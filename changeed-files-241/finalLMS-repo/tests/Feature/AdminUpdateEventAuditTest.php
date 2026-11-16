<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUpdateEventAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_update_check_failure_is_saved_and_visible_after_refresh(): void
    {
        config(['services.lms_updates.enabled' => false]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.check'))
            ->assertRedirect(route('admin.updates.index'));

        $this->assertDatabaseHas('system_update_events', [
            'performed_by' => $admin->id,
            'event' => 'release_check',
            'status' => 'failed',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('Update activity & audit trail')
            ->assertSee('Release check')
            ->assertSee('Release checks are disabled until a trusted release key and manifest URL are configured.');
    }

    public function test_non_admin_cannot_read_the_update_audit_timeline(): void
    {
        foreach (['student', 'teacher', 'branch_manager', 'accountant', 'guardian'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.updates.index'))
                ->assertForbidden();
        }
    }
}
