<?php

namespace Tests\\Feature;

use App\\Models\\Branch;
use App\\Models\\Invoice;
use App\\Models\\InvoicePayment;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finance_report_summarizes_payments_and_filters_by_payment_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $invoice = Invoice::create([
            'user_id' => $student->id,
            'created_by' => $admin->id,
            'invoice_number' => 'AEC-REPORT-001',
            'title' => 'Monthly tuition',
            'amount' => 3000,
            'status' => 'partial',
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $admin->id,
            'amount' => 1200,
            'method' => 'bkash',
            'paid_at' => now()->subDay(),
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'received_by' => $admin->id,
            'amount' => 500,
            'method' => 'cash',
            'paid_at' => now()->subDays(10),
        ]);

        $this->actingAs($admin)->get(route('admin.finance-reports.index', [
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk()
            ->assertSee('৳1,200.00')
            ->assertSee('৳2,500.00')
            ->assertDontSee('500.00');
    }

    public function test_accountant_finance_report_only_includes_its_own_branch_payments(): void
    {
        $branch = Branch::create(['name' => 'Dhaka Branch', 'slug' => 'dhaka-branch']);
        $otherBranch = Branch::create(['name' => 'Sylhet Branch', 'slug' => 'sylhet-branch']);
        $accountant = User::factory()->create(['role' => 'accountant', 'branch_id' => $branch->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $localStudent = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $remoteStudent = User::factory()->create(['role' => 'student', 'branch_id' => $otherBranch->id]);

        $localInvoice = Invoice::create([
            'user_id' => $localStudent->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-LOCAL-001', 'title' => 'Local fee',
            'amount' => 1000, 'status' => 'unpaid',
        ]);
        $remoteInvoice = Invoice::create([
            'user_id' => $remoteStudent->id, 'created_by' => $admin->id,
            'invoice_number' => 'AEC-REMOTE-001', 'title' => 'Remote fee',
            'amount' => 2000, 'status' => 'unpaid',
        ]);
        InvoicePayment::create([
            'invoice_id' => $localInvoice->id, 'received_by' => $admin->id,
            'amount' => 400, 'method' => 'cash', 'paid_at' => now(),
        ]);
        InvoicePayment::create([
            'invoice_id' => $remoteInvoice->id, 'received_by' => $admin->id,
            'amount' => 900, 'method' => 'cash', 'paid_at' => now(),
        ]);

        $this->actingAs($accountant)->get(route('accountant.finance-reports.index'))
            ->assertOk()
            ->assertSee('৳400.00')
            ->assertDontSee('৳900.00')
            ->assertSee('৳600.00')
            ->assertDontSee('Sylhet Branch');
    }

    public function test_accountant_without_an_active_branch_cannot_open_finance_report(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $this->actingAs($accountant)->get(route('accountant.finance-reports.index'))->assertForbidden();
    }
}
