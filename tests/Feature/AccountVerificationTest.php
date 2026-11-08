<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use Illuminate\Http\UploadedFile;
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

        $course = Course::factory()->create(['status' => 'published']);
        $this->post(route('register.store'), [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'phone_number' => '+8801712345678',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'role' => 'admin',
            'source_channel' => 'website',
            'school_name' => 'Demo School',
            'class_name' => 'Class 8',
            'course_id' => $course->id,
            'current_address' => 'Current address',
            'current_post_office' => 'Current PO',
            'current_district' => 'Dhaka',
            'current_division' => 'Dhaka',
            'current_country' => 'Bangladesh',
            'permanent_address' => 'Permanent address',
            'permanent_post_office' => 'Permanent PO',
            'permanent_district' => 'Dhaka',
            'permanent_division' => 'Dhaka',
            'permanent_country' => 'Bangladesh',
            'living_type' => 'non_residential',
            'special_quota' => 'none',
            'father' => ['name' => 'Father Name', 'mobile' => '+8801711111111', 'nid_front' => UploadedFile::fake()->image('parent-front.jpg'), 'nid_back' => UploadedFile::fake()->image('parent-back.jpg')],
            'profile_photo' => UploadedFile::fake()->image('student.jpg'),
            'privacy_consent' => '1',
        ])->assertRedirect(route('login'));

        $student = User::where('email', 'new.student@example.com')->firstOrFail();

        $this->assertSame('student', $student->role);
        $this->assertNull($student->email_verified_at);
        Notification::assertSentTo($student, VerifyEmail::class);
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
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
