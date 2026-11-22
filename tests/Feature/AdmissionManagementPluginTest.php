<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\AdmissionManagement\AdmissionApplication;
use Aloms\AdmissionManagement\AdmissionController;
use App\Models\Branch;
use App\Models\PluginInstallation;
use App\Models\User;
use Tests\Support\RefreshDatabaseWithPluginMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class AdmissionManagementPluginTest extends TestCase
{
    use RefreshDatabaseWithPluginMigrations;

    protected function pluginMigrationPaths(): array
    {
        return [base_path('plugins/aec/admission-management/1.0.0/migrations')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.admission-management',
            'name' => 'Admission Management',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => ['settings_schema' => []],
        ])->save();
        View::addNamespace('admission-management', base_path('plugins/aec/admission-management/1.0.0/resources/views'));
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionApplication.php');
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionDocument.php');
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionApplicationEvent.php');
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionController.php');
        Route::post('/__admission/apply', [AdmissionController::class, 'applyPublic'])->name('admission.public.apply.store');
        Route::get('/__admission/track', [AdmissionController::class, 'trackForm'])->name('admission.public.track');
        Route::post('/__admission/track', [AdmissionController::class, 'track'])->name('admission.public.track.submit');
        Route::get('/__admission/admin', [AdmissionController::class, 'index']);
        Route::post('/__admission/{application}/review', [AdmissionController::class, 'review']);
        Route::post('/__admission/{application}/convert', [AdmissionController::class, 'convertToStudent']);
        Route::post('/__admission/{application}/documents', [AdmissionController::class, 'storeDocument']);
        Route::post('/__admission/{application}/documents/{document}/review', [AdmissionController::class, 'reviewDocument']);
        Route::get('/__admission/reports', [AdmissionController::class, 'reports'])->name('admin.admissions.reports');
        Route::get('/__admission/reports/export', [AdmissionController::class, 'exportReport'])->name('admin.admissions.reports.export');
        Route::get('/__admission/admin-index', [AdmissionController::class, 'index'])->name('admin.admissions.index');
        Route::get('/__admission/{application}/documents/{document}', [AdmissionController::class, 'downloadDocument']);
        app('router')->getRoutes()->refreshNameLookups();
    }



    public function test_inactive_branches_cannot_receive_admissions_or_be_used_by_branch_managers(): void
    {
        $inactive = Branch::query()->create(['name' => 'Inactive Branch', 'slug' => 'inactive-branch', 'is_active' => false]);

        $this->post('/__admission/apply', [
            'applicant_name' => 'Inactive Branch Applicant',
            'email' => 'inactive-branch@example.com',
            'phone' => '01700000008',
            'desired_course' => 'Spoken English',
            'branch_id' => $inactive->id,
        ])->assertSessionHasErrors('branch_id');

        $this->assertDatabaseCount('admission_applications', 0);

        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $inactive->id]);
        $this->actingAs($manager)->get('/__admission/admin')->assertForbidden();
    }

    public function test_public_intake_can_be_closed_from_plugin_settings(): void
    {
        $plugin = \App\Models\PluginInstallation::query()->where('plugin_key', 'aec.admission-management')->firstOrFail();
        app(\App\Services\Plugins\PluginSettingsManager::class)->set($plugin, 'intake_status', 'closed');

        $this->post('/__admission/apply', [
            'applicant_name' => 'Closed Intake Applicant',
            'email' => 'closed@example.com',
            'phone' => '01700000007',
            'desired_course' => 'Spoken English',
        ])->assertForbidden();

        $this->assertDatabaseCount('admission_applications', 0);
    }

    public function test_public_intake_and_tracking_require_both_application_code_and_email(): void
    {
        $this->post('/__admission/apply', [
            'applicant_name' => 'Applicant One',
            'email' => 'APPLICANT@example.com',
            'phone' => '01700000000',
            'desired_course' => 'Spoken English',
        ])->assertRedirect();

        $application = AdmissionApplication::query()->firstOrFail();
        $this->assertSame('applicant@example.com', $application->email);
        $this->assertSame('pending', $application->status);
        $this->assertStringStartsWith('ADM-', $application->application_code);

        $this->post('/__admission/track', [
            'application_code' => $application->application_code,
            'email' => 'wrong@example.com',
        ])->assertOk()->assertSee('No application matched those details.');

        $application->update(['reviewer_notes' => 'Confidential reviewer note']);
        $this->post('/__admission/track', [
            'application_code' => strtolower($application->application_code),
            'email' => 'APPLICANT@example.com',
        ])->assertOk()->assertSee('Applicant One')->assertSee('Pending')->assertDontSee('Confidential reviewer note');
    }

    public function test_branch_manager_cannot_list_or_review_another_branch_application(): void
    {
        $branchA = Branch::query()->create(['name' => 'Branch A', 'slug' => 'branch-a', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Branch B', 'slug' => 'branch-b', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id]);
        $application = AdmissionApplication::query()->create([
            'branch_id' => $branchB->id,
            'application_code' => 'ADM-BRANCH0001',
            'applicant_name' => 'Private Applicant',
            'email' => 'private@example.com',
            'phone' => '01700000001',
            'desired_course' => 'English',
            'application_source' => 'walk_in',
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->get('/__admission/admin')->assertOk()->assertDontSee('Private Applicant');
        $this->actingAs($manager)->post('/__admission/'.$application->id.'/review', [
            'status' => 'screening',
        ])->assertNotFound();

        $this->assertDatabaseHas('admission_applications', ['id' => $application->id, 'status' => 'pending']);
    }

    public function test_review_requires_valid_transition_and_rejection_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-REVIEW0001',
            'applicant_name' => 'Review Applicant',
            'email' => 'review@example.com',
            'phone' => '01700000002',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', [
            'status' => 'rejected',
        ])->assertSessionHasErrors('reviewer_notes');

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertDatabaseHas('admission_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', [
            'status' => 'screening',
        ])->assertStatus(422);
    }

    public function test_review_can_save_notes_without_changing_the_current_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-NOOP000001',
            'applicant_name' => 'No-op Review Applicant',
            'email' => 'noop@example.com',
            'phone' => '01700000003',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', [
            'status' => 'pending',
            'reviewer_notes' => 'Documents are awaiting verification.',
        ])->assertRedirect();

        $this->assertDatabaseHas('admission_applications', [
            'id' => $application->id,
            'status' => 'pending',
            'reviewer_notes' => 'Documents are awaiting verification.',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_document_upload_is_private_and_download_is_scoped_to_application(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-DOCS000001',
            'applicant_name' => 'Document Applicant',
            'email' => 'docs@example.com',
            'phone' => '01700000003',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/documents', [
            'document' => \Illuminate\Http\UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $document = $application->documents()->firstOrFail();
        $this->assertSame('local', $document->disk);
        $this->assertStringStartsWith('admission-documents/'.$application->id.'/', $document->path);
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($admin)->get('/__admission/'.$application->id.'/documents/'.$document->id)->assertOk();
        $other = AdmissionApplication::query()->create([
            'application_code' => 'ADM-DOCS000002',
            'applicant_name' => 'Other Applicant',
            'email' => 'otherdocs@example.com',
            'phone' => '01700000004',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
        ]);
        $this->actingAs($admin)->get('/__admission/'.$other->id.'/documents/'.$document->id)->assertNotFound();
    }


    public function test_conversion_creates_configured_admission_fee_invoice_atomically(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-FEE000001',
            'applicant_name' => 'Fee Applicant',
            'email' => 'feeapplicant@example.com',
            'phone' => '01700000006',
            'desired_course' => 'Spoken English',
            'application_source' => 'website',
            'status' => 'approved',
        ]);
        $plugin = \App\Models\PluginInstallation::query()->where('plugin_key', 'aec.admission-management')->firstOrFail();
        app(\App\Services\Plugins\PluginSettingsManager::class)->set($plugin, 'admission_fee_bdt', '1500');

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/convert')->assertRedirect();

        $application->refresh();
        $this->assertNotNull($application->converted_student_id);
        $this->assertNotNull($application->admission_fee_invoice_id);
        $this->assertDatabaseHas('invoices', [
            'id' => $application->admission_fee_invoice_id,
            'user_id' => $application->converted_student_id,
            'amount' => 1500,
            'status' => 'unpaid',
        ]);
    }

    public function test_only_approved_applicants_convert_once_and_existing_emails_are_not_attached(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-CONVERT001',
            'applicant_name' => 'New Student',
            'email' => 'newstudent@example.com',
            'phone' => '01700000005',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/convert')->assertStatus(422);
        $application->update(['status' => 'approved']);
        $this->actingAs($admin)->post('/__admission/'.$application->id.'/convert')->assertRedirect();
        $application->refresh();
        $studentId = $application->converted_student_id;
        $this->assertNotNull($studentId);
        $this->assertDatabaseHas('users', ['id' => $studentId, 'role' => 'student', 'email' => 'newstudent@example.com']);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/convert')->assertRedirect();
        $this->assertSame(1, User::query()->where('email', 'newstudent@example.com')->count());
    }


    public function test_online_application_accepts_private_typed_documents_and_records_submission_audit(): void
    {
        Storage::fake('local');
        $branch = Branch::query()->create(['name' => 'Online Branch', 'slug' => 'online-branch', 'is_active' => true]);

        $this->get('/admission/apply')->assertOk()->assertSee('Online Admission Application')->assertSee('Online Branch');

        $this->post('/__admission/apply', [
            'applicant_name' => 'Online Applicant',
            'email' => 'online@example.com',
            'phone' => '01700000010',
            'desired_course' => 'Spoken English',
            'branch_id' => $branch->id,
            'documents' => [
                'photo' => \Illuminate\Http\UploadedFile::fake()->image('applicant.jpg'),
                'nid' => \Illuminate\Http\UploadedFile::fake()->create('nid.pdf', 120, 'application/pdf'),
            ],
        ])->assertRedirect();

        $application = AdmissionApplication::query()->where('email', 'online@example.com')->firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertSame('pending', $application->document_verification_status);
        $this->assertDatabaseCount('admission_documents', 2);
        foreach ($application->documents as $document) {
            $this->assertSame('local', $document->disk);
            $this->assertSame('pending', $document->review_status);
            Storage::disk('local')->assertExists($document->path);
        }
        $this->assertDatabaseHas('admission_application_events', [
            'admission_application_id' => $application->id,
            'event_key' => 'application_submitted',
            'to_status' => 'pending',
        ]);
    }

    public function test_document_review_requires_rejection_reason_and_all_documents_verified_before_approval(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $application = AdmissionApplication::query()->create([
            'application_code' => 'ADM-DOCREVIEW01',
            'applicant_name' => 'Document Review Applicant',
            'email' => 'docreview@example.com',
            'phone' => '01700000011',
            'desired_course' => 'English',
            'application_source' => 'website',
            'status' => 'pending',
            'document_verification_status' => 'pending',
        ]);
        $document = $application->documents()->create([
            'disk' => 'local',
            'path' => 'admission-documents/'.$application->id.'/sample.pdf',
            'original_name' => 'sample.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'document_type' => 'previous_certificate',
            'review_status' => 'pending',
        ]);
        Storage::disk('local')->put($document->path, 'private pdf');

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', ['status' => 'approved'])->assertStatus(422);
        $this->actingAs($admin)->post('/__admission/'.$application->id.'/documents/'.$document->id.'/review', [
            'review_status' => 'rejected',
        ])->assertSessionHasErrors('review_notes');
        $this->actingAs($admin)->post('/__admission/'.$application->id.'/documents/'.$document->id.'/review', [
            'review_status' => 'rejected',
            'review_notes' => 'Please upload a clearer certificate.',
        ])->assertRedirect();

        $application->refresh();
        $document->refresh();
        $this->assertSame('revision_required', $application->document_verification_status);
        $this->assertSame('rejected', $document->review_status);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/documents/'.$document->id.'/review', [
            'review_status' => 'verified',
            'review_notes' => 'Replacement document checked.',
        ])->assertRedirect();
        $application->refresh();
        $this->assertSame('verified', $application->document_verification_status);

        $this->actingAs($admin)->post('/__admission/'.$application->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('admission_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertDatabaseHas('admission_application_events', [
            'admission_application_id' => $application->id,
            'event_key' => 'document_reviewed',
        ]);
        $this->assertGreaterThanOrEqual(3, $application->events()->count());
    }

    public function test_admission_reports_and_csv_export_obey_branch_scope_and_filters(): void
    {
        $branchA = Branch::query()->create(['name' => 'Report Branch A', 'slug' => 'report-branch-a', 'is_active' => true]);
        $branchB = Branch::query()->create(['name' => 'Report Branch B', 'slug' => 'report-branch-b', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branchA->id]);

        $inside = AdmissionApplication::query()->create([
            'branch_id' => $branchA->id,
            'application_code' => 'ADM-REPORTA0001',
            'applicant_name' => 'Branch A Applicant',
            'email' => 'report-a@example.com',
            'phone' => '01700000012',
            'desired_course' => 'Spoken English',
            'application_source' => 'website',
            'status' => 'approved',
            'document_verification_status' => 'verified',
        ]);
        AdmissionApplication::query()->create([
            'branch_id' => $branchA->id,
            'application_code' => 'ADM-REPORTA0002',
            'applicant_name' => 'Branch A Walkin',
            'email' => 'report-a2@example.com',
            'phone' => '01700000013',
            'desired_course' => 'Grammar',
            'application_source' => 'walk_in',
            'status' => 'pending',
            'document_verification_status' => 'missing',
        ]);
        AdmissionApplication::query()->create([
            'branch_id' => $branchB->id,
            'application_code' => 'ADM-REPORTB0001',
            'applicant_name' => 'Branch B Private Applicant',
            'email' => 'report-b@example.com',
            'phone' => '01700000014',
            'desired_course' => 'Spoken English',
            'application_source' => 'website',
            'status' => 'approved',
            'document_verification_status' => 'verified',
        ]);

        $this->actingAs($manager)->get('/__admission/reports')->assertOk()
            ->assertSee('Admission Reports &amp; Analytics')
            ->assertSee('Branch A Applicant')
            ->assertDontSee('Branch B Private Applicant');
        $this->actingAs($manager)->get('/__admission/reports/export?status=approved')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($manager)->get('/__admission/reports/export?status=approved')
            ->assertStreamedContent(fn ($content) => str_contains($content, 'Branch A Applicant') && ! str_contains($content, 'Branch B Private Applicant'));
    }

}
