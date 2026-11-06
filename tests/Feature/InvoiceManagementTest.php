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
}
