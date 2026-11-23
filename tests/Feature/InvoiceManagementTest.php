<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LiveClass;
use App\Models\LiveClassAttendance;
use App\Models\AttendanceFeePeriod;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Services\Payments\BkashRefundService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;


    public function test_attendance_fee_billing_creates_one_invoice_per_student_from_present_and_late_records_and_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $noAttendanceStudent = User::factory()->create(['role' => 'student']);
        $branch = Branch::create(['name' => 'Class Fee Branch', 'slug' => 'class-fee-branch']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Per Class English',
            'slug' => 'per-class-english',
            'description' => 'Class attendance fee test.',
            'price' => 0,
            'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Per Class Batch',
            'capacity' => 20,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'fee_calculation_mode' => 'per_class',
            'per_class_fee' => 150,
            'status' => 'open',
        ]);
        foreach ([$student, $noAttendanceStudent] as $enrolled) {
            Enrollment::create([
                'user_id' => $enrolled->id,
                'course_id' => $course->id,
                'batch_id' => $batch->id,
                'status' => 'active',
            ]);
        }
        $period = now()->subMonth()->format('Y-m');
        foreach (['present', 'late', 'absent', 'present'] as $index => $status) {
            $classDate = now()->subMonth()->startOfMonth()->addDays($index + 1);
            $liveClass = LiveClass::create([
                'batch_id' => $batch->id,
                'title' => 'Billable Session '.$index,
                'meeting_url' => 'https://example.test/class/'.$index,
                'starts_at' => $classDate->copy()->setTime(10, 0),
                'ends_at' => $classDate->copy()->setTime(11, 0),
                'status' => 'completed',
                'created_by' => $teacher->id,
            ]);
            LiveClassAttendance::create([
                'live_class_id' => $liveClass->id,
                'student_id' => $student->id,
                'marked_by' => $teacher->id,
                'status' => $status,
            ]);
        }

        $payload = ['billing_period' => $period, 'due_date' => now()->addDays(7)->toDateString()];
        $this->actingAs($admin)
            ->post(route('admin.batches.attendance-fees.generate', $batch), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice = Invoice::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('450.00', $invoice->amount);
        $this->assertSame('Per Class Batch class fee — '.$period, $invoice->title);
        $this->assertDatabaseHas('attendance_fee_periods', [
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'billing_period' => $period,
            'attended_classes' => 3,
            'per_class_fee' => 150,
            'amount' => 450,
            'invoice_id' => $invoice->id,
        ]);
        $this->assertSame(1, Invoice::query()->where('user_id', $student->id)->count());

        $this->post(route('admin.batches.attendance-fees.generate', $batch), $payload)->assertRedirect();
        $this->assertSame(1, Invoice::query()->where('user_id', $student->id)->count());
        $this->assertSame(1, AttendanceFeePeriod::query()->where('batch_id', $batch->id)->where('billing_period', $period)->count());
        $this->assertSame(0, Invoice::query()->where('user_id', $noAttendanceStudent->id)->count());

        $firstSession = LiveClass::query()->where('batch_id', $batch->id)->orderBy('starts_at')->firstOrFail();
        $this->actingAs($teacher)->post(route('live-classes.attendance.store', $firstSession), [
            'records' => [['student_id' => $student->id, 'status' => 'absent']],
        ])->assertStatus(409);
        $this->assertDatabaseHas('live_class_attendances', [
            'live_class_id' => $firstSession->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }


    public function test_branch_manager_cannot_generate_class_fee_invoices_for_another_branch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $localBranch = Branch::create(['name' => 'Local Fee Branch', 'slug' => 'local-fee-branch']);
        $remoteBranch = Branch::create(['name' => 'Remote Fee Branch', 'slug' => 'remote-fee-branch']);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'branch_id' => $localBranch->id,
            'admin_permissions' => ['finance.invoices'],
        ]);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Remote Fee Course',
            'slug' => 'remote-fee-course',
            'description' => 'Branch boundary test.',
            'price' => 0,
            'status' => 'published',
        ]);
        $batch = Batch::create([
            'branch_id' => $remoteBranch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Remote Class Fee Batch',
            'capacity' => 20,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'fee' => 0,
            'fee_calculation_mode' => 'per_class',
            'per_class_fee' => 100,
            'status' => 'completed',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $session = LiveClass::create([
            'batch_id' => $batch->id,
            'title' => 'Remote completed session',
            'meeting_url' => 'https://example.test/remote-session',
            'starts_at' => now()->subMonth()->startOfMonth()->addDay(),
            'ends_at' => now()->subMonth()->startOfMonth()->addDay()->addHour(),
            'status' => 'completed',
            'created_by' => $teacher->id,
        ]);
        LiveClassAttendance::create([
            'live_class_id' => $session->id,
            'student_id' => $student->id,
            'marked_by' => $teacher->id,
            'status' => 'present',
        ]);

        $this->actingAs($manager)
            ->post(route('branch.batches.attendance-fees.generate', $batch), [
                'billing_period' => now()->subMonth()->format('Y-m'),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('attendance_fee_periods', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_admin_can_create_an_invoice_and_student_can_only_see_their_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $otherStudent = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'user_id' => $student->id,
            'title' => 'Course fee installment 1',
            'amount' => 1500,
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->actingAs($student)->get(route('student.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Course fee installment 1');

        $this->actingAs($otherStudent)->get(route('student.invoices.index'))
            ->assertOk()
            ->assertDontSee($invoice->invoice_number);
    }

    public function test_admin_can_record_partial_installments_and_invoice_becomes_paid_at_full_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-TEST-000001',
            'title' => 'Monthly tuition',
            'amount' => 3000,
            'status' => 'unpaid',
        ]);

        $payload = ['amount' => 1000, 'method' => 'cash', 'paid_at' => now()->format('Y-m-d H:i:s')];

        $this->actingAs($admin)->post(route('admin.invoices.payments.store', $invoice), $payload)->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partial']);

        $this->post(route('admin.invoices.payments.store', $invoice), [
            ...$payload,
            'amount' => 2000,
        ])->assertRedirect();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertSame(2, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_admin_cannot_record_more_than_the_outstanding_invoice_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-TEST-000002',
            'title' => 'Tuition',
            'amount' => 500,
            'status' => 'unpaid',
        ]);

        $this->actingAs($admin)->post(route('admin.invoices.payments.store', $invoice), [
            'amount' => 501,
            'method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('invoice_payments', 0);
    }
    public function test_admin_can_apply_percentage_discount_and_net_amount_is_used_for_payment_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($admin)->post(route('admin.invoices.store'), [
            'user_id' => $student->id,
            'title' => 'Scholarship tuition',
            'amount' => 3000,
            'discount_type' => 'percentage',
            'discount_value' => 25,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('3000.00', $invoice->subtotal);
        $this->assertSame('25.00', $invoice->discount_value);
        $this->assertSame('2250.00', $invoice->amount);
        $this->assertSame('percentage', $invoice->discount_type);
    }

    public function test_discount_cannot_exceed_invoice_subtotal_or_percentage_limit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $payload = [
            'user_id' => $student->id,
            'title' => 'Invalid discount',
            'amount' => 1000,
            'discount_type' => 'fixed',
            'discount_value' => 1001,
        ];

        $this->actingAs($admin)->post(route('admin.invoices.store'), $payload)
            ->assertSessionHasErrors('discount_value');
        $this->assertDatabaseCount('invoices', 0);

        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 101;
        $this->post(route('admin.invoices.store'), $payload)
            ->assertSessionHasErrors('discount_value');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_refund_requests_are_capped_and_require_admin_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-0001',
            'title' => 'Tuition fee',
            'amount' => 1000,
            'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $admin->id,
            'amount' => 1000,
            'method' => 'bkash',
            'paid_at' => now(),
        ]);

        $payload = [
            'invoice_payment_id' => $payment->id,
            'amount' => 600,
            'method' => 'cash',
            'reason' => 'Duplicate payment',
        ];

        $this->actingAs($admin)->post(route('admin.refunds.store'), $payload)->assertRedirect();
        $refund = InvoiceRefund::firstOrFail();
        $this->assertSame('pending', $refund->status);

        $this->post(route('admin.refunds.store'), [...$payload, 'amount' => 401])
            ->assertSessionHasErrors('amount');
        $this->assertSame(1, InvoiceRefund::count());

        $this->post(route('admin.refunds.approve', $refund))->assertRedirect();
        $this->assertDatabaseHas('invoice_refunds', [
            'id' => $refund->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partial']);
    }

    public function test_non_admin_cannot_approve_a_refund(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-0002',
            'title' => 'Tuition fee',
            'amount' => 500,
            'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $admin->id,
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now(),
        ]);
        $refund = InvoiceRefund::create([
            'invoice_id' => $invoice->id,
            'invoice_payment_id' => $payment->id,
            'requested_by' => $admin->id,
            'amount' => 100,
            'method' => 'cash',
            'reason' => 'Test refund',
            'status' => 'pending',
        ]);

        $this->actingAs($teacher)->post(route('admin.refunds.approve', $refund))->assertForbidden();
        $this->assertDatabaseHas('invoice_refunds', ['id' => $refund->id, 'status' => 'pending']);
    }

    public function test_bkash_refund_is_not_accounted_until_gateway_confirms_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-BKASH-01', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'received_by' => $admin->id,
            'amount' => 500, 'method' => 'bkash', 'gateway_provider' => 'bkash',
            'gateway_payment_id' => 'BKASH-ORIGINAL-PAYMENT',
            'gateway_transaction_id' => 'BKASH-ORIGINAL-TRX',
            'paid_at' => now(),
        ]);

        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'refund-token'], 200),
            '*/tokenized/checkout/payment/refund' => Http::response(['statusCode' => '0000', 'originalTrxID' => 'BKASH-ORIGINAL-TRX', 'refundTrxID' => 'BKASH-REFUND-TRX', 'amount' => '100.00', 'currency' => 'BDT', 'transactionStatus' => 'Completed']),
            '*/tokenized/checkout/payment/refund/status' => Http::response(['statusCode' => '0000', 'originalTrxID' => 'BKASH-ORIGINAL-TRX', 'refundTrxID' => 'BKASH-REFUND-TRX', 'amount' => '100.00', 'currency' => 'BDT', 'transactionStatus' => 'Completed']),
        ]);

        $payload = [
            'invoice_payment_id' => $payment->id, 'amount' => 100,
            'method' => 'bkash', 'reason' => 'Duplicate payment',
        ];
        $this->actingAs($admin)->post(route('admin.refunds.store'), $payload)->assertRedirect();
        $refund = InvoiceRefund::firstOrFail();

        $this->post(route('admin.refunds.approve', $refund))->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_refunds', [
            'id' => $refund->id, 'status' => 'approved',
            'gateway_refund_id' => 'BKASH-REFUND-TRX',
            'gateway_status' => 'Completed',
        ]);
        $this->assertDatabaseHas('payment_audit_events', [
            'invoice_refund_id' => $refund->id,
            'event' => 'gateway_refund_verified',
        ]);
    }

    public function test_unverified_bkash_refund_stays_reserved_and_does_not_reduce_invoice_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-BKASH-02', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'received_by' => $admin->id,
            'amount' => 500, 'method' => 'bkash', 'gateway_provider' => 'bkash',
            'gateway_payment_id' => 'BKASH-ORIGINAL-PAYMENT-2',
            'gateway_transaction_id' => 'BKASH-ORIGINAL-TRX-2', 'paid_at' => now(),
        ]);
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user', 'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key', 'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'refund-token'], 200),
            '*/tokenized/checkout/payment/refund' => Http::response(['statusCode' => '0000', 'originalTrxID' => 'BKASH-ORIGINAL-TRX-2', 'refundTrxID' => 'BKASH-REFUND-TRX-2', 'amount' => '100.00', 'currency' => 'BDT']),
            '*/tokenized/checkout/payment/refund/status' => Http::response(['statusCode' => '0000', 'originalTrxID' => 'BKASH-ORIGINAL-TRX-2', 'refundTrxID' => 'BKASH-REFUND-TRX-2', 'amount' => '100.00', 'currency' => 'BDT', 'transactionStatus' => 'Processing']),
        ]);

        $this->actingAs($admin)->post(route('admin.refunds.store'), [
            'invoice_payment_id' => $payment->id, 'amount' => 100,
            'method' => 'bkash', 'reason' => 'Gateway verification test',
        ])->assertRedirect();
        $refund = InvoiceRefund::firstOrFail();
        $this->post(route('admin.refunds.approve', $refund))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseHas('invoice_refunds', [
            'id' => $refund->id, 'status' => 'gateway_unknown',
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
    }

    public function test_uncertain_bkash_refund_can_be_reconciled_without_resubmitting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-RECON-01', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'received_by' => $admin->id,
            'amount' => 500, 'method' => 'bkash', 'gateway_provider' => 'bkash',
            'gateway_payment_id' => 'BKASH-RECON-ORIGINAL',
            'gateway_transaction_id' => 'BKASH-RECON-TRX',
            'paid_at' => now(),
        ]);
        $refund = InvoiceRefund::create([
            'invoice_id' => $invoice->id, 'invoice_payment_id' => $payment->id,
            'requested_by' => $admin->id, 'approved_by' => $admin->id,
            'amount' => 100, 'method' => 'bkash', 'reason' => 'Timeout during refund',
            'status' => 'gateway_unknown', 'gateway_status' => 'verification_unknown',
        ]);

        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user', 'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key', 'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'reconcile-refund-token'], 200),
            '*/tokenized/checkout/payment/refund/status' => Http::response([
                'statusCode' => '0000', 'originalTrxID' => 'BKASH-RECON-TRX',
                'refundTrxID' => 'BKASH-RECON-REFUND', 'amount' => '100.00',
                'currency' => 'BDT', 'transactionStatus' => 'Completed',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.refunds.reconcile', $refund))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_refunds', [
            'id' => $refund->id, 'status' => 'approved',
            'gateway_refund_id' => 'BKASH-RECON-REFUND',
        ]);
        $this->assertDatabaseHas('payment_audit_events', [
            'invoice_refund_id' => $refund->id,
            'event' => 'gateway_refund_reconciled',
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'partial']);
    }

    public function test_invoice_bkash_receipt_can_be_reconciled_against_gateway(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-INVOICE-RECON-01', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'paid',
        ]);
        $receipt = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'received_by' => $admin->id,
            'amount' => 500, 'method' => 'bkash', 'gateway_provider' => 'bkash',
            'gateway_payment_id' => 'BKASH-INVOICE-PAY',
            'gateway_transaction_id' => 'BKASH-INVOICE-TRX',
            'paid_at' => now(),
        ]);
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user', 'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key', 'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'invoice-reconcile-token'], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'BKASH-INVOICE-PAY',
                'trxID' => 'BKASH-INVOICE-TRX',
                'amount' => '500.00',
                'currency' => 'BDT',
                'merchantInvoiceNumber' => 'AEC-INVOICE-RECON-01',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.payment-reconciliation.run'), [
            'invoice_payment_id' => $receipt->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_reconciliations', [
            'invoice_payment_id' => $receipt->id,
            'result' => 'matched',
            'gateway_status' => 'completed',
        ]);
        $this->assertDatabaseHas('payment_audit_events', [
            'invoice_payment_id' => $receipt->id,
            'event' => 'invoice_reconciliation_matched',
        ]);
    }

    public function test_bkash_invoice_receipt_is_recorded_only_after_exact_gateway_verification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-BKASH-INV-100', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'unpaid',
        ]);
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user', 'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key', 'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'invoice-payment-token'], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed', 'paymentID' => 'BKASH-INVOICE-PAY-100',
                'trxID' => 'BKASH-INVOICE-TRX-100', 'amount' => '500.00',
                'currency' => 'BDT', 'merchantInvoiceNumber' => 'AEC-BKASH-INV-100',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.invoices.payments.store', $invoice), [
            'amount' => 500, 'method' => 'bkash',
            'gateway_payment_id' => 'BKASH-INVOICE-PAY-100',
            'gateway_transaction_id' => 'BKASH-INVOICE-TRX-100',
            'reference' => 'BKASH-INVOICE-TRX-100', 'paid_at' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id, 'gateway_payment_id' => 'BKASH-INVOICE-PAY-100',
            'gateway_transaction_id' => 'BKASH-INVOICE-TRX-100',
        ]);
        $this->assertDatabaseHas('payment_gateway_events', [
            'event_key' => hash('sha256', 'bkash:payment:BKASH-INVOICE-PAY-100:BKASH-INVOICE-TRX-100'),
            'invoice_payment_id' => InvoicePayment::firstOrFail()->id,
            'event_type' => 'invoice.payment.verified',
            'processing_status' => 'processed',
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
        $this->assertDatabaseHas('payment_audit_events', [
            'event' => 'invoice_payment_recorded',
        ]);
    }

    public function test_bkash_invoice_receipt_rejects_gateway_invoice_or_amount_mismatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-BKASH-INV-101', 'title' => 'Tuition',
            'amount' => 500, 'status' => 'unpaid',
        ]);
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user', 'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key', 'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);
        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'invoice-payment-token'], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed', 'paymentID' => 'BKASH-INVOICE-PAY-101',
                'trxID' => 'BKASH-INVOICE-TRX-101', 'amount' => '500.00',
                'currency' => 'BDT', 'merchantInvoiceNumber' => 'ANOTHER-INVOICE',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('admin.invoices.payments.store', $invoice), [
            'amount' => 500, 'method' => 'bkash',
            'gateway_payment_id' => 'BKASH-INVOICE-PAY-101',
            'gateway_transaction_id' => 'BKASH-INVOICE-TRX-101',
            'paid_at' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('gateway_payment_id');

        $this->assertDatabaseMissing('invoice_payments', ['gateway_payment_id' => 'BKASH-INVOICE-PAY-101']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'unpaid']);
    }

    public function test_bkash_refund_verification_rejects_non_bdt_settlement_currency(): void
    {
        config([
            'services.bkash.enabled' => true,
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'refund-currency-token'], 200),
            '*/tokenized/checkout/payment/refund' => Http::response([
                'statusCode' => '0000', 'originalTrxID' => 'BKASH-CURRENCY-TRX',
                'refundTrxID' => 'BKASH-CURRENCY-REFUND', 'amount' => '25.00', 'currency' => 'BDT',
            ], 200),
            '*/tokenized/checkout/payment/refund/status' => Http::response([
                'statusCode' => '0000', 'originalTrxID' => 'BKASH-CURRENCY-TRX',
                'refundTrxID' => 'BKASH-CURRENCY-REFUND', 'amount' => '25.00',
                'currency' => 'USD', 'transactionStatus' => 'Completed',
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);

        app(\App\Services\Payments\BkashRefundService::class)->refund(
            'BKASH-CURRENCY-PAY',
            'BKASH-CURRENCY-TRX',
            '25.00',
            'Currency verification test'
        );
    }


    public function test_stale_invoice_reconciliation_failure_cannot_overwrite_a_concurrently_completed_refund(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-REFUND-RACE-001',
            'title' => 'Race-condition tuition',
            'amount' => 500,
            'status' => 'paid',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $admin->id,
            'amount' => 500,
            'method' => 'bkash',
            'gateway_provider' => 'bkash',
            'gateway_payment_id' => 'BKASH-RACE-PAYMENT',
            'gateway_transaction_id' => 'BKASH-RACE-TRX',
            'paid_at' => now(),
        ]);
        $refund = InvoiceRefund::create([
            'invoice_id' => $invoice->id,
            'invoice_payment_id' => $payment->id,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'amount' => 100,
            'method' => 'bkash',
            'reason' => 'Concurrent reconciliation test',
            'status' => 'gateway_unknown',
        ]);

        $gateway = \Mockery::mock(BkashRefundService::class);
        $gateway->shouldReceive('verifyExisting')->once()->andReturnUsing(function () use ($refund): array {
            $refund->update([
                'status' => 'approved',
                'gateway_refund_id' => 'BKASH-RACE-REFUND-VERIFIED',
                'gateway_status' => 'Completed',
                'processed_at' => now(),
            ]);

            throw new \RuntimeException('Simulated stale request failure after concurrent completion.');
        });
        $this->app->instance(BkashRefundService::class, $gateway);

        $this->actingAs($admin)->post(route('admin.refunds.reconcile', $refund))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoice_refunds', [
            'id' => $refund->id,
            'status' => 'approved',
            'gateway_refund_id' => 'BKASH-RACE-REFUND-VERIFIED',
            'gateway_status' => 'Completed',
        ]);
        $this->assertDatabaseMissing('payment_audit_events', [
            'invoice_refund_id' => $refund->id,
            'event' => 'gateway_refund_reconciliation_unconfirmed',
        ]);
    }


}
