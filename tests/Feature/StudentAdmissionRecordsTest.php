<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentAdmissionProfile;
use App\Models\StudentCourseSession;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAdmissionRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admissionPayload(Course $course): array
    {
        return [
            'name' => 'Test Student',
            'email' => 'admission@example.com',
            'phone_number' => '+8801712345678',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'source_channel' => 'facebook',
            'source_details' => 'Facebook campaign',
            'school_name' => 'Dhaka Model School',
            'class_name' => 'Class 8',
            'school_roll' => '108',
            'course_id' => $course->id,
            'course_updates_opt_in' => '1',
            'current_address' => 'House 10, Road 2',
            'current_post_office' => 'Mirpur',
            'current_district' => 'Dhaka',
            'current_division' => 'Dhaka',
            'current_country' => 'Bangladesh',
            'permanent_address' => 'Village Road',
            'permanent_post_office' => 'Uttara',
            'permanent_district' => 'Dhaka',
            'permanent_division' => 'Dhaka',
            'permanent_country' => 'Bangladesh',
            'living_type' => 'non_residential',
            'special_quota' => 'none',
            'father' => [
                'name' => 'Student Father',
                'mobile' => '+8801711111111',
                'occupation' => 'Teacher',
                'nid_number' => '1234567890',
                'nid_front' => UploadedFile::fake()->image('father-front.jpg'),
                'nid_back' => UploadedFile::fake()->image('father-back.jpg'),
            ],
            'privacy_consent' => '1',
            'profile_photo' => UploadedFile::fake()->image('student.jpg'),
        ];
    }

    public function test_student_admission_saves_course_price_guardian_and_encrypted_documents(): void
    {
        $course = Course::factory()->create(['price' => 3500, 'status' => 'published']);
        $payload = $this->admissionPayload($course);
        $payload['course_price'] = '1.00';

        $this->post(route('register.store'), $payload)->assertRedirect(route('login'));

        $student = User::where('email', 'admission@example.com')->firstOrFail();
        $profile = StudentAdmissionProfile::where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending', $profile->admission_status);
        $this->assertSame('3500.00', $profile->quoted_course_price);
        $this->assertSame('Class 8', $profile->class_name);
        $this->assertNotNull($profile->privacy_consent_at);
        $this->assertDatabaseHas('student_documents',['student_id'=>$student->id,'document_type'=>'father_nid_front']);
        $this->assertDatabaseHas('student_documents',['student_id'=>$student->id,'document_type'=>'father_nid_back']);
        $this->assertSame('active', $student->account_status);
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'guardian_type' => 'father',
            'mobile_number' => '+8801711111111',
        ]);

        $photo = StudentDocument::where('student_id',$student->id)->where('document_type','profile_photo')->firstOrFail();
        $encrypted = Storage::disk('local')->get($photo->encrypted_path);
        $this->assertNotSame('student.jpg', $encrypted);
        $this->assertNotSame('1234567890', DB::table('student_guardians')->where('student_id',$student->id)->value('nid_number'));
        $this->assertNotEmpty(Crypt::decryptString($encrypted));
        $this->assertFalse(auth()->check(), 'Registration must not bypass the separate SMS device verification step.');
    }

    public function test_referral_code_links_a_new_registration_to_the_referring_student(): void
    {
        $referrer = User::factory()->create(['role'=>'student']);
        $referrerProfile = StudentAdmissionProfile::create([
            'user_id'=>$referrer->id,
            'admission_number'=>'AEC-2026-REF001',
            'referral_code'=>'AECREF1234',
        ]);
        $course = Course::factory()->create();
        $payload = $this->admissionPayload($course);
        $payload['referral_code'] = $referrerProfile->referral_code;
        $payload['email'] = 'referred@example.com';
        $payload['phone_number'] = '+8801712345689';

        $this->post(route('register.store'), $payload)->assertRedirect(route('login'));
        $referred = User::where('email','referred@example.com')->firstOrFail();
        $this->assertDatabaseHas('student_referrals', [
            'referrer_student_id'=>$referrer->id,
            'referred_student_id'=>$referred->id,
            'referral_code'=>'AECREF1234',
        ]);
    }

    public function test_student_can_update_admission_profile_and_request_deletion(): void
    {
        $student = User::factory()->create(['role'=>'student']);
        $course = Course::factory()->create();
        $profile = StudentAdmissionProfile::create([
            'user_id'=>$student->id,
            'admission_number'=>'AEC-2026-TEST01',
            'referral_code'=>'AECTEST123',
            'school_name'=>'Old School',
            'class_name'=>'Class 7',
            'interested_course_id'=>$course->id,
            'quoted_course_price'=>$course->price,
        ]);
        StudentGuardian::create([
            'student_id'=>$student->id,'guardian_type'=>'guardian','relationship'=>'Uncle','name'=>'Guardian One',
            'mobile_number'=>'+8801712345670','is_primary'=>true,
        ]);
        $data = [
            'school_name'=>'New School','class_name'=>'Class 9','school_roll'=>'99','course_id'=>$course->id,
            'current_address'=>'Current','current_post_office'=>'PO','current_district'=>'Dhaka','current_division'=>'Dhaka','current_country'=>'Bangladesh',
            'permanent_address'=>'Permanent','permanent_post_office'=>'PO','permanent_district'=>'Dhaka','permanent_division'=>'Dhaka','permanent_country'=>'Bangladesh',
            'living_type'=>'residential','special_quota'=>'none',
            'guardian_name'=>'Updated Guardian','guardian_relationship'=>'Uncle','guardian_mobile'=>'+8801712345670',
            'guardian_email'=>'guardian@example.com','guardian_occupation'=>'Business',
        ];
        $this->actingAs($student)->post(route('student.admission.update'),$data)->assertRedirect();
        $this->assertSame('New School',$profile->fresh()->school_name);
        $this->assertDatabaseHas('student_guardians',['student_id'=>$student->id,'name'=>'Updated Guardian']);
        $this->actingAs($student)->post(route('student.admission.delete-request'),['confirm'=>'1'])->assertRedirect();
        $this->assertNotNull($student->fresh()->deletion_requested_at);
    }

    public function test_admin_can_review_admission_and_deactivate_student_account(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $student = User::factory()->create(['role'=>'student']);
        StudentAdmissionProfile::create([
            'user_id'=>$student->id,'admission_number'=>'AEC-2026-ADM001','referral_code'=>'AECADM001',
            'admission_status'=>'pending',
        ]);
        $this->actingAs($admin)->get(route('admin.admissions.index'))->assertOk()->assertSee($student->name);
        $this->actingAs($admin)->post(route('admin.admissions.approve',$student))->assertRedirect();
        $this->assertSame('approved',$student->admissionProfile->fresh()->admission_status);
        $this->actingAs($admin)->patch(route('admin.admissions.status',$student),['account_status'=>'inactive'])->assertRedirect();
        $this->assertSame('inactive',$student->fresh()->account_status);
    }

    public function test_enrolled_student_can_check_in_and_check_out_of_a_course_session(): void
    {
        $teacher = User::factory()->create(['role'=>'teacher']);
        $student = User::factory()->create(['role'=>'student']);
        $course = Course::factory()->create(['teacher_id'=>$teacher->id]);
        Enrollment::create(['user_id'=>$student->id,'course_id'=>$course->id,'status'=>'active']);
        $session = StudentCourseSession::create([
            'course_id'=>$course->id,'created_by'=>$teacher->id,'title'=>'Class 1',
            'starts_at'=>now()->subMinutes(5),'ends_at'=>now()->addHour(),'status'=>'scheduled',
        ]);

        $this->actingAs($student)->post(route('student.course-tracking.check-in',$session))->assertRedirect();
        $this->assertDatabaseHas('student_course_attendances',['session_id'=>$session->id,'student_id'=>$student->id]);
        $this->actingAs($student)->post(route('student.course-tracking.check-out',$session))->assertRedirect();
        $this->assertNotNull(\App\Models\StudentCourseAttendance::where('session_id',$session->id)->first()->checked_out_at);
    }

    public function test_non_enrolled_student_cannot_check_into_course_session(): void
    {
        $teacher = User::factory()->create(['role'=>'teacher']);
        $student = User::factory()->create(['role'=>'student']);
        $course = Course::factory()->create(['teacher_id'=>$teacher->id]);
        $session = StudentCourseSession::create([
            'course_id'=>$course->id,'created_by'=>$teacher->id,'title'=>'Class 1',
            'starts_at'=>now()->subMinutes(5),'ends_at'=>now()->addHour(),'status'=>'scheduled',
        ]);
        $this->actingAs($student)->post(route('student.course-tracking.check-in',$session))->assertForbidden();
    }
}
