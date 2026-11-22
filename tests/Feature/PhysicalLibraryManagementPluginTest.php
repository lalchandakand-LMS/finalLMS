<?php

declare(strict_types=1);

namespace Tests\Feature;

use Aloms\LibraryManagement\LibraryBook;
use Aloms\LibraryManagement\LibraryController;
use Aloms\LibraryManagement\LibraryLoan;
use App\Models\Branch;
use App\Models\PluginInstallation;
use App\Models\User;
use Tests\Support\RefreshDatabaseWithPluginMigrations;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

final class PhysicalLibraryManagementPluginTest extends TestCase
{
    use RefreshDatabaseWithPluginMigrations;

    protected function pluginMigrationPaths(): array
    {
        return [base_path('plugins/aec/library-management/1.0.0/migrations')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $plugin = new PluginInstallation();
        $plugin->forceFill([
            'plugin_key' => 'aec.library-management',
            'name' => 'Physical Library Management',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => ['settings_schema' => []],
        ])->save();
        View::addNamespace('library-management', base_path('plugins/aec/library-management/1.0.0/resources/views'));
        require_once base_path('plugins/aec/library-management/1.0.0/src/LibraryBook.php');
        require_once base_path('plugins/aec/library-management/1.0.0/src/LibraryLoan.php');
        require_once base_path('plugins/aec/library-management/1.0.0/src/LibraryController.php');
        Route::get('/__library', [LibraryController::class, 'index'])->name('branch.library.index');
        Route::post('/__library/books', [LibraryController::class, 'storeBook'])->name('branch.library.books.store');
        Route::post('/__library/loans', [LibraryController::class, 'issueLoan'])->name('branch.library.loans.issue');
        Route::post('/__library/loans/{loan}/return', [LibraryController::class, 'returnLoan'])->name('branch.library.loans.return');
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_branch_manager_is_scoped_to_books_and_cannot_issue_other_branch_inventory(): void
    {
        $local = Branch::query()->create(['name' => 'Library Local', 'slug' => 'library-local', 'is_active' => true]);
        $remote = Branch::query()->create(['name' => 'Library Remote', 'slug' => 'library-remote', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $local->id]);
        $localBook = LibraryBook::query()->create(['branch_id' => $local->id, 'isbn' => 'ISBN-LOCAL-01', 'title' => 'Local Book', 'author' => 'Author', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 1]);
        $remoteBook = LibraryBook::query()->create(['branch_id' => $remote->id, 'isbn' => 'ISBN-REMOTE-01', 'title' => 'Remote Book', 'author' => 'Author', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 1]);

        $this->actingAs($manager)->get('/__library')->assertOk()->assertSee('Local Book')->assertDontSee('Remote Book');
        $this->actingAs($manager)->post('/__library/loans', ['library_book_id' => $remoteBook->id, 'borrower_name' => 'Borrower', 'borrower_code' => 'STU-1', 'due_at' => now()->addDays(7)->toDateString()])->assertNotFound();
        $this->actingAs($manager)->post('/__library/loans', ['library_book_id' => $localBook->id, 'borrower_name' => 'Borrower', 'borrower_code' => 'STU-1', 'due_at' => now()->addDays(7)->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('library_books', ['id' => $localBook->id, 'available_copies' => 0]);
        $this->assertDatabaseHas('library_loans', ['library_book_id' => $localBook->id, 'status' => 'borrowed']);
    }

    public function test_same_isbn_can_be_stocked_at_multiple_branches_but_not_twice_in_one_branch(): void
    {
        $first = Branch::query()->create(['name' => 'Library East', 'slug' => 'library-east', 'is_active' => true]);
        $second = Branch::query()->create(['name' => 'Library West', 'slug' => 'library-west', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'isbn' => '9780000000123',
            'title' => 'English Grammar',
            'author' => 'A. Writer',
            'category' => 'English',
            'total_copies' => 3,
        ];

        $this->actingAs($admin)->post('/__library/books', $payload + ['branch_id' => $first->id])->assertRedirect();
        $this->actingAs($admin)->post('/__library/books', $payload + ['branch_id' => $second->id])->assertRedirect();
        $this->assertDatabaseCount('library_books', 2);

        $this->actingAs($admin)->post('/__library/books', $payload + ['branch_id' => $first->id])
            ->assertSessionHasErrors('isbn');
        $this->assertDatabaseCount('library_books', 2);
    }

    public function test_return_is_idempotent_and_restores_one_copy_with_overdue_fine(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = LibraryBook::query()->create(['isbn' => 'ISBN-RETURN-01', 'title' => 'Return Book', 'author' => 'Author', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 0]);
        $loan = LibraryLoan::query()->create(['library_book_id' => $book->id, 'borrower_name' => 'Borrower', 'borrower_code' => 'STU-2', 'borrowed_at' => now()->subDays(20)->toDateString(), 'due_at' => now()->subDays(5)->toDateString(), 'status' => 'borrowed', 'fine_amount' => 0]);

        $this->actingAs($admin)->post('/__library/loans/'.$loan->id.'/return')->assertRedirect();
        $this->assertDatabaseHas('library_loans', ['id' => $loan->id, 'status' => 'returned', 'fine_amount' => 50]);
        $this->assertDatabaseHas('library_books', ['id' => $book->id, 'available_copies' => 1]);
        $this->actingAs($admin)->post('/__library/loans/'.$loan->id.'/return')->assertRedirect();
        $this->assertDatabaseHas('library_books', ['id' => $book->id, 'available_copies' => 1]);
    }

    public function test_issue_is_rejected_when_inventory_is_exhausted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = LibraryBook::query()->create(['isbn' => 'ISBN-EMPTY-01', 'title' => 'No Copies', 'author' => 'Author', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 0]);
        $this->actingAs($admin)->post('/__library/loans', ['library_book_id' => $book->id, 'borrower_name' => 'Borrower', 'borrower_code' => 'STU-3', 'due_at' => now()->addDays(7)->toDateString()])->assertStatus(409);
        $this->assertDatabaseCount('library_loans', 0);
    }
}
