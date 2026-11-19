<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\StudentDevice;
use App\Models\User;
use App\Services\Security\AdminTotpService;
use App\Notifications\AdminMfaBootstrapCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

    public function test_admin_password_alone_does_not_authenticate_and_first_login_requires_totp_enrollment(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'phone_number' => null,
        ]);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.bootstrap'));

        $this->assertGuest();
        $this->assertSame($admin->id, session('pending_admin_mfa_user_id'));
        $this->assertNotNull(session('admin_mfa_bootstrap_code_hash'));
        $this->assertSame(hash('sha256', strtolower($admin->email)), session('admin_mfa_bootstrap_email_hash'));
        $this->assertNull(session('admin_mfa_setup_secret'), 'TOTP setup must not begin before the email bootstrap factor is verified.');
        $bootstrapCode = null;
        Notification::assertSentOnDemand(AdminMfaBootstrapCodeNotification::class, function ($notification) use (&$bootstrapCode): bool {
            $bootstrapCode = $notification->code;
            return true;
        });
        $this->assertIsString($bootstrapCode);

        $this->post(route('admin.mfa.bootstrap.verify'), ['code' => $bootstrapCode])
            ->assertRedirect(route('admin.mfa.setup'));
        $this->assertSame(hash('sha256', strtolower($admin->email)), session('admin_mfa_bootstrap_email_hash'));

        $secret = session('admin_mfa_setup_secret');
        $this->assertIsString($secret);

        $this->get(route('admin.mfa.setup'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->assertSee($admin->email)
            ->assertSee($secret);

        $code = app(AdminTotpService::class)->codeForTesting($secret);
        $this->post(route('admin.mfa.setup.confirm'), ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
        $recoveryCodes = session('admin_mfa_recovery_codes');
        $this->assertCount(8, $recoveryCodes);
        $dashboard = $this->get('/dashboard')->assertOk();
        $dashboard->assertSee($recoveryCodes[0]);
        $this->assertAuthenticatedAs($admin);
        $fresh = $admin->fresh();
        $this->assertNotNull($fresh->admin_totp_enabled_at);
        $this->assertNotEmpty($fresh->admin_totp_secret);
        $this->assertCount(8, $fresh->admin_totp_recovery_codes);
        $storedSecret = \Illuminate\Support\Facades\DB::table('users')->where('id', $admin->id)->value('admin_totp_secret');
        $this->assertNotSame($secret, $storedSecret, 'TOTP secrets must be encrypted at rest.');
        $this->assertArrayNotHasKey('admin_totp_secret', $fresh->toArray());
        $this->assertArrayNotHasKey('admin_totp_recovery_codes', $fresh->toArray());
        $this->assertDatabaseHas('device_sessions', [
            'user_id' => $admin->id,
            'student_device_id' => null,
            'revoked_at' => null,
        ]);
    }

    public function test_existing_admin_login_requires_totp_and_rejects_replayed_codes(): void
    {
        $secret = app(AdminTotpService::class)->generateSecret();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'admin_totp_secret' => $secret,
            'admin_totp_enabled_at' => now()->subMinute(),
            'admin_totp_last_used_step' => null,
            'admin_totp_recovery_codes' => [],
        ]);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.challenge'));
        $this->assertGuest();

        $code = app(AdminTotpService::class)->codeForTesting($secret);
        $this->post(route('admin.mfa.verify'), ['code' => $code])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($admin);
        $usedStep = $admin->fresh()->admin_totp_last_used_step;
        $this->assertNotNull($usedStep);

        $this->post(route('logout'));
        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.challenge'));
        $this->post(route('admin.mfa.verify'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame($usedStep, $admin->fresh()->admin_totp_last_used_step);
    }

    public function test_admin_recovery_codes_are_single_use(): void
    {
        $secret = app(AdminTotpService::class)->generateSecret();
        $recoveryCode = 'A1B2-C3D4-E5F6';
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'admin_totp_secret' => $secret,
            'admin_totp_enabled_at' => now()->subMinute(),
            'admin_totp_recovery_codes' => [app(AdminTotpService::class)->recoveryCodeHash($recoveryCode)],
        ]);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.challenge'));
        $this->post(route('admin.mfa.verify'), ['recovery_code' => $recoveryCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($admin);
        $this->assertSame([], $admin->fresh()->admin_totp_recovery_codes);

        $this->post(route('logout'));
        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.challenge'));
        $this->post(route('admin.mfa.verify'), ['recovery_code' => $recoveryCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_admin_without_a_completed_mfa_session_is_logged_out_of_protected_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'admin_totp_secret' => null,
            'admin_totp_enabled_at' => null,
        ]);

        $this->actingAsAdminWithoutMfa($admin)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
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

    public function test_admin_session_is_invalidated_if_account_role_changes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin);
        $admin->forceFill(['role' => 'student'])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }


    public function test_first_admin_enrollment_requires_verified_email_and_expiring_email_code(): void
    {
        Notification::fake();
        $unverified = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => null,
        ]);

        $this->post(route('login.post'), ['email' => $unverified->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        Notification::assertNothingSent();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.bootstrap'));

        $this->withSession(['admin_mfa_bootstrap_expires_at' => now()->subMinute()->timestamp]);
        $this->post(route('admin.mfa.bootstrap.verify'), ['code' => '123456'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }


    public function test_email_change_invalidates_pending_admin_mfa_bootstrap(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->post(route('login.post'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.bootstrap'));

        $admin->forceFill(['email' => 'changed-admin@example.test'])->save();

        $this->get(route('admin.mfa.bootstrap'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('pending_admin_mfa_user_id'));
    }


    public function test_new_admin_login_cannot_reuse_another_admins_pending_email_verification(): void
    {
        Notification::fake();
        $firstAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $secondAdmin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->post(route('login.post'), ['email' => $firstAdmin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.bootstrap'));
        $firstCode = null;
        Notification::assertSentOnDemand(AdminMfaBootstrapCodeNotification::class, function ($notification) use (&$firstCode): bool {
            $firstCode = $notification->code;
            return true;
        });
        $this->post(route('admin.mfa.bootstrap.verify'), ['code' => $firstCode])
            ->assertRedirect(route('admin.mfa.setup'));
        $this->assertNotNull(session('admin_mfa_bootstrap_verified_at'));

        $this->post(route('login.post'), ['email' => $secondAdmin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.mfa.bootstrap'));

        $this->assertGuest();
        $this->assertSame($secondAdmin->id, session('pending_admin_mfa_user_id'));
        $this->assertNull(session('admin_mfa_bootstrap_verified_at'));
        $this->get(route('admin.mfa.bootstrap'))->assertOk();
    }

}
