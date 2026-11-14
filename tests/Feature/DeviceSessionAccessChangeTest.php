<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceSessionAccessChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_branch_changes_revoke_existing_device_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $branch = \App\Models\Branch::create([
            'name' => 'Session Security Branch',
            'slug' => 'session-security-branch',
            'is_active' => true,
        ]);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => hash('sha256', 'existing-session'),
            'device_name' => 'Windows · Chrome',
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $student), [
                'role' => 'branch_manager',
                'branch_id' => $branch->id,
                'admin_permissions_present' => '1',
                'admin_permissions' => ['branch.students'],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertStringContainsString('role, branch, or phone changed', $session->fresh()->revoke_reason);
    }

    public function test_phone_number_change_revokes_existing_device_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801711111111',
            'phone_verified_at' => now(),
        ]);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => hash('sha256', 'phone-change-session'),
            'device_name' => 'Android · Chrome',
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.updateRole', $student), [
                'role' => 'student',
                'phone_number' => '+8801722222222',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertNull($student->fresh()->phone_verified_at);
    }
}
