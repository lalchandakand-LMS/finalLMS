<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCapacityPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_manual_payment_reserves_the_last_batch_seat(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Business English',
            'slug' => 'business-english',
            'description' => 'Business English.',
            'price' => 100,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'One seat only',
            'capacity' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 100,
            'status' => 'open',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => 'Account', 'instructions' => 'Pay'],
        ]));

        $this->actingAs($firstStudent)
            ->post(route('courses.enroll', $course), [
                'batch_id' => $batch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'CAPACITY-TXN-001',
            ]);

        $this->actingAs($secondStudent)
            ->from(route('courses.show', $course))
            ->post(route('courses.enroll', $course), [
                'batch_id' => $batch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'CAPACITY-TXN-002',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_assigned_student_sees_and_checks_out_only_within_their_branch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $branchA = Branch::create(['name' => 'Assigned Branch A', 'slug' => 'assigned-branch-a']);
        $branchB = Branch::create(['name' => 'Restricted Branch B', 'slug' => 'restricted-branch-b']);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branchA->id]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Branch Scoped Checkout',
            'slug' => 'branch-scoped-checkout',
            'description' => 'Branch-specific batches.',
            'price' => 1000,
            'status' => 'published',
        ]);
        $ownBatch = Batch::create([
            'branch_id' => $branchA->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'title' => 'Branch A Batch', 'capacity' => 20, 'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(), 'fee' => 900, 'status' => 'open',
        ]);
        $foreignBatch = Batch::create([
            'branch_id' => $branchB->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'title' => 'Branch B Batch', 'capacity' => 20, 'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(), 'fee' => 800, 'status' => 'open',
        ]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => 'Account', 'instructions' => 'Pay'],
        ]));

        $this->actingAs($student)->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Branch A Batch')
            ->assertDontSee('Branch B Batch');

        $this->from(route('courses.show', $course))
            ->post(route('courses.enroll', $course), [
                'batch_id' => $foreignBatch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'FOREIGN-BRANCH-CHECKOUT',
            ])
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'batch_id' => $foreignBatch->id,
        ]);
        $this->assertDatabaseHas('batches', ['id' => $ownBatch->id, 'branch_id' => $branchA->id]);
    }

    public function test_admin_cannot_approve_a_payment_after_its_batch_is_full(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $enrolledStudent = User::factory()->create(['role' => 'student']);
        $payingStudent = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Business English',
            'slug' => 'business-english',
            'description' => 'Business English.',
            'price' => 100,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'One seat only',
            'capacity' => 1,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 100,
            'status' => 'open',
        ]);
        Enrollment::create([
            'user_id' => $enrolledStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $payment = Payment::create([
            'user_id' => $payingStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'method' => 'manual',
            'amount' => 100,
            'currency' => 'BDT',
            'transaction_reference' => 'CAPACITY-APPROVAL-001',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.approve', $payment))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $payingStudent->id, 'batch_id' => $batch->id]);
    }
}
