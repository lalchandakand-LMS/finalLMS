<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
    }

    public function test_student_is_enrolled_only_after_bkash_server_side_verification(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::sequence()
                ->push(['id_token' => 'init-token'])
                ->push(['id_token' => 'verify-token']),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'BKASH-PAY-001',
                'bkashURL' => 'https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-PAY-001',
            ], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-001',
                'trxID' => 'BKASH-TRX-001',
                'amount' => '1500.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-001',
                'trxID' => 'BKASH-TRX-001',
                'amount' => '1500.00',
            ], 200),
        ]);

        $this->actingAs($student)
            ->post(route('courses.payments.bkash', $course))
            ->assertRedirect('https://tokenized.sandbox.bka.sh/checkout/pay/BKASH-PAY-001');

        $payment = Payment::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-001',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-TRX-001',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-PAY-001'
        );
    }

    public function test_callback_with_amount_mismatch_never_activates_enrollment(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();

        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-LOCAL-REF-2',
            'gateway_payment_id' => 'BKASH-PAY-002',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-002',
                'trxID' => 'BKASH-TRX-002',
                'amount' => '1500.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-002',
                'trxID' => 'BKASH-TRX-002',
                'amount' => '1.00',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-002',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_admin_cannot_manually_approve_or_reject_gateway_verified_bkash_payments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-MANUAL-BLOCK-1',
            'gateway_payment_id' => 'BKASH-PAY-MANUAL-BLOCK',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertRedirect();

        $this->post(route('admin.payments.reject', $payment), ['admin_note' => 'Manual rejection attempt'])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }


    public function test_callback_retry_after_approval_is_idempotent_and_does_not_call_gateway_again(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-RETRY-REF',
            'gateway_payment_id' => 'BKASH-PAY-RETRY',
            'gateway_transaction_id' => 'BKASH-TRX-RETRY',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        Http::preventStrayRequests();

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-RETRY',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'approved']);
        $this->assertDatabaseCount('enrollments', 1);
        Http::assertNothingSent();
    }

    public function test_status_query_can_confirm_payment_when_execute_request_fails(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->paidCourse();
        $payment = Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'amount' => '1500.00',
            'currency' => 'BDT',
            'transaction_reference' => 'BKASH-EXEC-RETRY-REF',
            'gateway_payment_id' => 'BKASH-PAY-EXEC-RETRY',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'verify-token'], 200),
            '*/tokenized/checkout/execute' => Http::response(['message' => 'Already executed'], 409),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-PAY-EXEC-RETRY',
                'trxID' => 'BKASH-TRX-EXEC-RETRY',
                'amount' => '1500.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'BKASH-EXEC-RETRY-REF',
            ], 200),
        ]);

        $this->get(route('payments.bkash.callback', [
            'paymentID' => 'BKASH-PAY-EXEC-RETRY',
            'status' => 'success',
        ]))->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'approved',
            'gateway_transaction_id' => 'BKASH-TRX-EXEC-RETRY',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'BKASH-PAY-EXEC-RETRY'
        );
    }

    private function paidCourse(): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'bKash Test Course',
            'slug' => 'bkash-test-course-'.$teacher->id,
            'description' => 'Gateway test course.',
            'price' => 1500,
            'status' => 'published',
        ]);
    }
}
