<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use App\Modules\Hr\StaffAttendance;
use App\Modules\Hr\StaffLeaveRequest;
use App\Modules\Hr\StaffPayroll;
use App\Modules\Hr\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NativeHrPayrollRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_payroll_snapshots_absence_half_day_and_approved_unpaid_leave(): void
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
            [1, 'present'], [2, 'present'], [3, 'absent'],
            [4, 'half_day'], [5, 'on_leave'],
        ] as [$day, $status]) {
            StaffAttendance::query()->create([
                'staff_profile_id' => $staff->id,
                'attendance_date' => $monthStart->copy()->addDays($day - 1)->toDateString(),
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
            'starts_on' => $monthStart->copy()->addDays(4)->toDateString(),
            'ends_on' => $monthStart->copy()->addDays(4)->toDateString(),
            'reason' => 'Approved unpaid leave',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.hr.payrolls.store'), [
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

    public function test_attendance_payroll_rejects_incomplete_working_day_register(): void
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

        $this->actingAs($admin)->post(route('admin.hr.payrolls.store'), [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->subMonth()->format('Y-m'),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('staff_payrolls', 0);
    }

    public function test_per_class_payroll_counts_only_completed_classes_for_linked_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $branch = Branch::query()->create(['name' => 'Per Class Payroll', 'slug' => 'per-class-payroll']);
        $monthStart = now()->subMonth()->startOfMonth();
        $course = Course::factory()->create(['status' => 'published']);
        $batch = Batch::query()->create([
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
        $otherBatch = Batch::query()->create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other Teacher Batch',
            'capacity' => 20,
            'starts_at' => $monthStart,
            'ends_at' => $monthStart->copy()->endOfMonth(),
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
            LiveClass::query()->create([
                'batch_id' => $classBatch->id,
                'title' => 'Payroll Class '.$index,
                'meeting_url' => 'https://example.test/meeting/'.$index,
                'starts_at' => $monthStart->copy()->addDays($index + 1)->setTime(10, 0),
                'ends_at' => $monthStart->copy()->addDays($index + 1)->setTime(11, 0),
                'status' => $status,
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)->post(route('admin.hr.payrolls.store'), [
            'staff_profile_id' => $staff->id,
            'payroll_month' => now()->subMonth()->format('Y-m'),
        ])->assertRedirect();

        $payroll = StaffPayroll::query()->firstOrFail();
        $this->assertSame('per_class', $payroll->compensation_basis_snapshot);
        $this->assertSame(2, $payroll->classes_delivered);
        $this->assertSame('2400.00', $payroll->basic_salary);
        $this->assertSame('2400.00', $payroll->net_paid);
    }
}
