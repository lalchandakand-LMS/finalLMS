<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileStudentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_checkout_options_and_submit_a_manual_payment(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $this->enableManualPayments();
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/checkout/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('data.course.id', $course->id)
            ->assertJsonPath('data.batches.0.id', $batch->id)
            ->assertJsonPath('data.batches.0.available_seats', 20)
            ->assertJsonPath('data.batches.0.base_amount', '2500.00')
            ->assertJsonPath('data.batches.0.discounted_amount', '2500.00')
            ->assertJsonPath('data.payment_methods.0.key', 'manual')
            ->assertJsonPath('data.pricing.base_amount', '3000.00');

        $this->withToken($token)->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
            'batch_id' => $batch->id,
            'payment_method' => 'manual',
            'sender_account' => '01700000000',
            'transaction_reference' => 'AEC-MOBILE-REF-001',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '2500.00')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'method' => 'manual',
            'transaction_reference' => 'AEC-MOBILE-REF-001',
            'status' => 'pending',
            'amount' => '2500.00',
        ]);

        $this->withToken($token)->getJson('/api/v1/my/payments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.course.slug', $course->slug)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_pending_payment_reserves_batch_capacity_and_duplicate_checkout_is_rejected(): void
    {
        [$student, $course, $batch] = $this->fixture(['capacity' => 1]);
        $this->enableManualPayments();
        $token = $this->token($student);

        $this->withToken($token)->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
            'batch_id' => $batch->id,
            'payment_method' => 'manual',
            'transaction_reference' => 'AEC-MOBILE-REF-002',
        ])->assertCreated();

        $secondStudent = User::factory()->create(['role' => 'student']);
        $secondToken = $this->token($secondStudent);
        $this->withToken($secondToken)->getJson('/api/v1/checkout/courses/'.$course->slug)
            ->assertOk()
            ->assertJsonPath('data.batches.0.available_seats', 0);

        $this->withToken($secondToken)->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
            'batch_id' => $batch->id,
            'payment_method' => 'manual',
            'transaction_reference' => 'AEC-MOBILE-REF-003',
        ])->assertStatus(409)->assertJsonPath('message', 'The selected batch is full or no longer available.');

        $this->withToken($token)->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
            'batch_id' => $batch->id,
            'payment_method' => 'manual',
            'transaction_reference' => 'AEC-MOBILE-REF-004',
        ])->assertStatus(409);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_free_course_enrollment_does_not_require_payment_methods_or_transaction_details(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $course->update(['price' => 0]);
        $batch->update(['fee' => 0]);
        $token = $this->token($student);

        $this->withToken($token)->postJson('/api/v1/checkout/courses/'.$course->slug.'/manual', [
            'batch_id' => $batch->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_student_can_only_view_their_own_payment_details(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $other = User::factory()->create(['role' => 'student']);
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'method' => 'manual',
            'amount' => 2500,
            'currency' => 'BDT',
            'transaction_reference' => 'AEC-PRIVATE-PAYMENT',
            'status' => 'pending',
        ]);
        $token = $this->token($other);

        $this->withToken($token)->getJson('/api/v1/my/payments/'.$payment->id)->assertNotFound();
    }

    public function test_student_cannot_use_mobile_checkout_for_unpublished_courses_or_another_courses_batch(): void
    {
        [$student, $course, $batch] = $this->fixture();
        $this->enableManualPayments();
        $token = $this->token($student);
        $otherCourse = Course::factory()->create([
            'teacher_id' => $course->teacher_id,
            'status' => 'published',
            'price' => 1000,
        ]);
        $draft = Course::factory()->create([
            'teacher_id' => $course->teacher_id,
            'status' => 'draft',
            'price' => 1000,
        ]);

        $this->withToken($token)->getJson('/api/v1/checkout/courses/'.$draft->slug)->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/checkout/courses/'.$otherCourse->slug.'/manual', [
            'batch_id' => $batch->id,
            'payment_method' => 'manual',
            'transaction_reference' => 'AEC-MOBILE-REF-004',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
    }

    private function fixture(array $batchOverrides = []): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Checkout Course',
            'status' => 'published',
            'price' => 3000,
        ]);
        $branch = Branch::create(['name' => 'Checkout Branch', 'slug' => 'checkout-branch']);
        $batch = Batch::create(array_merge([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Checkout Batch',
            'capacity' => 20,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 2500,
            'status' => 'open',
        ], $batchOverrides));

        return [$student, $course, $batch];
    }

    private function enableManualPayments(): void
    {
        Setting::setValue('payment_methods', json_encode([
            'manual' => [
                'enabled' => true,
                'label' => 'Manual payment',
                'account' => '01700000000',
                'instructions' => 'Send payment and enter transaction reference.',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function token(User $student): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Checkout API test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'name' => 'Checkout API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
