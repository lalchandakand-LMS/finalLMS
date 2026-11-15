<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminVerificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_email_and_phone_verification_for_all_requested_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        foreach (['student', 'guardian', 'teacher', 'branch_manager'] as $role) {
            User::factory()->create([
                'name' => ucwords(str_replace('_', ' ', $role)).' Account',
                'role' => $role,
                'email_verified_at' => $role === 'student' ? now() : null,
                'phone_number' => '+8801'.str_pad((string) random_int(300000000, 999999999), 9, '0', STR_PAD_LEFT),
                'phone_verified_at' => $role === 'teacher' ? now() : null,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.verification-center.index'))
            ->assertOk()
            ->assertSee('Student Account')
            ->assertSee('Guardian Account')
            ->assertSee('Teacher Account')
            ->assertSee('Branch Manager Account')
            ->assertSee('Email verified')
            ->assertSee('Mobile verified');
    }

    public function test_non_admin_cannot_access_verification_center(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);

        $this->actingAs($teacher)
            ->get(route('admin.verification-center.index'))
            ->assertForbidden();
    }

    public function test_admin_can_resend_unverified_email_notification(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => null]);

        $this->actingAs($admin)
            ->post(route('admin.verification-center.email', $student))
            ->assertRedirect();

        Notification::assertSentTo($student, VerifyEmail::class);
    }

    public function test_admin_can_send_phone_otp_without_marking_number_verified(): void
    {
        Http::fake();
        config([
            'services.twilio.sid' => 'test-account-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15005550006',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.verification-center.phone', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertNull($student->phone_verified_at);
        $this->assertNotNull($student->phone_verification_code);
        $this->assertTrue($student->phone_verification_expires_at->isFuture());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com')
            && $request['To'] === '+8801712345678'
            && preg_match('/\b[0-9]{6}\b/', $request['Body']) === 1);
    }

    public function test_admin_cannot_resend_phone_otp_for_verified_number(): void
    {
        Http::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email_verified_at' => now(),
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.verification-center.phone', $teacher))
            ->assertRedirect();

        Http::assertNothingSent();
    }
}
