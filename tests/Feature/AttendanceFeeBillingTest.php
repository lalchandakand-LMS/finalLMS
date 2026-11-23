<?php

namespace Tests\Feature;

use App\Models\AttendanceFeePeriod;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\LiveClass;
use App\Models\LiveClassAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFeeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_fee_billing_creates_one_invoice_per_student_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $noAttendanceStudent = User::factory()->create(['role' => 'student']);
        $branch = Branch::create(['name' => 'Class Fee Branch', 'slug' => 'class-fee-branch']);
        $course = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Per Class English', 'slug' => 'per-class-english',
            'description' => 'Class attendance fee test.', 'price' => 0, 'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'title' => 'Per Class Batch', 'capacity' => 20, 'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(), 'fee' => 0, 'fee_calculation_mode' => 'per_class',
            'per_class_fee' => 150, 'status' => 'open',
        ]);
        foreach ([$student, $noAttendanceStudent] as $enrolled) {
            Enrollment::create(['user_id' => $enrolled->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        }

        $period = now()->subMonth()->format('Y-m');
        foreach (['present', 'late', 'absent', 'present'] as $index => $status) {
            $classDate = now()->subMonth()->startOfMonth()->addDays($index + 1);
            $liveClass = LiveClass::create([
                'batch_id' => $batch->id, 'title' => 'Billable Session '.$index,
                'meeting_url' => 'https://example.test/class/'.$index,
                'starts_at' => $classDate->copy()->setTime(10, 0),
                'ends_at' => $classDate->copy()->setTime(11, 0), 'status' => 'completed',
                'created_by' => $teacher->id,
            ]);
            LiveClassAttendance::create([
                'live_class_id' => $liveClass->id, 'student_id' => $student->id,
                'marked_by' => $teacher->id, 'status' => $status,
            ]);
        }

        $payload = ['billing_period' => $period, 'due_date' => now()->addDays(7)->toDateString()];
        $this->actingAs($admin)->post(route('admin.batches.attendance-fees.generate', $batch), $payload)->assertRedirect();

        $invoice = Invoice::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('450.00', (string) $invoice->amount);
        $this->assertDatabaseHas('attendance_fee_periods', [
            'batch_id' => $batch->id, 'student_id' => $student->id, 'billing_period' => $period,
            'attended_classes' => 3, 'amount' => 450, 'invoice_id' => $invoice->id,
        ]);

        $this->post(route('admin.batches.attendance-fees.generate', $batch), $payload)->assertRedirect();
        $this->assertSame(1, Invoice::query()->where('user_id', $student->id)->count());
        $this->assertSame(1, AttendanceFeePeriod::query()->where('batch_id', $batch->id)->where('billing_period', $period)->count());
        $this->assertSame(0, Invoice::query()->where('user_id', $noAttendanceStudent->id)->count());
    }

    public function test_branch_workspace_cannot_generate_class_fees_for_another_branch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $localBranch = Branch::create(['name' => 'Local Fee Branch', 'slug' => 'local-fee-branch']);
        $remoteBranch = Branch::create(['name' => 'Remote Fee Branch', 'slug' => 'remote-fee-branch']);
        $manager = User::factory()->create([
            'role' => 'branch_manager', 'branch_id' => $localBranch->id,
            'admin_permissions' => ['finance.invoices'],
        ]);
        $course = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'Remote Fee Course', 'slug' => 'remote-fee-course',
            'description' => 'Branch boundary test.', 'price' => 0, 'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $remoteBranch->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'title' => 'Remote Class Fee Batch', 'capacity' => 20, 'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(), 'fee' => 0, 'fee_calculation_mode' => 'per_class',
            'per_class_fee' => 100, 'status' => 'completed',
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $session = LiveClass::create([
            'batch_id' => $batch->id, 'title' => 'Remote completed session',
            'meeting_url' => 'https://example.test/remote-session',
            'starts_at' => now()->subMonth()->startOfMonth()->addDay(),
            'ends_at' => now()->subMonth()->startOfMonth()->addDay()->addHour(),
            'status' => 'completed', 'created_by' => $teacher->id,
        ]);
        LiveClassAttendance::create([
            'live_class_id' => $session->id, 'student_id' => $student->id,
            'marked_by' => $teacher->id, 'status' => 'present',
        ]);

        $this->actingAs($manager)
            ->post(route('admin.branch-workspace.batches.attendance-fees.generate', $batch), [
                'billing_period' => now()->subMonth()->format('Y-m'),
            ])->assertNotFound();

        $this->assertDatabaseCount('attendance_fee_periods', 0);
        $this->assertDatabaseCount('invoices', 0);
    }
}
