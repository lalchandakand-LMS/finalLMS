<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_save_phone_number_and_marketing_consent(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('profile.update'), [
                'name' => $student->name,
                'email' => $student->email,
                'phone_number' => '+8801712345678',
                'sms_marketing_opt_in' => '1',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'phone_number' => '+8801712345678',
            'sms_marketing_opt_in' => 1,
        ]);
    }

    public function test_student_can_verify_phone_with_a_sent_one_time_code(): void
    {
        config([
            'services.twilio.sid' => 'AC'.str_repeat('0', 32),
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15005550006',
        ]);

        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/(\d{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;

            return Http::response(['sid' => 'SM123'], 201);
        });

        $student = User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801712345678',
        ]);

        $this->actingAs($student)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Verify phone number');

        $this->actingAs($student)
            ->from(route('profile.edit'))
            ->post(route('phone.verification.send'))
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($code);
        $this->assertNull($student->fresh()->phone_verified_at);

        $this->from(route('profile.edit'))
            ->post(route('phone.verification.verify'), ['code' => $code])
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($student->fresh()->phone_verified_at);
        Http::assertSent(fn ($request) => str_contains($request['Body'], $code));
    }
}
