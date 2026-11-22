<?php

declare(strict_types=1);

namespace Aloms\LibraryManagement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PluginInstallation;
use App\Models\User;
use App\Services\Plugins\PluginSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $books = $this->scoped(LibraryBook::query(), $actor)->orderBy('title')->paginate(25, ['*'], 'book_page')->withQueryString();
        $loans = $this->scoped(LibraryLoan::query()->with('book'), $actor)->latest()->limit(60)->get();

        return view('library-management::index', compact('books', 'loans'));
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
            'borrower_name' => ['required', 'string', 'max:160'],
            'borrower_code' => ['required', 'string', 'max:60'],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $loan = DB::transaction(function () use ($actor, $data): LibraryLoan {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($data['library_book_id']);
            $this->assertRecordBranch($actor, $book->branch_id);
            abort_unless($book->available_copies > 0, 409, 'No copies of this book are currently available.');
            $book->decrement('available_copies');

            return LibraryLoan::query()->create([
                'library_book_id' => $book->id,
                'branch_id' => $book->branch_id,
                'borrower_name' => trim($data['borrower_name']),
                'borrower_code' => trim($data['borrower_code']),
                'borrowed_at' => now()->toDateString(),
                'due_at' => $data['due_at'],
                'status' => 'borrowed',
                'fine_amount' => 0,
            ]);
        }, 3);
        $this->audit($request, 'library.loan_issued', 'Issued library loan #'.$loan->id.'.');

        return back()->with('success', 'Book issued successfully.');
    }

    public function returnLoan(Request $request, int $loan, PluginSettingsManager $settings): RedirectResponse
    {
        $actor = $request->user();
        $this->assertBranchAssigned($actor);
        $plugin = PluginInstallation::query()->where('plugin_key', 'aec.library-management')->firstOrFail();
        $dailyFine = max(0, (int) ($settings->get($plugin, 'daily_fine_bdt') ?? 10));

        $returned = DB::transaction(function () use ($actor, $loan, $dailyFine): bool {
            $record = LibraryLoan::query()->lockForUpdate()->findOrFail($loan);
            $this->assertRecordBranch($actor, $record->branch_id);
            if ($record->status === 'returned') {
                return false;
            }
            $due = $record->due_at->copy()->startOfDay();
            $today = now()->startOfDay();
            $overdueDays = $today->greaterThan($due) ? (int) $due->diffInDays($today) : 0;
            $record->forceFill([
                'returned_at' => $today->toDateString(),
                'fine_amount' => round($overdueDays * $dailyFine, 2),
                'status' => 'returned',
            ])->save();

            $book = LibraryBook::query()->lockForUpdate()->find($record->library_book_id);
            if ($book) {
                abort_if($book->available_copies >= $book->total_copies, 409, 'Book inventory is inconsistent; return was not applied.');
                $book->increment('available_copies');
            }

            return true;
        }, 3);
        if ($returned) {
            $this->audit($request, 'library.loan_returned', 'Returned library loan #'.$loan.'.');
        }

        return back()->with('success', $returned ? 'Book return recorded.' : 'This loan was already returned.');
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
