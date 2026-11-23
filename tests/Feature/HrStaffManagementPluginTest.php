<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\HrStaffManagement\HrController;
use Aloms\HrStaffManagement\StaffAttendance;
use Aloms\HrStaffManagement\StaffLeaveRequest;
use Aloms\HrStaffManagement\StaffJobOpening;
use Aloms\HrStaffManagement\StaffApplication;
use Aloms\HrStaffManagement\StaffPayroll;
use Aloms\HrStaffManagement\StaffProfile;
use App\Models\Branch;
use App\Models\User;
use Tests\Support\RefreshDatabaseWithPluginMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class HrStaffManagementPluginTest extends TestCase
{
    use RefreshDatabaseWithPluginMigrations;

    protected function pluginMigrationPaths(): array
    {
        return [base_path('plugins/aec/hr-staff-management/1.0.0/migrations')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        View::addNamespace('hr-staff-management', base_path('plugins/aec/hr-staff-management/1.0.0/resources/views'));
        foreach (['StaffProfile.php', 'StaffPayroll.php', 'StaffAttendance.php', 'StaffLeaveRequest.php', 'StaffJobOpening.php', 'StaffApplication.php', 'HrController.php'] as $file) {
            require_once base_path('plugins/aec/hr-staff-management/1.0.0/src/'.$file);
        }
        Route::get('/__hr', [HrController::class, 'index'])->name('branch.hr.index');
        Route::get('/__careers', [HrController::class, 'publicCareers'])->name('careers.index');
        Route::get('/__careers/{opening}', [HrController::class, 'publicCareerShow'])->name('careers.show');
        Route::post('/__careers/{opening}/apply', [HrController::class, 'applyCareer'])->name('careers.apply');
        Route::get('/__admin/hr/recruitment', [HrController::class, 'recruitmentIndex'])->name('admin.hr.recruitment.index');
        Route::post('/__admin/hr/recruitment/openings', [HrController::class, 'storeJobOpening'])->name('admin.hr.recruitment.openings.store');
        Route::patch('/__admin/hr/recruitment/openings/{opening}', [HrController::class, 'updateJobOpeningStatus'])->name('admin.hr.recruitment.openings.status');
        Route::post('/__admin/hr/recruitment/applications/{application}', [HrController::class, 'reviewApplication'])->name('admin.hr.recruitment.applications.review');
        Route::get('/__admin/hr/recruitment/applications/{application}/resume', [HrController::class, 'downloadApplicationResume'])->name('admin.hr.recruitment.applications.resume');

        Route::get('/__hr/teacher', [HrController::class, 'teacherIndex'])->name('teacher.hr.index');
        Route::post('/__hr/teacher/leave', [HrController::class, 'teacherRequestLeave'])->name('teacher.hr.leave.store');
        Route::post('/__hr/staff', [HrController::class, 'storeStaff'])->name('branch.hr.staff.store');
        Route::put('/__hr/staff/{staff}', [HrController::class, 'updateStaff'])->name('branch.hr.staff.update');
        Route::post('/__hr/attendance', [HrController::class, 'recordAttendance'])->name('branch.hr.attendance.store');
        Route::post('/__hr/leave', [HrController::class, 'requestLeave'])->name('branch.hr.leave.store');
        Route::post('/__hr/leave/{leave}/review', [HrController::class, 'reviewLeave'])->name('admin.hr.leave.review');
        Route::post('/__hr/payroll', [HrController::class, 'recordPayroll'])->name('branch.hr.payrolls.store');
        Route::post('/__hr/payroll/{payroll}/paid', [HrController::class, 'markPayrollPaid'])->name('admin.hr.payroll.mark-paid');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_public_recruitment_application_is_stored_privately_and_admin_can_hire_candidate(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $opening = StaffJobOpening::query()->create([
            'title' => 'English Teacher',
            'department' => 'Academic',
            'designation' => 'English Teacher',
            'description' => 'Teach English classes.',
            'employment_type' => 'full_time',
            'status' => 'published',
            'created_by' => $admin->id,
        ]);

        $this->get('/__careers')->assertOk()->assertSee('English Teacher');
        $this->get('/__careers/'.$opening->id)->assertOk()->assertSee('Apply for this position');
        $this->post('/__careers/'.$opening->id.'/apply', [
            'applicant_name' => 'Candidate One',
            'email' => 'candidate@example.test',
            'phone' => '01700000000',
            'cover_letter' => 'I have teaching experience.',
            'resume' => UploadedFile::fake()->create('candidate.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $application = StaffApplication::query()->firstOrFail();
        $this->assertSame('submitted', $application->status);
        Storage::disk('local')->assertExists($application->resume_path);
        $this->actingAs($admin)->get('/__admin/hr/recruitment/applications/'.$application->id.'/resume')->assertOk();

        $branch = Branch::query()->create(['name' => 'Recruitment Branch', 'slug' => 'recruitment-branch', 'is_active' => true]);
        $this->post('/__admin/hr/recruitment/applications/'.$application->id, [
            'status' => 'hired',
            'review_notes' => 'Interview completed and approved.',
            'employee_code' => 'HIRED-001',
            'base_salary' => 32000,
            'branch_id' => $branch->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_profiles', [
            'employee_code' => 'HIRED-001',
            'full_name' => 'Candidate One',
            'email' => 'candidate@example.test',
            'branch_id' => $branch->id,
            'base_salary' => 32000,
        ]);
        $this->assertDatabaseHas('staff_applications', ['id' => $application->id, 'status' => 'hired']);
        $this->post('/__admin/hr/recruitment/applications/'.$application->id, [
            'status' => 'hired', 'employee_code' => 'HIRED-002', 'base_salary' => 30000,
        ])->assertStatus(409);
    }

    public function test_recruitment_review_and_resume_access_are_admin_only(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $opening = StaffJobOpening::query()->create([
            'title' => 'Tutor', 'department' => 'Academic', 'designation' => 'Tutor',
            'description' => 'Tutor role.', 'employment_type' => 'part_time', 'status' => 'published',
        ]);
        $path = UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf')->store('private/hr-applications', 'local');
        $application = StaffApplication::query()->create([
            'staff_job_opening_id' => $opening->id, 'applicant_name' => 'Applicant',
            'email' => 'applicant@example.test', 'phone' => '01700000001', 'resume_path' => $path,
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher)->post('/__admin/hr/recruitment/applications/'.$application->id, ['status' => 'rejected'])->assertForbidden();
        $this->get('/__admin/hr/recruitment/applications/'.$application->id.'/resume')->assertForbidden();
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_branch_manager_can_only_see_and_prepare_payroll_for_their_branch(): void
    {
        $local = Branch::query()->create(['name' => 'Local', 'slug' => 'hr-local', 'is_active' => true]);
        $remote = Branch::query()->create(['name' => 'Remote', 'slug' => 'hr-remote', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $local->id]);
        $localStaff = StaffProfile::query()->create(['branch_id' => $local->id, 'employee_code' => 'EMP-LOCAL', 'full_name' => 'Local Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 30000, 'status' => 'active']);
        $remoteStaff = StaffProfile::query()->create(['branch_id' => $remote->id, 'employee_code' => 'EMP-REMOTE', 'full_name' => 'Remote Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 40000, 'status' => 'active']);

        $this->actingAs($manager)->get('/__hr')->assertOk()->assertSee('Local Staff')->assertDontSee('Remote Staff');
        $this->actingAs($manager)->post('/__hr/payroll', ['staff_profile_id' => $remoteStaff->id, 'payroll_month' => '2026-11'])->assertNotFound();
        $this->actingAs($manager)->post('/__hr/payroll', ['staff_profile_id' => $localStaff->id, 'payroll_month' => '2026-11', 'bonus' => 1000, 'deductions' => 500])->assertRedirect();
        $this->assertDatabaseHas('staff_payrolls', ['staff_profile_id' => $localStaff->id, 'net_paid' => 30500, 'status' => 'pending']);

        $this->put('/__hr/staff/'.$localStaff->id, [
            'employee_code' => 'EMP-LOCAL',
            'full_name' => 'Local Staff Updated',
            'designation' => 'Senior Teacher',
            'department' => 'Academic',
            'base_salary' => 36000,
            'compensation_basis' => 'monthly_attendance',
            'per_class_rate' => 0,
            'working_days_per_month' => 22,
            'status' => 'active',
            'employment_type' => 'full_time',
        ])->assertRedirect();

        $this->assertDatabaseHas('staff_profiles', [
            'id' => $localStaff->id,
            'full_name' => 'Local Staff Updated',
            'compensation_basis' => 'monthly_attendance',
            'base_salary' => 36000,
        ]);
        // A later salary-profile change must not rewrite an already prepared payroll.
        $this->assertDatabaseHas('staff_payrolls', ['staff_profile_id' => $localStaff->id, 'net_paid' => 30500, 'status' => 'pending']);
    }

    public function test_staff_linking_rejects_admin_accounts_and_cross_branch_teacher_accounts(): void
    {
        $branch = Branch::query()->create(['name' => 'Link Branch', 'slug' => 'link-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $remoteTeacher = User::factory()->create(['role' => 'teacher', 'branch_id' => Branch::query()->create(['name' => 'Other', 'slug' => 'link-other', 'is_active' => true])->id]);
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = ['employee_code' => 'LINK-FAIL-1', 'full_name' => 'Wrong Link', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 20000, 'status' => 'active', 'employment_type' => 'full_time', 'user_id' => $admin->id];
        $this->actingAs($manager)->post('/__hr/staff', $payload)->assertStatus(422);
        $payload['employee_code'] = 'LINK-FAIL-2';
        $payload['user_id'] = $remoteTeacher->id;
        $this->post('/__hr/staff', $payload)->assertStatus(422);
        $this->assertDatabaseCount('staff_profiles', 0);
    }

    public function test_payroll_month_is_unique_and_only_admin_can_confirm_disbursement_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create(['employee_code' => 'EMP-UNIQUE', 'full_name' => 'Payroll Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 25000, 'status' => 'active']);
        $this->actingAs($admin)->post('/__hr/payroll', ['staff_profile_id' => $staff->id, 'payroll_month' => '2026-11'])->assertRedirect();
        $payroll = StaffPayroll::query()->firstOrFail();
        $this->actingAs($admin)->post('/__hr/payroll', ['staff_profile_id' => $staff->id, 'payroll_month' => '2026-11'])->assertStatus(409);
        $this->actingAs($admin)->post('/__hr/payroll/'.$payroll->id.'/paid', ['payment_reference' => 'BANK-REF-001'])->assertRedirect();
        $this->assertDatabaseHas('staff_payrolls', ['id' => $payroll->id, 'status' => 'paid', 'payment_reference' => 'BANK-REF-001']);
        $this->actingAs($admin)->post('/__hr/payroll/'.$payroll->id.'/paid', ['payment_reference' => 'BANK-REF-002'])->assertStatus(409);
    }

    public function test_payroll_calculates_allowances_overtime_bonus_and_deductions_and_preserves_breakdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create(['employee_code' => 'PAY-COMP-01', 'full_name' => 'Payroll Components', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 30000, 'status' => 'active']);

        $this->actingAs($admin)->post('/__hr/payroll', [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->format('Y-m'),
            'allowances' => 2500,
            'overtime_hours' => 5,
            'overtime_rate' => 200,
            'bonus' => 1000,
            'deductions' => 1500,
        ])->assertRedirect();

        $payroll = StaffPayroll::query()->firstOrFail();
        $this->assertSame('30000.00', $payroll->basic_salary);
        $this->assertSame('2500.00', $payroll->allowances);
        $this->assertSame('1000.00', $payroll->overtime_amount);
        $this->assertSame('33500.00', $payroll->gross_salary);
        $this->assertSame('1000.00', $payroll->bonus);
        $this->assertSame('1500.00', $payroll->deductions);
        $this->assertSame('33000.00', $payroll->net_paid);
    }


    public function test_attendance_based_monthly_payroll_uses_absence_half_day_and_approved_unpaid_leave_snapshots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create([
            'employee_code' => 'ATT-PAY-001',
            'full_name' => 'Attendance Payroll Staff',
            'designation' => 'Teacher',
            'department' => 'Academic',
            'base_salary' => 26000,
            'compensation_basis' => 'monthly_attendance',
            'working_days_per_month' => 26,
            'status' => 'active',
        ]);
        $monthStart = now()->subMonth()->startOfMonth();
        foreach ([
            [$monthStart->copy()->addDays(1), 'present'],
            [$monthStart->copy()->addDays(2), 'present'],
            [$monthStart->copy()->addDays(3), 'absent'],
            [$monthStart->copy()->addDays(4), 'half_day'],
            [$monthStart->copy()->addDays(5), 'on_leave'],
        ] as [$date, $status]) {
            StaffAttendance::query()->create([
                'staff_profile_id' => $staff->id,
                'attendance_date' => $date->toDateString(),
                'status' => $status,
                'recorded_by' => $admin->id,
            ]);
        }
        for ($day = 7; $day <= 27; $day++) {
            StaffAttendance::query()->create([
                'staff_profile_id' => $staff->id,
                'attendance_date' => $monthStart->copy()->addDays($day - 1)->toDateString(),
                'status' => 'present',
                'recorded_by' => $admin->id,
            ]);
        }
        StaffLeaveRequest::query()->create([
            'staff_profile_id' => $staff->id,
            'leave_type' => 'unpaid',
            'starts_on' => $monthStart->copy()->addDays(5)->toDateString(),
            'ends_on' => $monthStart->copy()->addDays(5)->toDateString(),
            'reason' => 'Approved unpaid leave',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post('/__hr/payroll', [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->subMonth()->format('Y-m'),
        ])->assertRedirect();

        $payroll = StaffPayroll::query()->firstOrFail();
        $this->assertSame('monthly_attendance', $payroll->compensation_basis_snapshot);
        $this->assertSame('23.00', $payroll->present_days);
        $this->assertSame('1.00', $payroll->absent_days);
        $this->assertSame('1.00', $payroll->half_days);
        $this->assertSame('1.00', $payroll->unpaid_leave_days);
        $this->assertSame('2500.00', $payroll->attendance_deduction);
        $this->assertSame('23500.00', $payroll->net_paid);
        $this->assertSame(26, $payroll->attendance_records_count);
        $this->assertSame(26, $payroll->expected_working_days);
        $this->assertSame('pending', $payroll->status);
    }



    public function test_attendance_payroll_refuses_to_finalize_when_working_day_records_are_incomplete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create([
            'employee_code' => 'ATT-INCOMPLETE-01',
            'full_name' => 'Incomplete Attendance Staff',
            'designation' => 'Teacher',
            'department' => 'Academic',
            'base_salary' => 26000,
            'compensation_basis' => 'monthly_attendance',
            'working_days_per_month' => 26,
            'status' => 'active',
        ]);
        StaffAttendance::query()->create([
            'staff_profile_id' => $staff->id,
            'attendance_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'status' => 'present',
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/__hr/payroll', [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->subMonth()->format('Y-m'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('staff_payrolls', 0);
    }

    public function test_per_class_teacher_payroll_counts_only_completed_classes_assigned_to_the_linked_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::query()->create(['name' => 'Per Class Payroll', 'slug' => 'per-class-payroll']);
        $monthStart = now()->subMonth()->startOfMonth();
        $course = \App\Models\Course::query()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Per Class Payroll Course',
            'slug' => 'per-class-payroll-course',
            'description' => 'Teacher compensation test.',
            'price' => 0,
            'status' => 'published',
        ]);
        $batch = \App\Models\Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Teacher Payroll Batch',
            'capacity' => 20,
            'starts_at' => $monthStart,
            'ends_at' => $monthStart->copy()->endOfMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        $otherBatch = \App\Models\Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other Teacher Batch',
            'capacity' => 20,
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        $staff = StaffProfile::query()->create([
            'user_id' => $teacher->id,
            'branch_id' => $branch->id,
            'employee_code' => 'PER-CLASS-TEACHER',
            'full_name' => 'Per Class Teacher',
            'designation' => 'Teacher',
            'department' => 'Academic',
            'base_salary' => 0,
            'compensation_basis' => 'per_class',
            'per_class_rate' => 1200,
            'status' => 'active',
        ]);
        foreach ([[$batch, 'completed'], [$batch, 'completed'], [$batch, 'scheduled'], [$otherBatch, 'completed']] as $index => [$classBatch, $status]) {
            \App\Models\LiveClass::query()->create([
                'batch_id' => $classBatch->id,
                'title' => 'Payroll Class '.$index,
                'meeting_url' => 'https://example.test/meeting/'.$index,
                'starts_at' => $monthStart->copy()->addDays($index + 1)->setTime(10, 0),
                'ends_at' => $monthStart->copy()->addDays($index + 1)->setTime(11, 0),
                'status' => $status,
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)->post('/__hr/payroll', [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->subMonth()->format('Y-m'),
        ])->assertRedirect();

        $payroll = StaffPayroll::query()->firstOrFail();
        $this->assertSame('per_class', $payroll->compensation_basis_snapshot);
        $this->assertSame(2, $payroll->classes_delivered);
        $this->assertSame('2400.00', $payroll->basic_salary);
        $this->assertSame('2400.00', $payroll->net_paid);
    }

    public function test_branch_manager_cannot_mark_payroll_paid(): void
    {
        $branch = Branch::query()->create(['name' => 'Branch', 'slug' => 'hr-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $staff = StaffProfile::query()->create(['branch_id' => $branch->id, 'employee_code' => 'EMP-BRANCH', 'full_name' => 'Branch Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 25000, 'status' => 'active']);
        $payroll = StaffPayroll::query()->create(['staff_profile_id' => $staff->id, 'branch_id' => $branch->id, 'payroll_month' => '2026-11', 'gross_salary' => 25000, 'bonus' => 0, 'deductions' => 0, 'net_paid' => 25000, 'status' => 'pending']);
        $this->actingAs($manager)->post('/__hr/payroll/'.$payroll->id.'/paid', ['payment_reference' => 'BANK-REF'])->assertForbidden();
        $this->assertDatabaseHas('staff_payrolls', ['id' => $payroll->id, 'status' => 'pending']);
    }

    public function test_teacher_can_only_view_their_linked_hr_profile_and_apply_for_their_own_leave(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $profile = StaffProfile::query()->create([
            'user_id' => $teacher->id,
            'employee_code' => 'TEACHER-HR-01',
            'full_name' => 'Linked Teacher',
            'designation' => 'English Teacher',
            'department' => 'Academic',
            'base_salary' => 30000,
            'status' => 'active',
        ]);

        $this->actingAs($teacher)->get('/__hr/teacher')->assertOk()->assertSee('Linked Teacher');
        $payload = [
            'leave_type' => 'casual',
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'reason' => 'Personal matter',
        ];
        $this->post('/__hr/teacher/leave', $payload)->assertRedirect();
        $this->assertDatabaseHas('staff_leave_requests', [
            'staff_profile_id' => $profile->id,
            'status' => 'pending',
            'reason' => 'Personal matter',
        ]);

        $this->actingAs($otherTeacher)->get('/__hr/teacher')->assertNotFound();
        $this->post('/__hr/teacher/leave', $payload)->assertNotFound();
        $this->assertDatabaseCount('staff_leave_requests', 1);
    }

    public function test_branch_manager_attendance_is_branch_scoped_and_duplicate_dates_update_one_record(): void
    {
        $local = Branch::query()->create(['name' => 'Attendance Local', 'slug' => 'attendance-local', 'is_active' => true]);
        $remote = Branch::query()->create(['name' => 'Attendance Remote', 'slug' => 'attendance-remote', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $local->id]);
        $localStaff = StaffProfile::query()->create(['branch_id' => $local->id, 'employee_code' => 'ATT-LOCAL', 'full_name' => 'Attendance Local Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 20000, 'status' => 'active']);
        $remoteStaff = StaffProfile::query()->create(['branch_id' => $remote->id, 'employee_code' => 'ATT-REMOTE', 'full_name' => 'Attendance Remote Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 20000, 'status' => 'active']);
        $payload = ['staff_profile_id' => $localStaff->id, 'attendance_date' => now()->toDateString(), 'status' => 'late', 'check_in_at' => '09:15'];
        $this->actingAs($manager)->post('/__hr/attendance', $payload)->assertRedirect();
        $this->post('/__hr/attendance', [...$payload, 'status' => 'present', 'check_in_at' => '09:00'])->assertRedirect();
        $this->assertDatabaseCount('staff_attendances', 1);
        $this->assertDatabaseHas('staff_attendances', ['staff_profile_id' => $localStaff->id, 'status' => 'present']);
        $this->post('/__hr/attendance', [...$payload, 'staff_profile_id' => $remoteStaff->id])->assertNotFound();
    }

    public function test_leave_requests_are_branch_scoped_and_only_admin_can_review_once(): void
    {
        $branch = Branch::query()->create(['name' => 'Leave Branch', 'slug' => 'leave-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create(['branch_id' => $branch->id, 'employee_code' => 'LEAVE-01', 'full_name' => 'Leave Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 20000, 'status' => 'active']);
        $payload = ['staff_profile_id' => $staff->id, 'leave_type' => 'sick', 'starts_on' => now()->addDay()->toDateString(), 'ends_on' => now()->addDays(2)->toDateString(), 'reason' => 'Medical rest'];
        $this->actingAs($manager)->post('/__hr/leave', $payload)->assertRedirect();
        $this->post('/__hr/leave', [...$payload, 'starts_on' => now()->addDays(2)->toDateString()])->assertStatus(409);
        $leave = StaffLeaveRequest::query()->firstOrFail();
        $this->assertDatabaseCount('staff_leave_requests', 1);
        $this->actingAs($manager)->post('/__hr/leave/'.$leave->id.'/review', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($admin)->post('/__hr/leave/'.$leave->id.'/review', ['decision' => 'approved', 'review_note' => 'Approved'])->assertRedirect();
        $this->assertDatabaseHas('staff_leave_requests', ['id' => $leave->id, 'status' => 'approved', 'reviewed_by' => $admin->id]);
        $this->post('/__hr/leave/'.$leave->id.'/review', ['decision' => 'rejected'])->assertStatus(409);
    }
}
