<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\StudentAdmissionProfile;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAdmissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_full_admission_application_with_private_encrypted_documents(): void
    {
        Storage::fake('local');
        Event::fake();
        $course = Course::factory()->create(['status' => 'published', 'price' => 2500]);

        $response = $this->post(route('register.store'), [
            'name' => 'Demo Student',
            'email' => 'admission@example.test',
            'phone_number' => '+8801712345678',
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
            'source_channel' => 'facebook',
            'source_details' => 'Facebook page',
            'school_name' => 'Dhaka Model School',
            'class_name' => 'Class 8',
            'school_roll' => '18',
            'course_id' => $course->id,
            'course_updates_opt_in' => '1',
            'current_address' => 'House 10, Road 2',
            'current_post_office' => 'Dhanmondi',
            'current_district' => 'Dhaka',
            'current_division' => 'Dhaka',
            'current_country' => 'Bangladesh',
            'permanent_address' => 'Village address',
            'permanent_post_office' => 'Post office',
            'permanent_district' => 'Cumilla',
            'permanent_division' => 'Chattogram',
            'permanent_country' => 'Bangladesh',
            'living_type' => 'non_residential',
            'special_quota' => 'disability',
            'special_quota_notes' => 'Requires accessible classroom',
            'profile_photo' => UploadedFile::fake()->image('student.jpg'),
            'quota_document' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            'father' => [
                'name' => 'Father Name',
                'mobile' => '+8801812345678',
                'occupation' => 'Teacher',
                'nid_number' => '1234567890123',
                'nid_front' => UploadedFile::fake()->image('father-front.jpg'),
                'nid_back' => UploadedFile::fake()->image('father-back.jpg'),
            ],
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect(route('login'));
        $student = User::where('email', 'admission@example.test')->firstOrFail();
        $profile = StudentAdmissionProfile::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending', $profile->admission_status);
        $this->assertSame('Class 8', $profile->class_name);
        $this->assertSame('2500.00', $profile->quoted_course_price);
        $this->assertDatabaseHas('student_guardians', ['student_id' => $student->id, 'name' => 'Father Name']);

        $guardian = StudentGuardian::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('1234567890123', $guardian->nid_number);
        $rawNid = $guardian->getRawOriginal('nid_number');
        $this->assertNotSame('1234567890123', $rawNid);
        $this->assertNotNull(Crypt::decryptString($rawNid));

        $documents = StudentDocument::where('student_id', $student->id)->get();
        $this->assertCount(4, $documents);
        foreach ($documents as $document) {
            $this->assertStringEndsWith('.enc', $document->encrypted_path);
            Storage::disk('local')->assertExists($document->encrypted_path);
            $stored = Storage::disk('local')->get($document->encrypted_path);
            $this->assertNotSame('', Crypt::decryptString($stored));
        }
        $this->assertGuest();
    }

    public function test_registration_requires_camera_photo_parent_contact_and_quota_evidence(): void
    {
        $course = Course::factory()->create(['status' => 'published']);
        $this->from('/register')->post(route('register.store'), [
            'name' => 'Missing Documents',
            'email' => 'missing@example.test',
            'phone_number' => '+8801712345678',
            'password' => 'StrongPassword!123',
            'password_confirmation' => 'StrongPassword!123',
            'source_channel' => 'website',
            'school_name' => 'School',
            'class_name' => 'Seven',
            'course_id' => $course->id,
            'current_address' => 'Address',
            'current_post_office' => 'PO',
            'current_district' => 'Dhaka',
            'current_division' => 'Dhaka',
            'current_country' => 'Bangladesh',
            'permanent_address' => 'Address',
            'permanent_post_office' => 'PO',
            'permanent_district' => 'Dhaka',
            'permanent_division' => 'Dhaka',
            'permanent_country' => 'Bangladesh',
            'living_type' => 'residential',
            'special_quota' => 'disability',
            'privacy_consent' => '1',
        ])->assertSessionHasErrors(['profile_photo', 'quota_document', 'father.nid_front']);
    }

    public function test_admin_can_review_student_admission_records_and_documents_are_not_public(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.admissions.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.admissions.index'))->assertOk();
    }
}
