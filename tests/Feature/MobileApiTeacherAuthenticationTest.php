<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiTeacherAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_sign_in_with_password_without_student_device_or_phone_verification(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'phone_number' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'Teacher App',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $teacher->id)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonStructure(['access_token', 'expires_at', 'token_type']);

        $token = $response->json('access_token');
        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $teacher->id,
            'student_device_id' => null,
            'token_hash' => hash('sha256', $token),
            'revoked_at' => null,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'teacher');
    }

    public function test_student_login_still_requires_a_secure_device_identifier(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'phone_number' => '01700000000',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'password',
            'device_name' => 'Student App',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'A secure mobile installation identifier is required for student sign-in.');

        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertDatabaseCount('student_devices', 0);
    }

    public function test_student_cannot_use_an_unbound_bearer_token(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $plainToken = Str::random(80);

        ApiToken::query()->create([
            'user_id' => $student->id,
            'student_device_id' => null,
            'name' => 'Unbound student token',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        $this->withToken($plainToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $student->id,
            'revoked_at' => now(),
        ]);
    }
}
