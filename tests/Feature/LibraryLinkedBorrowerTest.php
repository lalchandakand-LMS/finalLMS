<?php

namespace Tests\Feature;

use App\Modules\Library\LibraryBook;
use App\Modules\Library\LibraryLoan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryLinkedBorrowerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_a_book_to_a_registered_student_and_keep_the_account_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Registered Learner']);
        $book = LibraryBook::query()->create([
            'branch_id' => null,
            'isbn' => 'AEC-LIB-1001',
            'title' => 'English Grammar',
            'author' => 'A. Teacher',
            'category' => 'English',
            'total_copies' => 2,
            'available_copies' => 2,
        ]);

        $this->actingAs($admin)->post(route('admin.library.loans.issue'), [
            'library_book_id' => $book->id,
            'borrower_user_id' => $student->id,
            'due_at' => now()->addDays(14)->toDateString(),
        ])->assertRedirect();

        $loan = LibraryLoan::query()->firstOrFail();
        $this->assertSame($student->id, $loan->borrower_user_id);
        $this->assertSame($student->name, $loan->borrower_name);
        $this->assertSame('USER-'.$student->id, $loan->borrower_code);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertSame($student->id, $loan->borrower()->firstOrFail()->id);
    }

    public function test_library_rejects_linking_an_admin_or_unapproved_account_as_borrower(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $book = LibraryBook::query()->create([
            'branch_id' => null,
            'isbn' => 'AEC-LIB-1002',
            'title' => 'Basic English',
            'author' => 'A. Teacher',
            'category' => 'English',
            'total_copies' => 1,
            'available_copies' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.library.loans.issue'), [
            'library_book_id' => $book->id,
            'borrower_user_id' => $otherAdmin->id,
            'due_at' => now()->addDays(7)->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseCount('library_loans', 0);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_manual_external_borrower_flow_remains_supported(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = LibraryBook::query()->create([
            'branch_id' => null,
            'isbn' => 'AEC-LIB-1003',
            'title' => 'Spoken English',
            'author' => 'A. Teacher',
            'category' => 'English',
            'total_copies' => 1,
            'available_copies' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.library.loans.issue'), [
            'library_book_id' => $book->id,
            'borrower_name' => 'External Visitor',
            'borrower_code' => 'VISITOR-01',
            'due_at' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('library_loans', [
            'library_book_id' => $book->id,
            'borrower_user_id' => null,
            'borrower_name' => 'External Visitor',
            'borrower_code' => 'VISITOR-01',
        ]);
    }
}
