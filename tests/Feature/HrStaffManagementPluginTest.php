<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\HrStaffManagement\HrController;
use Aloms\HrStaffManagement\StaffPayroll;
use Aloms\HrStaffManagement\StaffProfile;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class HrStaffManagementPluginTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('staff_payrolls');
        Schema::dropIfExists('staff_profiles');
        Schema::enableForeignKeyConstraints();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        View::addNamespace('hr-staff-management', base_path('plugins/aec/hr-staff-management/1.0.0/resources/views'));
        require_once base_path('plugins/aec/hr-staff-management/1.0.0/src/StaffProfile.php');
        require_once base_path('plugins/aec/hr-staff-management/1.0.0/src/StaffPayroll.php');
        require_once base_path('plugins/aec/hr-staff-management/1.0.0/src/HrController.php');
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('staff_payrolls');
        Schema::dropIfExists('staff_profiles');
        Schema::enableForeignKeyConstraints();
        $migration = require base_path('plugins/aec/hr-staff-management/1.0.0/migrations/2026_11_21_020000_create_hr_staff_payroll_tables.php');
        $migration->up();
        Route::get('/__hr', [HrController::class, 'index'])->name('branch.hr.index');
        Route::post('/__hr/staff', [HrController::class, 'storeStaff'])->name('branch.hr.staff.store');
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
}
