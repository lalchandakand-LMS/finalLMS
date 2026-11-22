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
        View::addNamespace('admission-management', base_path('plugins/aec/admission-management/1.0.0/resources/views'));
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionApplication.php');
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionDocument.php');
        require_once base_path('plugins/aec/admission-management/1.0.0/src/AdmissionController.php');
        Route::post('/__admission/apply', [AdmissionController::class, 'applyPublic'])->name('admission.public.apply.store');
        Route::get('/__admission/track', [AdmissionController::class, 'trackForm'])->name('admission.public.track');
        Route::post('/__admission/track', [AdmissionController::class, 'track'])->name('admission.public.track.submit');
        Route::get('/__admission/admin', [AdmissionController::class, 'index']);
        Route::post('/__admission/{application}/review', [AdmissionController::class, 'review']);
        Route::post('/__admission/{application}/convert', [AdmissionController::class, 'convertToStudent']);
        Route::post('/__admission/{application}/documents', [AdmissionController::class, 'storeDocument']);
        Route::get('/__admission/{application}/documents/{document}', [AdmissionController::class, 'downloadDocument']);
        app('router')->getRoutes()->refreshNameLookups();
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
}
