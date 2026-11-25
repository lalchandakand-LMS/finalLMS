<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Library\LibraryBook;
use App\Modules\Library\LibraryInventoryAdjustment;
use App\Modules\Library\LibraryLoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryLostDamagedBookTest extends TestCase
{
    use RefreshDatabase;

    private function createLoan(int $totalCopies = 2, int $availableCopies = 1): array
    {
        $book = LibraryBook::query()->create([
            'isbn' => 'COND-'.uniqid(), 'title' => 'Condition test', 'author' => 'Teacher',
            'category' => 'English', 'total_copies' => $totalCopies, 'available_copies' => $availableCopies,
        ]);
        $loan = LibraryLoan::query()->create([
            'library_book_id' => $book->id, 'branch_id' => null, 'borrower_user_id' => null,
            'borrower_name' => 'Guest', 'borrower_code' => 'GUEST-COND',
            'borrowed_at' => now()->subDays(3)->toDateString(), 'due_at' => now()->addDays(5)->toDateString(),
            'status' => 'borrowed', 'fine_amount' => 0,
        ]);
        return [$book, $loan];
    }

    public function test_lost_copy_is_retired_without_increasing_available_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$book, $loan] = $this->createLoan();

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), [
            'condition' => 'lost', 'condition_notes' => 'Borrower reported the book missing.',
        ])->assertRedirect();

        $this->assertSame('lost', $loan->fresh()->status);
        $this->assertSame(1, $book->fresh()->total_copies);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseHas('library_inventory_adjustments', [
            'library_loan_id' => $loan->id, 'condition' => 'lost', 'quantity' => 1,
        ]);
    }

    public function test_damaged_copy_is_retired_and_reason_is_kept_in_inventory_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$book, $loan] = $this->createLoan(3, 2);

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), [
            'condition' => 'damaged', 'condition_notes' => 'Pages are wet and unreadable.',
        ])->assertRedirect();

        $this->assertSame('damaged', $loan->fresh()->status);
        $this->assertSame(2, $book->fresh()->total_copies);
        $this->assertSame(2, $book->fresh()->available_copies);
        $this->assertDatabaseHas('library_inventory_adjustments', [
            'library_loan_id' => $loan->id, 'condition' => 'damaged',
            'notes' => 'Pages are wet and unreadable.',
        ]);
    }

    public function test_lost_or_damaged_copy_requires_a_reason_and_does_not_close_the_loan_without_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$book, $loan] = $this->createLoan();

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), [
            'condition' => 'damaged',
        ])->assertSessionHasErrors('condition_notes');

        $this->assertSame('borrowed', $loan->fresh()->status);
        $this->assertSame(2, $book->fresh()->total_copies);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseCount('library_inventory_adjustments', 0);
    }

    public function test_closed_lost_or_damaged_loan_cannot_be_processed_twice(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        [$book, $loan] = $this->createLoan();

        $payload = ['condition' => 'lost', 'condition_notes' => 'Missing after follow-up.'];
        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id), $payload)->assertRedirect();

        $this->assertSame(1, $book->fresh()->total_copies);
        $this->assertDatabaseCount('library_inventory_adjustments', 1);
    }
}
