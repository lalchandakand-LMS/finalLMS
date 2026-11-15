<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_owned_invoices_and_open_expiring_printable_invoice_and_receipts(): void
    {
        [$student, $token] = $this->studentWithToken();
        $admin = User::factory()->create(['role' => 'admin']);
        $invoice = Invoice::create([
            'user_id' => $student->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-TEST-INV-001', 'title' => 'Spoken English fees',
            'description' => 'Course tuition', 'amount' => 1000,
            'subtotal' => 1200, 'discount_type' => 'fixed', 'discount_value' => 200,
            'status' => 'partial',
        ]);
        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'received_by' => $admin->id,
            'amount' => 400, 'method' => 'bkash', 'reference' => 'TXN-INV-001',
            'paid_at' => now(),
        ]);
        InvoiceRefund::create([
            'invoice_id' => $invoice->id, 'invoice_payment_id' => $payment->id,
            'requested_by' => $admin->id, 'approved_by' => $admin->id,
            'amount' => 50, 'method' => 'bkash', 'reason' => 'Overpayment adjustment',
            'status' => 'approved', 'processed_at' => now(),
        ]);

        $list = $this->withToken($token)->getJson('/api/v1/my/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_number', 'AEC-TEST-INV-001')
            ->assertJsonPath('data.0.net_paid', '350.00')
            ->assertJsonPath('data.0.outstanding', '650.00');
        $invoiceUrl = $list->json('data.0.print_url');
        $receiptUrl = $list->json('data.0.payments.0.print_url');

        $this->get($invoiceUrl)->assertOk()->assertSee('AEC-TEST-INV-001');
        $this->get($receiptUrl)->assertOk()->assertSee('TXN-INV-001');

        $this->withToken($token)->getJson('/api/v1/my/invoices/'.$invoice->id)
            ->assertOk()->assertJsonPath('data.payments.0.reference', 'TXN-INV-001');
    }

    public function test_student_cannot_read_another_students_invoice_or_create_a_print_link_for_it(): void
    {
        [$owner] = $this->studentWithToken();
        [$other, $otherToken] = $this->studentWithToken();
        $admin = User::factory()->create(['role' => 'admin']);
        $invoice = Invoice::create([
            'user_id' => $owner->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-PRIVATE-INV-002', 'title' => 'Private invoice',
            'amount' => 500, 'subtotal' => 500, 'status' => 'unpaid',
        ]);

        $this->withToken($otherToken)->getJson('/api/v1/my/invoices/'.$invoice->id)->assertNotFound();
        $this->withToken($otherToken)->getJson('/api/v1/my/invoices')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    private function studentWithToken(): array
    {
        $student = User::factory()->create(['role' => 'student']);
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Invoice test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'name' => 'Invoice API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$student, $plain];
    }
}
