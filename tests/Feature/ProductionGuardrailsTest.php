<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_only_shows_their_own_certificates(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'English Course', 0);

        Certificate::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_number' => 'OWN-CERTIFICATE',
            'status' => 'issued',
            'issued_at' => now(),
        ]);
        Certificate::create([
            'user_id' => $otherStudent->id,
            'course_id' => $course->id,
            'certificate_number' => 'OTHER-CERTIFICATE',
            'status' => 'issued',
            'issued_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('OWN-CERTIFICATE')
            ->assertDontSee('OTHER-CERTIFICATE');
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.updateRole', $admin), ['role' => 'student'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_pending_payment_reserves_the_last_batch_seat(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'Paid English', 100);
        $batch = $this->batch($teacher, $course, 1);
        $this->enableManualPayments();

        $this->actingAs($firstStudent)
            ->post(route('courses.enroll', $course), [
                'batch_id' => $batch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'RESERVE-001',
            ]);

        $this->actingAs($secondStudent)
            ->from(route('courses.show', $course))
            ->post(route('courses.enroll', $course), [
                'batch_id' => $batch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'RESERVE-002',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_admin_cannot_approve_payment_after_batch_capacity_is_full(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $enrolledStudent = User::factory()->create(['role' => 'student']);
        $payingStudent = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'Paid English', 100);
        $batch = $this->batch($teacher, $course, 1);
        Enrollment::create(['user_id' => $enrolledStudent->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $payment = Payment::create([
            'user_id' => $payingStudent->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'method' => 'manual',
            'amount' => 100,
            'currency' => 'BDT',
            'transaction_reference' => 'FULL-001',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.approve', $payment))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $payingStudent->id, 'batch_id' => $batch->id]);
    }

    public function test_batch_fee_is_charged_even_when_course_is_free(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'Free course with paid batch', 0);
        $batch = $this->batch($teacher, $course, 5);
        $batch->update(['fee' => 250]);
        $this->enableManualPayments();

        $this->actingAs($student)
            ->post(route('courses.enroll', $course), [
                'batch_id' => $batch->id,
                'payment_method' => 'manual',
                'transaction_reference' => 'BATCH-FEE-001',
            ]);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'amount' => '250.00',
        ]);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_payment_transaction_reference_cannot_be_reused_for_same_method(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);
        $course = $this->course($teacher, 'Paid English', 100);
        $this->enableManualPayments();

        $this->actingAs($firstStudent)
            ->post(route('courses.enroll', $course), [
                'payment_method' => 'manual',
                'transaction_reference' => 'DUPLICATE-REF-001',
            ]);

        $this->actingAs($secondStudent)
            ->post(route('courses.enroll', $course), [
                'payment_method' => 'manual',
                'transaction_reference' => 'DUPLICATE-REF-001',
            ])
            ->assertSessionHasErrors('transaction_reference');

        $this->assertDatabaseCount('payments', 1);
    }

    private function course(User $teacher, string $title, float $price): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'description' => 'Course description.',
            'price' => $price,
            'status' => 'published',
        ]);
    }

    private function batch(User $teacher, Course $course, int $capacity): Batch
    {
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);

        return Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Evening Batch',
            'capacity' => $capacity,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => $course->price,
            'status' => 'open',
        ]);
    }

    private function enableManualPayments(): void
    {
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => 'Account', 'instructions' => 'Pay'],
        ]));
    }
}
