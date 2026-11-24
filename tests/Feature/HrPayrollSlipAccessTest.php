<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\StaffPayroll;
use App\Modules\Hr\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HrPayrollSlipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_print_a_payroll_slip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = StaffProfile::query()->create([
            'employee_code' => 'SLIP-ADMIN-01', 'full_name' => 'Slip Employee',
            'designation' => 'Teacher', 'department' => 'Academic', 'base_salary' => 25000,
            'status' => 'active',
        ]);
        $payroll = StaffPayroll::query()->create([
            'staff_profile_id' => $staff->id, 'payroll_month' => '2026-10',
            'basic_salary' => 25000, 'allowances' => 1000, 'overtime_amount' => 0,
            'gross_salary' => 26000, 'bonus' => 500, 'deductions' => 1000,
            'net_paid' => 25500, 'status' => 'paid', 'compensation_basis_snapshot' => 'monthly',
        ]);

        $this->actingAs($admin)->get(route('admin.hr.payrolls.slip', $payroll))
            ->assertOk()->assertSee('Slip Employee')->assertSee('25,500.00')->assertSee('Print / Save PDF');
    }

    public function test_teacher_can_only_view_their_own_payroll_slip(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $ownProfile = StaffProfile::query()->create([
            'user_id' => $teacher->id, 'employee_code' => 'SLIP-TEACHER-01',
            'full_name' => 'Linked Teacher', 'designation' => 'Teacher',
            'department' => 'Academic', 'base_salary' => 0, 'status' => 'active',
        ]);
        $otherProfile = StaffProfile::query()->create([
            'employee_code' => 'SLIP-OTHER-01', 'full_name' => 'Other Employee',
            'designation' => 'Staff', 'department' => 'Operations', 'base_salary' => 20000,
            'status' => 'active',
        ]);
        $own = StaffPayroll::query()->create([
            'staff_profile_id' => $ownProfile->id, 'payroll_month' => '2026-10',
            'basic_salary' => 1000, 'allowances' => 0, 'overtime_amount' => 0,
            'gross_salary' => 1000, 'bonus' => 0, 'deductions' => 0, 'net_paid' => 1000,
            'status' => 'pending', 'compensation_basis_snapshot' => 'per_class',
        ]);
        $other = StaffPayroll::query()->create([
            'staff_profile_id' => $otherProfile->id, 'payroll_month' => '2026-10',
            'basic_salary' => 20000, 'allowances' => 0, 'overtime_amount' => 0,
            'gross_salary' => 20000, 'bonus' => 0, 'deductions' => 0, 'net_paid' => 20000,
            'status' => 'pending', 'compensation_basis_snapshot' => 'monthly',
        ]);

        $this->actingAs($teacher)->get(route('teacher.hr.payrolls.slip', $own))
            ->assertOk()->assertSee('Linked Teacher');
        $this->actingAs($teacher)->get(route('teacher.hr.payrolls.slip', $other))
            ->assertNotFound();
    }
}
