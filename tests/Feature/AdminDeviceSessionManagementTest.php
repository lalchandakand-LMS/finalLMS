<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeviceSessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_revoke_another_users_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student']);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => hash('sha256', 'student-browser-session'),
            'device_name' => 'Android · Chrome',
            'user_agent' => 'Android Chrome',
            'ip_address' => '203.0.113.10',
            'logged_in_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.security.sessions.index'))
            ->assertOk()
            ->assertSee($student->email)
            ->assertSee('Android');

        $this->actingAs($admin)
            ->patch(route('admin.security.sessions.revoke', $session))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_non_admin_cannot_view_or_revoke_sessions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => hash('sha256', 'student-browser-session'),
            'device_name' => 'Android · Chrome',
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('admin.security.sessions.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->patch(route('admin.security.sessions.revoke', $session))
            ->assertForbidden();

        $this->assertNull($session->fresh()->revoked_at);
    }

    public function test_revoked_tracked_session_is_logged_out_on_its_next_request(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student);
        $sessionHash = hash('sha256', $this->app['session']->getId());

        DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => $sessionHash,
            'device_name' => 'Windows · Edge',
            'logged_in_at' => now()->subHour(),
            'last_seen_at' => now(),
            'revoked_at' => now(),
            'revoke_reason' => 'Test revocation',
        ]);

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_admin_cannot_revoke_the_session_currently_used_to_manage_the_lms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $session = DeviceSession::create([
            'user_id' => $admin->id,
            'session_hash' => hash('sha256', $this->app['session']->getId()),
            'device_name' => 'Windows · Chrome',
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->patch(route('admin.security.sessions.revoke', $session))
            ->assertStatus(422);

        $this->assertNull($session->fresh()->revoked_at);
    }
}
