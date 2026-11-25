<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Library\LibraryBook;
use App\Modules\Library\LibraryLoan;
use App\Modules\Library\LibraryReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryReservationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reserve_an_available_copy_and_duplicate_active_reservation_is_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $book = LibraryBook::query()->create(['isbn' => 'RES-001', 'title' => 'Grammar', 'author' => 'Teacher', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 1]);

        $this->actingAs($student)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertRedirect();
        $reservation = LibraryReservation::query()->firstOrFail();
        $this->assertSame('ready_for_pickup', $reservation->status);
        $this->assertNotNull($reservation->expires_at);

        $this->actingAs($student)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertStatus(409);
        $this->assertDatabaseCount('library_reservations', 1);
    }

    public function test_waiting_list_is_promoted_in_fifo_order_when_a_book_is_returned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = User::factory()->create(['role' => 'student']);
        $second = User::factory()->create(['role' => 'student']);
        $book = LibraryBook::query()->create(['isbn' => 'RES-002', 'title' => 'Speaking', 'author' => 'Teacher', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 0]);
        $loan = LibraryLoan::query()->create([
            'library_book_id' => $book->id, 'branch_id' => null, 'borrower_user_id' => null,
            'borrower_name' => 'Guest', 'borrower_code' => 'GUEST-1',
            'borrowed_at' => now()->subDays(5)->toDateString(), 'due_at' => now()->addDays(2)->toDateString(),
            'status' => 'borrowed', 'fine_amount' => 0,
        ]);

        $this->actingAs($first)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertRedirect();
        $this->actingAs($second)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertRedirect();
        $this->assertSame('waiting', LibraryReservation::query()->where('user_id', $first->id)->value('status'));
        $this->assertSame(1, LibraryReservation::query()->where('user_id', $second->id)->value('queue_position'));

        $this->actingAs($admin)->post(route('admin.library.loans.return', $loan->id))->assertRedirect();
        $firstReservation = LibraryReservation::query()->where('user_id', $first->id)->firstOrFail();
        $secondReservation = LibraryReservation::query()->where('user_id', $second->id)->firstOrFail();
        $this->assertSame('ready_for_pickup', $firstReservation->status);
        $this->assertNotNull($firstReservation->expires_at);
        $this->assertSame('waiting', $secondReservation->status);
        $this->assertSame(1, $secondReservation->queue_position);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_ready_reservation_can_only_be_claimed_for_its_registered_borrower(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $book = LibraryBook::query()->create(['isbn' => 'RES-003', 'title' => 'Writing', 'author' => 'Teacher', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 1]);
        $this->actingAs($owner)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.library.loans.issue'), [
            'library_book_id' => $book->id, 'borrower_user_id' => $other->id, 'due_at' => now()->addDays(7)->toDateString(),
        ])->assertStatus(409);
        $this->assertSame(1, $book->fresh()->available_copies);

        $this->actingAs($admin)->post(route('admin.library.loans.issue'), [
            'library_book_id' => $book->id, 'borrower_user_id' => $owner->id, 'due_at' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();
        $this->assertSame('fulfilled', LibraryReservation::query()->firstOrFail()->status);
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_student_can_cancel_own_reservation_but_not_another_users_reservation(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $book = LibraryBook::query()->create(['isbn' => 'RES-004', 'title' => 'Reading', 'author' => 'Teacher', 'category' => 'English', 'total_copies' => 1, 'available_copies' => 1]);
        $this->actingAs($owner)->post(route('student.library.reservations.store'), ['library_book_id' => $book->id])->assertRedirect();
        $reservation = LibraryReservation::query()->firstOrFail();

        $this->actingAs($other)->post(route('student.library.reservations.cancel', $reservation->id))->assertNotFound();
        $this->actingAs($owner)->post(route('student.library.reservations.cancel', $reservation->id))->assertRedirect();
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }
}
