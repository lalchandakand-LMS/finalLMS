<?php

declare(strict_types=1);

namespace App\Modules\Library;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $books = $this->scoped(LibraryBook::query(), $actor)->orderBy('title')->paginate(25, ['*'], 'book_page')->withQueryString();
        $loans = $this->scoped(LibraryLoan::query()->with(['book', 'borrower']), $actor)->latest()->limit(60)->get();
        $reservations = $this->scoped(LibraryReservation::query()->with(['book', 'user']), $actor)->latest()->limit(100)->get();
        $adjustments = $this->scoped(LibraryInventoryAdjustment::query()->with(['book', 'loan', 'adjustedBy']), $actor)->latest()->limit(100)->get();
        $borrowableUsers = User::query()
            ->whereIn('role', ['student', 'teacher', 'staff'])
            ->when($actor->isBranchManager(), fn ($query) => $query->where('branch_id', $actor->branch_id))
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'role', 'branch_id']);

        return view('modules.library.index', compact('books', 'loans', 'borrowableUsers', 'reservations', 'adjustments'));
    }

    public function storeBook(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'isbn' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/D', Rule::unique('library_books', 'isbn')->where(fn ($query) => $query->where('branch_id', $actor->isBranchManager() ? $actor->branch_id : $request->input('branch_id')))],
            'title' => ['required', 'string', 'max:200'],
            'author' => ['required', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'total_copies' => ['required', 'integer', 'min:1', 'max:10000'],
            'shelf_location' => ['nullable', 'string', 'max:60'],
            'branch_id' => [$actor->isBranchManager() ? 'nullable' : 'required', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);
        if ($actor->isBranchManager()) {
            $data['branch_id'] = $actor->branch_id;
        } elseif (! empty($data['branch_id'])) {
            abort_unless(DB::table('branches')->where('id', $data['branch_id'])->where('is_active', true)->exists(), 422, 'Select an active branch.');
        }
        $data['available_copies'] = $data['total_copies'];
        $book = LibraryBook::query()->create($data);
        $this->audit($request, 'library.book_added', 'Added book '.$book->isbn.' to inventory.');

        return back()->with('success', 'Book added to library inventory.');
    }

    public function issueLoan(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'library_book_id' => ['required', 'integer', 'exists:library_books,id'],
            'borrower_user_id' => ['nullable', 'integer', 'required_without:borrower_name', 'exists:users,id'],
            'borrower_name' => ['nullable', 'required_without:borrower_user_id', 'string', 'max:160'],
            'borrower_code' => ['nullable', 'required_without:borrower_user_id', 'string', 'max:60'],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $loan = DB::transaction(function () use ($actor, $data): LibraryLoan {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($data['library_book_id']);
            $this->assertRecordBranch($actor, $book->branch_id);
            abort_unless($book->available_copies > 0, 409, 'No copies of this book are currently available.');

            $borrower = null;
            if (! empty($data['borrower_user_id'])) {
                $borrower = User::query()->whereKey($data['borrower_user_id'])
                    ->whereIn('role', ['student', 'teacher', 'staff'])
                    ->firstOrFail();
                if ($actor->isBranchManager()) {
                    abort_unless((int) $borrower->branch_id === (int) $actor->branch_id, 404, 'Borrower is not in your branch.');
                } elseif ($borrower->branch_id !== null && (int) $borrower->branch_id !== (int) $book->branch_id) {
                    abort(422, 'Borrower and book must belong to the same branch.');
                }
            }

            $this->expireAndPromote($book);
            $ready = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'ready_for_pickup')->where('expires_at', '>', now())->orderBy('created_at')->lockForUpdate()->first();
            $claim = $borrower ? LibraryReservation::query()->where('library_book_id', $book->id)->where('user_id', $borrower->id)->where('status', 'ready_for_pickup')->where('expires_at', '>', now())->lockForUpdate()->first() : null;
            abort_if($ready && (! $claim || $ready->id !== $claim->id), 409, 'This copy is reserved for the next borrower in the waiting list.');
            $held = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'ready_for_pickup')->where('expires_at', '>', now())->count();
            abort_unless($claim || $book->available_copies > $held, 409, 'All available copies are held for reservations.');
            $book->decrement('available_copies');
            if ($claim) {
                $claim->forceFill(['status' => 'fulfilled', 'fulfilled_at' => now(), 'expires_at' => null])->save();
            }

            return LibraryLoan::query()->create([
                'library_book_id' => $book->id,
                'branch_id' => $book->branch_id,
                'borrower_user_id' => $borrower?->id,
                'borrower_name' => $borrower?->name ?? trim($data['borrower_name']),
                'borrower_code' => $borrower ? 'USER-'.$borrower->id : trim($data['borrower_code']),
                'borrowed_at' => now()->toDateString(),
                'due_at' => $data['due_at'],
                'status' => 'borrowed',
                'fine_amount' => 0,
            ]);
        }, 3);
        $this->audit($request, 'library.loan_issued', 'Issued library loan #'.$loan->id.'.');

        return back()->with('success', 'Book issued successfully.');
    }

    public function studentReservations(Request $request): View
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['student', 'teacher', 'staff'], true), 403);
        $books = LibraryBook::query()->where(function ($query) use ($actor): void {
            $query->whereNull('branch_id')->orWhere('branch_id', $actor->branch_id);
        })->orderBy('title')->paginate(30);
        $reservations = LibraryReservation::query()->with('book')->where('user_id', $actor->id)->latest()->limit(100)->get();
        return view('modules.library.student-reservations', compact('books', 'reservations'));
    }

    public function storeReservation(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(in_array($actor->role, ['student', 'teacher', 'staff'], true), 403);
        $data = $request->validate(['library_book_id' => ['required', 'integer', 'exists:library_books,id']]);
        $reservation = DB::transaction(function () use ($actor, $data): LibraryReservation {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($data['library_book_id']);
            if ($book->branch_id !== null) abort_unless($actor->branch_id !== null && (int) $actor->branch_id === (int) $book->branch_id, 404);
            $this->expireAndPromote($book);
            $active = LibraryReservation::query()->where('library_book_id', $book->id)->where('user_id', $actor->id)->whereIn('status', ['waiting', 'ready_for_pickup'])->lockForUpdate()->first();
            abort_if($active, 409, 'You already have an active reservation for this book.');
            $held = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'ready_for_pickup')->where('expires_at', '>', now())->count();
            $free = max(0, (int) $book->available_copies - $held);
            $waiting = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'waiting')->count();
            $ready = $free > 0 && $waiting === 0;
            return LibraryReservation::query()->create([
                'library_book_id' => $book->id, 'branch_id' => $book->branch_id, 'user_id' => $actor->id,
                'status' => $ready ? 'ready_for_pickup' : 'waiting',
                'queue_position' => $ready ? null : $waiting + 1,
                'expires_at' => $ready ? now()->addDays(3) : null,
            ]);
        }, 3);
        $this->audit($request, 'library.reservation_created', 'Created library reservation #'.$reservation->id.'.');
        return back()->with('success', $reservation->status === 'ready_for_pickup' ? 'A copy is ready. Collect within 3 days.' : 'Added to the waiting list.');
    }

    public function cancelReservation(Request $request, int $reservation): RedirectResponse
    {
        $actor = $request->user();
        DB::transaction(function () use ($actor, $reservation): void {
            $record = LibraryReservation::query()->lockForUpdate()->findOrFail($reservation);
            abort_unless((int) $record->user_id === (int) $actor->id, 404);
            abort_unless(in_array($record->status, ['waiting', 'ready_for_pickup'], true), 409);
            $record->forceFill(['status' => 'cancelled', 'expires_at' => null])->save();
            $book = LibraryBook::query()->lockForUpdate()->find($record->library_book_id);
            if ($book) $this->expireAndPromote($book);
        }, 3);
        $this->audit($request, 'library.reservation_cancelled', 'Cancelled library reservation #'.$reservation.'.');
        return back()->with('success', 'Reservation cancelled.');
    }

    private function expireAndPromote(LibraryBook $book): void
    {
        LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'ready_for_pickup')->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['status' => 'expired', 'expires_at' => null, 'updated_at' => now()]);
        $held = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'ready_for_pickup')->where('expires_at', '>', now())->count();
        $slots = max(0, (int) $book->available_copies - $held);
        while ($slots-- > 0) {
            $next = LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if (! $next) break;
            $next->forceFill(['status' => 'ready_for_pickup', 'queue_position' => null, 'expires_at' => now()->addDays(3)])->save();
        }
        $position = 1;
        LibraryReservation::query()->where('library_book_id', $book->id)->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->get()->each(function (LibraryReservation $item) use (&$position): void {
            $item->forceFill(['queue_position' => $position++])->save();
        });
    }

    public function returnLoan(Request $request, int $loan): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $data = $request->validate([
            'condition' => ['nullable', Rule::in(['good', 'lost', 'damaged'])],
            'condition_notes' => ['nullable', 'string', 'max:1000', 'required_if:condition,lost,damaged'],
        ]);
        $condition = $data['condition'] ?? 'good';
        $dailyFine = max(0, (int) Setting::value('library_daily_fine_bdt', 10));

        $closed = DB::transaction(function () use ($actor, $loan, $dailyFine, $condition, $data): bool {
            $record = LibraryLoan::query()->lockForUpdate()->findOrFail($loan);
            $this->assertRecordBranch($actor, $record->branch_id);
            if ($record->status !== 'borrowed') {
                return false;
            }

            $due = $record->due_at->copy()->startOfDay();
            $today = now()->startOfDay();
            $overdueDays = $today->greaterThan($due) ? (int) $due->diffInDays($today) : 0;
            $record->forceFill([
                'returned_at' => $today->toDateString(),
                'fine_amount' => round($overdueDays * $dailyFine, 2),
                'status' => $condition === 'good' ? 'returned' : $condition,
            ])->save();

            $book = LibraryBook::query()->lockForUpdate()->find($record->library_book_id);
            if ($book) {
                if ($condition === 'good') {
                    abort_if($book->available_copies >= $book->total_copies, 409, 'Book inventory is inconsistent; return was not applied.');
                    $book->increment('available_copies');
                    $this->expireAndPromote($book->fresh());
                } else {
                    abort_if($book->total_copies <= $book->available_copies || $book->total_copies < 1, 409, 'Book inventory is inconsistent; the damaged or lost copy cannot be retired.');
                    $book->decrement('total_copies');
                    LibraryInventoryAdjustment::query()->create([
                        'library_book_id' => $book->id,
                        'library_loan_id' => $record->id,
                        'branch_id' => $record->branch_id,
                        'adjusted_by' => $actor->id,
                        'condition' => $condition,
                        'quantity' => 1,
                        'notes' => trim($data['condition_notes']),
                    ]);
                }
            }

            return true;
        }, 3);
        if ($closed) {
            $event = $condition === 'good' ? 'library.loan_returned' : 'library.inventory_copy_retired';
            $this->audit($request, $event, 'Closed library loan #'.$loan.'; copy condition: '.$condition.'.');
        }

        $message = match ($condition) {
            'lost' => 'Loan closed. The lost copy was removed from active inventory.',
            'damaged' => 'Loan closed. The damaged copy was removed from active inventory.',
            default => 'Book return recorded.',
        };

        return back()->with('success', $closed ? $message : 'This loan was already closed.');
    }

    private function scoped($query, User $actor)
    {
        return $actor->isBranchManager() ? $query->where('branch_id', $actor->branch_id) : $query;
    }

    private function assertBranchAssigned(User $actor): void
    {
        if ($actor->isBranchManager()) {
            abort_unless($actor->branch_id !== null
                && DB::table('branches')->where('id', $actor->branch_id)->where('is_active', true)->exists(), 403,
                'Branch manager must be assigned to an active branch.');
        }
    }

    private function assertRecordBranch(User $actor, ?int $branchId): void
    {
        if ($actor->isBranchManager()) {
            abort_unless((int) $actor->branch_id === (int) $branchId, 404);
        }
    }

    private function audit(Request $request, string $event, string $description): void
    {
        try {
            ActivityLog::query()->create([
                'user_id' => $request->user()?->id,
                'event' => $event,
                'description' => mb_substr($description, 0, 255),
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
