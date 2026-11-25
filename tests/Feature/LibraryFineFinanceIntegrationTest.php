<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Library\LibraryBook;
use App\Modules\Library\LibraryLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryFineFinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createOverdueLoan(?User $borrower): array
    {
        $book = LibraryBook::query()->create([
            'branch_id' => null, 'isbn' => 'FINE-'.uniqid(),
            'title' => 'Library fine test book', 'author' => 'AEC',
            'category' => 'English', 'total_copies' => 1, 'available_copies' => 0,
        ]);
        $loan = LibraryLoan::query()->create([
            'library_book_id' => $book->id, 'branch_id' => null,
            'borrower_user_id' => $borrower?->id,
            'borrower_name' => $borrower?->name ?? 'External visitor',
            'borrower_code' => $borrower ? 'USER-'.$borrower->id : 'VISITOR-FINE',
            'borrowed_at' => now()->subDays(20)->toDateString(),
            'due_at' => now()->subDays(4)->toDateString(),
            'status' => 'borrowed', 'fine_amount' => 0,
        ]);
        return [$book, $loan];
    }

    public function test_overdue_return_creates_one_native_finance_invoice_for_registered_borrower(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        Setting::setValue('library_daily_fine_bdt', 10);
        [$book, $loan] = $this->createOverdueLoan($student);

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), ['condition' => 'good'])->assertRedirect();

        $loan->refresh();
        $invoice = Invoice::query()->where('library_loan_id', $loan->id)->firstOrFail();
        $this->assertSame($student->id, $invoice->user_id);
        $this->assertSame($admin->id, $invoice->created_by);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame($loan->fine_amount, $invoice->amount);
        $this->assertSame(1, Invoice::query()->where('library_loan_id', $loan->id)->count());
        $this->assertSame('returned', $loan->status);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_guest_borrower_fine_remains_visible_without_creating_an_invalid_finance_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('library_daily_fine_bdt', 10);
        [$book, $loan] = $this->createOverdueLoan(null);

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), ['condition' => 'good'])->assertRedirect();

        $this->assertGreaterThan(0, (float) $loan->fresh()->fine_amount);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_on_time_return_does_not_create_a_zero_value_invoice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        [$book, $loan] = $this->createOverdueLoan($student);
        $loan->forceFill(['due_at' => now()->addDays(2)->toDateString()])->save();

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), ['condition' => 'good'])->assertRedirect();

        $this->assertSame('0.00', $loan->fresh()->fine_amount);
        $this->assertDatabaseCount('invoices', 0);
    }
}
