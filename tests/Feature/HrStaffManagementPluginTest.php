<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\HrStaffManagement\HrController;
use Aloms\HrStaffManagement\StaffAttendance;
use Aloms\HrStaffManagement\StaffLeaveRequest;
use Aloms\HrStaffManagement\StaffPayroll;
use Aloms\HrStaffManagement\StaffProfile;
use App\Models\Branch;
use App\Models\User;
use Tests\Support\RefreshDatabaseWithPluginMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
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
        foreach (['StaffProfile.php', 'StaffPayroll.php', 'StaffAttendance.php', 'StaffLeaveRequest.php', 'HrController.php'] as $file) {
            require_once base_path('plugins/aec/hr-staff-management/1.0.0/src/'.$file);
        }
        Route::get('/__hr', [HrController::class, 'index'])->name('branch.hr.index');
        Route::post('/__hr/staff', [HrController::class, 'storeStaff'])->name('branch.hr.staff.store');
        Route::post('/__hr/attendance', [HrController::class, 'recordAttendance'])->name('branch.hr.attendance.store');
        Route::post('/__hr/leave', [HrController::class, 'requestLeave'])->name('branch.hr.leave.store');
        Route::post('/__hr/leave/{leave}/review', [HrController::class, 'reviewLeave'])->name('admin.hr.leave.review');
        Route::post('/__hr/payroll', [HrController::class, 'recordPayroll'])->name('branch.hr.payrolls.store');
        Route::post('/__hr/payroll/{payroll}/paid', [HrController::class, 'markPayrollPaid'])->name('admin.hr.payroll.mark-paid');
        app('router')->getRoutes()->refreshNameLookups();
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

    public function test_branch_manager_cannot_mark_payroll_paid(): void
    {
        $branch = Branch::query()->create(['name' => 'Branch', 'slug' => 'hr-branch', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        $staff = StaffProfile::query()->create(['branch_id' => $branch->id, 'employee_code' => 'EMP-BRANCH', 'full_name' => 'Branch Staff', 'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 25000, 'status' => 'active']);
        $payroll = StaffPayroll::query()->create(['staff_profile_id' => $staff->id, 'branch_id' => $branch->id, 'payroll_month' => '2026-11', 'gross_salary' => 25000, 'bonus' => 0, 'deductions' => 0, 'net_paid' => 25000, 'status' => 'pending']);
        $this->actingAs($manager)->post('/__hr/payroll/'.$payroll->id.'/paid', ['payment_reference' => 'BANK-REF'])->assertForbidden();
        $this->assertDatabaseHas('staff_payrolls', ['id' => $payroll->id, 'status' => 'pending']);
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
