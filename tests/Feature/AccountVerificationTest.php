<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_register_and_receives_an_email_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'role' => 'admin',
        ])->assertRedirect(route('verification.notice'));

        $student = User::where('email', 'new.student@example.com')->firstOrFail();

        $this->assertSame('student', $student->role);
        $this->assertNull($student->email_verified_at);
        Notification::assertSentTo($student, VerifyEmail::class);
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_signed_email_verification_link_marks_the_account_as_verified(): void
    {
        $student = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $student->id, 'hash' => sha1($student->email)]
        );

        $this->actingAs($student)
            ->get($verificationUrl)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($student->fresh()->email_verified_at);
    }
}
