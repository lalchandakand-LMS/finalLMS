<?php

namespace Tests\Feature;

use App\Models\TeacherApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherApplicationTest extends TestCase
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

    public function test_teacher_application_requires_documents_and_verified_mobile_before_admin_review(): void
    {
        $code = null;
        Http::fake(function ($request) use (&$code) {
            preg_match('/code: ([0-9]{6})/', $request['Body'], $matches);
            $code = $matches[1] ?? null;
            return Http::response(['sid' => 'SM-test'], 201);
        });

        $this->post(route('teacher.apply.store'), [
            'name' => 'Test Teacher',
            'email' => 'teacher@example.com',
            'phone_number' => '+8801712345678',
            'institution_name' => 'Example College',
            'designation' => 'English Lecturer',
            'nid_number' => '1234567890',
            'nid_document' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
            'institution_document' => UploadedFile::fake()->create('employment.pdf', 100, 'application/pdf'),
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'consent' => '1',
        ])->assertRedirect(route('teacher.apply.verify'));

        $application = TeacherApplication::where('email', 'teacher@example.com')->firstOrFail();
        $this->assertSame('pending_phone_verification', $application->status);
        $this->assertNotNull($code);
        $this->assertDatabaseMissing('users', ['email' => 'teacher@example.com']);

        $this->post(route('teacher.apply.verify.post'), ['code' => $code])
            ->assertRedirect(route('login'));

        $this->assertSame('pending_admin_review', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->phone_verified_at);
        $this->assertDatabaseMissing('users', ['email' => 'teacher@example.com']);
    }

    public function test_only_admin_can_download_private_teacher_kyc_documents_and_approve_application(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $application = TeacherApplication::create([
            'name' => 'Approved Teacher',
            'email' => 'approved.teacher@example.com',
            'phone_number' => '+8801712345679',
            'institution_name' => 'Sample School',
            'designation' => 'Teacher',
            'nid_encrypted' => encrypt('1234567890'),
            'password' => bcrypt('StrongPassword123!'),
            'nid_document_path' => 'teacher-kyc/nid/test.pdf',
            'institution_document_path' => 'teacher-kyc/institution/test.pdf',
            'status' => 'pending_admin_review',
            'phone_verified_at' => now(),
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('teacher-kyc/nid/test.pdf', 'private test');
        \Illuminate\Support\Facades\Storage::disk('local')->put('teacher-kyc/institution/test.pdf', 'private test');

        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student)
            ->get(route('admin.teacher-applications.document', [$application, 'nid']))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.teacher-applications.document', [$application, 'nid']))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.teacher-applications.approve', $application))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'approved.teacher@example.com',
            'role' => 'teacher',
        ]);
        $this->assertSame('approved', $application->fresh()->status);
    }
}