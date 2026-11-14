<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentDeviceApprovalTest extends TestCase
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

    public function test_first_student_device_requires_sms_verification_and_is_trusted_after_success(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;
            return Http::response(['sid' => 'SM-test'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
        ]);

        $this->post(route('login.post'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student-device.verify'))
            ->assertSessionHas('student_device_request_id');

        $device = StudentDevice::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending_phone_verification', $device->status);
        $this->assertNotNull($code);

        $this->post(route('student-device.verify-phone'), ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($student);
        $this->assertSame($device->id, session('student_device_id'));
        $this->assertDatabaseHas('student_devices', [
            'id' => $device->id,
            'status' => 'approved',
        ]);
        $this->assertNotNull($student->fresh()->phone_verified_at);
    }

    public function test_additional_device_requires_phone_verification_then_admin_approval(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;
            return Http::response(['sid' => 'SM-test'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
        ]);
        $existingDevice = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', str_repeat('a', 64)),
            'device_name' => 'Android · Chrome',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $existingSession = DeviceSession::create([
            'user_id' => $student->id,
            'student_device_id' => $existingDevice->id,
            'session_hash' => hash('sha256', 'existing-student-browser-session'),
            'device_name' => 'Android · Chrome',
            'logged_in_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        $newDeviceToken = str_repeat('b', 64);
        $this->withCookie('lms_student_device', $newDeviceToken)
            ->post(route('login.post'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student-device.verify'));

        $pending = StudentDevice::where('user_id', $student->id)
            ->where('device_hash', hash('sha256', $newDeviceToken))
            ->firstOrFail();

        $this->post(route('student-device.verify-phone'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        $this->assertSame('pending_admin_approval', $pending->fresh()->status);
        $this->assertGuest();

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('admin.student-devices.approve', $pending))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_devices', [
            'id' => $pending->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('student_devices', [
            'id' => $existingDevice->id,
            'status' => 'revoked',
        ]);

        $this->assertNotNull($existingSession->fresh()->revoked_at);

        auth()->logout();
        $this->actingAs($student)
            ->withSession(['student_device_id' => $existingDevice->id])
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');
        $this->assertGuest();
        $this->withCookie('lms_student_device', $newDeviceToken)
            ->post(route('login.post'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($student);
    }


    public function test_revoked_previously_trusted_device_requires_admin_approval_again(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;
            return Http::response(['sid' => 'SM-test'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
        ]);
        $token = str_repeat('c', 64);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', $token),
            'device_name' => 'Windows · Edge',
            'status' => 'revoked',
            'phone_verified_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
        ]);

        $this->withCookie('lms_student_device', $token)
            ->post(route('login.post'), ['email' => $student->email, 'password' => 'password'])
            ->assertRedirect(route('student-device.verify'));

        $this->post(route('student-device.verify-phone'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        $this->assertSame('pending_admin_approval', $device->fresh()->status);
        $this->assertGuest();
    }

    public function test_non_admin_cannot_open_device_approval_dashboard(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->get(route('admin.student-devices.index'))
            ->assertForbidden();
    }

    public function test_student_without_phone_number_cannot_start_a_device_login(): void
    {
        Http::fake();
        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => null,
        ]);

        $this->post(route('login.post'), ['email' => $student->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_teacher_guardian_branch_manager_and_accountant_logins_require_sms_verification(): void
    {
        foreach (['teacher', 'guardian', 'branch_manager', 'accountant'] as $index => $role) {
            $code = null;
            Http::fake(function ($request) use (&$code) {
                preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
                $code = $matches[1] ?? null;

                return Http::response(['sid' => 'SM-test'], 201);
            });

            $user = User::factory()->create([
                'role' => $role,
                'email_verified_at' => now(),
                'phone_number' => '+88017'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
            ]);

            $this->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route('student-device.verify'))
                ->assertSessionHas('student_device_request_id');

            // Give each role an isolated client IP so the route's real 5/minute
            // throttle does not make this role-matrix test order-dependent.
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)]);

            $device = StudentDevice::where('user_id', $user->id)->firstOrFail();
            $this->assertSame('pending_phone_verification', $device->status);
            $this->assertNotNull($code);

            $this->post(route('student-device.verify-phone'), ['code' => $code])
                ->assertRedirect('/dashboard');

            $this->assertAuthenticatedAs($user);
            $this->assertDatabaseHas('student_devices', [
                'id' => $device->id,
                'status' => 'approved',
            ]);
            auth()->logout();
        }
    }


    public function test_revoked_trusted_devices_log_out_every_non_admin_role(): void
    {
        foreach (['student', 'teacher', 'guardian', 'branch_manager', 'accountant'] as $index => $role) {
            $user = User::factory()->create([
                'role' => $role,
                'email_verified_at' => now(),
                'phone_number' => '+88017'.str_pad((string) (20000000 + $index), 8, '0', STR_PAD_LEFT),
            ]);
            $token = str_repeat(dechex($index + 1), 64);
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', $token),
                'device_name' => 'Trusted test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);

            $this->withCookie('lms_student_device', $token)
                ->post(route('login.post'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/dashboard');

            $this->assertAuthenticatedAs($user);
            $device->forceFill(['status' => 'revoked'])->save();

            $this->get('/dashboard')
                ->assertRedirect(route('login'))
                ->assertSessionHas('warning');
            $this->assertGuest();
        }
    }


    public function test_existing_non_admin_sessions_without_a_trusted_device_must_reauthenticate(): void
    {
        foreach (['student', 'teacher', 'guardian', 'branch_manager', 'accountant'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertRedirect(route('login'))
                ->assertSessionHas('warning');

            $this->assertGuest();
        }
    }

    public function test_admin_password_login_remains_available_without_sms_for_recovery(): void
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
        Http::assertNothingSent();
    }

}
