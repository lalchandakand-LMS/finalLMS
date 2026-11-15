<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AllRoleDeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.twilio.sid' => 'AC-test-account',
            'services.twilio.token' => 'test-auth-token',
            'services.twilio.from' => '+15005550006',
        ]);
    }

    public function test_teacher_branch_manager_and_guardian_need_sms_otp_on_a_new_device(): void
    {
        foreach (['teacher', 'branch_manager', 'guardian', 'accountant'] as $index => $role) {
            $code = null;
            Http::fake(function ($request) use (&$code) {
                preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
                $code = $matches[1] ?? null;
                return Http::response(['sid' => 'SM-test'], 201);
            });

            $user = User::factory()->create([
                'role' => $role,
                'email_verified_at' => now(),
                'phone_number' => '+88017'.random_int(10000000, 99999999),
            ]);

            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($index + 10)])->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('student-device.verify'));
            $device = StudentDevice::where('user_id', $user->id)->firstOrFail();
            $this->assertSame('pending_phone_verification', $device->status);
            $this->assertNotNull($code);

            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($index + 10)])->post(route('student-device.verify-phone'), ['code' => $code])
                ->assertRedirect('/dashboard');

            $this->assertAuthenticatedAs($user);
            $this->assertDatabaseHas('student_devices', ['id' => $device->id, 'status' => 'approved']);
            $this->assertDatabaseHas('device_sessions', [
                'user_id' => $user->id,
                'student_device_id' => $device->id,
                'revoked_at' => null,
            ]);

            auth()->logout();
        }
    }

    public function test_admin_login_is_registered_for_session_management_without_student_mfa_gate(): void
    {
        Http::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'phone_number' => null,
        ]);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $admin->id,
            'student_device_id' => null,
            'revoked_at' => null,
        ]);
        Http::assertNothingSent();
    }

    public function test_admin_can_review_and_revoke_a_specific_device_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student']);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'session_hash' => hash('sha256', 'fake-session-id'),
            'device_name' => 'Android · Chrome',
            'user_agent' => 'Android Chrome',
            'ip_address' => '203.0.113.10',
            'logged_in_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.security.sessions.index'))
            ->assertOk()->assertSee('Android')->assertSee($student->email);

        $this->actingAs($admin)->patch(route('admin.security.sessions.revoke', $session))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull($session->fresh()->revoked_at);
    }

    public function test_revoking_a_trusted_device_also_revokes_its_active_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student']);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', str_repeat('c', 64)),
            'device_name' => 'iOS · Safari',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $session = DeviceSession::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'session_hash' => hash('sha256', 'device-session'),
            'device_name' => 'iOS · Safari',
            'logged_in_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.student-devices.revoke', $device))
            ->assertRedirect();

        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertNotNull($session->fresh()->revoked_at);
    }
}
