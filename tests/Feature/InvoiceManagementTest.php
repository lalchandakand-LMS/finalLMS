<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_invoice_and_student_can_only_see_their_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

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
        $this->assertSame('750.00', $invoice->discount_value);
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
            'method' => 'bkash',
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

}
