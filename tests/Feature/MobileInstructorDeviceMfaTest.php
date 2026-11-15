<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileInstructorDeviceMfaTest extends TestCase
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

    public function test_teacher_mobile_login_requires_sms_before_issuing_an_api_token(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;

            return Http::response(['sid' => 'SM-test'], 201);
        });

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
        ]);
        $deviceId = str_repeat('a', 64);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'Aloms Instructor App',
            'device_id' => $deviceId,
        ])->assertAccepted()
            ->assertJsonPath('status', 'phone_verification_required');

        $challenge = $login->json('challenge_token');
        $device = StudentDevice::where('user_id', $teacher->id)->firstOrFail();

        $this->assertSame('pending_phone_verification', $device->status);
        $this->assertNotNull($code);
        $this->assertDatabaseCount('api_tokens', 0);

        $this->postJson('/api/v1/auth/verify-device', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('user.id', $teacher->id)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonStructure(['access_token', 'expires_at']);

        $this->assertSame('approved', $device->fresh()->status);
        $this->assertNotNull($teacher->fresh()->phone_verified_at);
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_second_teacher_mobile_device_waits_for_admin_approval_before_token_issue(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code is ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;

            return Http::response(['sid' => 'SM-test'], 201);
        });

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
        ]);
        StudentDevice::create([
            'user_id' => $teacher->id,
            'device_hash' => hash('sha256', str_repeat('b', 64)),
            'device_name' => 'Previously trusted instructor device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'New instructor device',
            'device_id' => str_repeat('c', 64),
        ])->assertAccepted();

        $challenge = $login->json('challenge_token');
        $this->assertNotNull($code);

        $this->postJson('/api/v1/auth/verify-device', [
            'challenge_token' => $challenge,
            'code' => $code,
        ])->assertAccepted()
            ->assertJsonPath('status', 'pending_admin_approval');

        $pending = StudentDevice::where('user_id', $teacher->id)
            ->where('device_hash', hash('sha256', str_repeat('c', 64)))
            ->firstOrFail();

        $this->assertSame('pending_admin_approval', $pending->status);
        $this->assertDatabaseCount('api_tokens', 0);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)
            ->post(route('admin.student-devices.approve', $pending))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->postJson('/api/v1/auth/device-status', [
            'challenge_token' => $challenge,
        ])->assertOk()
            ->assertJsonPath('user.id', $teacher->id)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonStructure(['access_token']);

        $this->assertSame('approved', $pending->fresh()->status);
        $this->assertDatabaseCount('api_tokens', 1);
    }

    public function test_legacy_teacher_api_tokens_without_an_approved_device_are_rejected(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
        ]);
        $plainToken = str_repeat('legacy-token-', 5).'legacy-token';
        $token = ApiToken::create([
            'user_id' => $teacher->id,
            'student_device_id' => null,
            'name' => 'Legacy instructor app token',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_teacher_mobile_login_requires_a_device_identifier(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'Aloms Instructor App',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('device_id');
    }

    public function test_teacher_mobile_login_requires_a_registered_phone_number(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'Aloms Instructor App',
            'device_id' => str_repeat('d', 64),
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'A registered mobile number is required for device verification. Contact the academy administrator.');
    }
}
