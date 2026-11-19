<?php

namespace Tests\\Feature;

use App\\Models\\ApiToken;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class MobileInstructorDeviceMfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_login_with_password_without_student_device_or_phone_verification(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'phone_number' => null]);
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email, 'password' => 'password', 'device_name' => 'Teacher App',
        ])->assertOk()->assertJsonPath('user.id', $teacher->id)
          ->assertJsonPath('user.role', 'teacher')
          ->assertJsonStructure(['access_token', 'expires_at', 'token_type']);

        $token = ApiToken::query()->where('user_id', $teacher->id)->firstOrFail();
        $this->assertNull($token->student_device_id);
        $this->withToken($response->json('access_token'))->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('user.role', 'teacher');
    }

    public function test_teacher_can_use_a_valid_legacy_token_without_device_binding(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $plainToken = Str::random(80);
        $token = ApiToken::query()->create([
            'user_id' => $teacher->id, 'student_device_id' => null, 'name' => 'Legacy teacher token',
            'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonPath('user.id', $teacher->id);
        $this->assertNull($token->fresh()->revoked_at);
    }

    public function test_student_login_still_requires_a_device_identifier_and_registered_phone(): void
    {
        $student = User::factory()->create(['role' => 'student', 'phone_number' => null]);
        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email, 'password' => 'password', 'device_name' => 'Student App',
        ])->assertUnprocessable()->assertJsonPath('message', 'A secure mobile installation identifier is required for student sign-in.');

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email, 'password' => 'password', 'device_name' => 'Student App',
            'device_id' => str_repeat('a', 64),
        ])->assertUnprocessable()->assertJsonPath('message', 'A registered mobile number is required for device verification. Contact the academy administrator.');

        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertDatabaseCount('student_devices', 0);
    }

    public function test_student_cannot_use_an_unbound_bearer_token(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $plainToken = Str::random(80);
        $token = ApiToken::query()->create([
            'user_id' => $student->id, 'student_device_id' => null, 'name' => 'Unbound student token',
            'token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertNotNull($token->fresh()->revoked_at);
    }
}
