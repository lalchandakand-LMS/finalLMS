<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_in_a_free_course_through_mobile_api(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course(0);
        $this->authenticate($student);

        $this->postJson('/api/v1/courses/'.$course->slug.'/checkout', [])
            ->assertCreated()
            ->assertJsonPath('status', 'enrolled');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_student_can_submit_manual_course_payment_with_server_calculated_price(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course(1250);
        Setting::setValue('payment_methods', json_encode([
            'bkash' => ['enabled' => false],
            'rocket' => ['enabled' => true, 'label' => 'Rocket'],
            'bank' => ['enabled' => false],
            'manual' => ['enabled' => false],
        ]));
        $this->authenticate($student);

        $this->postJson('/api/v1/courses/'.$course->slug.'/checkout', [
            'payment_method' => 'rocket',
            'transaction_reference' => 'ROCKET-MOBILE-TEST-001',
            'sender_account' => '01700000000',
            'amount' => 1,
            'user_id' => 999999,
        ])->assertCreated()
            ->assertJsonPath('status', 'payment_submitted')
            ->assertJsonPath('amount', 1250);

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'rocket',
            'amount' => '1250.00',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_mobile_checkout_rejects_non_student_accounts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = $this->course(0);
        $this->authenticate($teacher);

        $this->postJson('/api/v1/courses/'.$course->slug.'/checkout', [])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_mobile_checkout_rejects_batch_ids_from_another_course(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course(900);
        $otherCourse = $this->course(700);
        $batch = $otherCourse->batches()->create([
            'title' => 'Different course batch',
            'capacity' => 10,
            'fee' => 900,
            'status' => 'open',
        ]);
        $this->authenticate($student);

        $this->postJson('/api/v1/courses/'.$course->slug.'/checkout', [
            'batch_id' => $batch->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_paid_mobile_checkout_requires_a_payment_method_before_creating_payment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->course(500);
        $this->authenticate($student);

        $this->postJson('/api/v1/courses/'.$course->slug.'/checkout', [])
            ->assertStatus(409)
            ->assertJsonPath('status', 'payment_method_required');

        $this->assertDatabaseCount('payments', 0);
    }

    private function course(float $price): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        return Course::factory()->create([
            'teacher_id' => $teacher->id,
            'price' => $price,
            'status' => 'published',
        ]);
    }

    private function authenticate(User $user): void
    {
        $plainToken = 'checkout-api-token-'.Str::random(48);
        $device = StudentDevice::create([
            'user_id' => $user->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Checkout test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $user->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => 'Checkout test',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);
        $this->withToken($plainToken);
    }
}
