<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use App\Modules\Hr\StaffLeaveRequest;
use App\Modules\Hr\StaffPayroll;
use App\Modules\Hr\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HrReportsAndLeaveCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_filtered_monthly_payroll_with_spreadsheet_safe_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create([
            'employee_code' => '=FORMULA-01', 'full_name' => '+Payroll Employee',
            'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 25000,
            'status' => 'active',
        ]);
        StaffPayroll::query()->create([
            'staff_profile_id' => $staff->id, 'payroll_month' => '2026-10',
            'basic_salary' => 25000, 'gross_salary' => 25000, 'bonus' => 0,
            'deductions' => 0, 'net_paid' => 25000, 'status' => 'paid',
        ]);
        $response = $this->actingAs($admin)->get(route('admin.hr.payroll-reports.export', ['month' => '2026-10', 'status' => 'paid']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=FORMULA-01", $csv);
        $this->assertStringContainsString("'+Payroll Employee", $csv);
    }

    public function test_branch_manager_payroll_export_is_limited_to_assigned_branch(): void
    {
        $branch = Branch::query()->create(['name' => 'Payroll Branch A', 'slug' => 'payroll-branch-a']);
        $otherBranch = Branch::query()->create(['name' => 'Payroll Branch B', 'slug' => 'payroll-branch-b']);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        foreach ([[$branch, 'Visible Staff'], [$otherBranch, 'Hidden Staff']] as [$staffBranch, $name]) {
            $staff = StaffProfile::query()->create([
                'branch_id' => $staffBranch->id, 'employee_code' => strtoupper(str_replace(' ', '-', $name)),
                'full_name' => $name, 'designation' => 'Teacher', 'department' => 'Academic',
                'base_salary' => 20000, 'status' => 'active',
            ]);
            StaffPayroll::query()->create([
                'staff_profile_id' => $staff->id, 'branch_id' => $staffBranch->id,
                'payroll_month' => '2026-10', 'basic_salary' => 20000,
                'gross_salary' => 20000, 'bonus' => 0, 'deductions' => 0,
                'net_paid' => 20000, 'status' => 'paid',
            ]);
        }

        $response = $this->actingAs($manager)->get(route('branch.hr.payroll-reports.export', ['month' => '2026-10']));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Visible Staff', $csv);
        $this->assertStringNotContainsString('Hidden Staff', $csv);
    }

    public function test_branch_manager_leave_calendar_only_shows_their_branch_and_overlapping_month_requests(): void
    {
        $branch = Branch::query()->create(['name' => 'Leave Branch A', 'slug' => 'leave-branch-a']);
        $otherBranch = Branch::query()->create(['name' => 'Leave Branch B', 'slug' => 'leave-branch-b']);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);
        foreach ([[$branch, 'Visible Leave Staff', '2026-10-30', '2026-11-02'], [$otherBranch, 'Hidden Leave Staff', '2026-11-10', '2026-11-11']] as [$staffBranch, $name, $from, $to]) {
            $staff = StaffProfile::query()->create([
                'branch_id' => $staffBranch->id, 'employee_code' => strtoupper(str_replace(' ', '-', $name)),
                'full_name' => $name, 'designation' => 'Teacher', 'department' => 'Academic',
                'base_salary' => 20000, 'status' => 'active',
            ]);
            StaffLeaveRequest::query()->create([
                'staff_profile_id' => $staff->id, 'branch_id' => $staffBranch->id,
                'leave_type' => 'annual', 'starts_on' => $from, 'ends_on' => $to,
                'reason' => 'Annual leave', 'status' => 'approved',
            ]);
        }

        $this->actingAs($manager)->get(route('branch.hr.leave-calendar', ['month' => '2026-11']))
            ->assertOk()->assertSee('Visible Leave Staff')->assertDontSee('Hidden Leave Staff');
    }

    public function test_non_hr_roles_cannot_access_hr_reports(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.hr.payroll-reports'))->assertForbidden();
    }
}
