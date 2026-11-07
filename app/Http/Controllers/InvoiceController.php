<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function accountantIndex(Request $request): View
    {
        $branchId = $request->user()->branch_id;
        abort_unless($branchId && \App\Models\Branch::whereKey($branchId)->where('is_active', true)->exists(), 403, 'No active branch is assigned to this account.');

        $invoices = Invoice::with(['user', 'course', 'batch', 'payments', 'refunds'])
            ->where(function ($query) use ($branchId): void {
                $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                    ->orWhere(function ($unbatched) use ($branchId): void {
                        $unbatched->whereNull('batch_id')
                            ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId));
                    });
            })->latest()->paginate(25);
        $students = User::query()->where('role', 'student')
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('enrollments.batch', fn ($batch) => $batch->where('branch_id', $branchId));
            })->orderBy('name')->get();
        $courses = Course::query()->whereHas('batches', fn ($batch) => $batch->where('branch_id', $branchId))
            ->orderBy('title')->get();

        return view('admin.invoices.index', compact('invoices', 'students', 'courses'));
    }

    public function adminIndex(): View
    {
        $invoices = Invoice::with(['user', 'course', 'batch', 'payments', 'refunds'])
            ->latest()
            ->paginate(25);
        $students = User::query()->where('role', 'student')->orderBy('name')->get();
        $courses = Course::query()->orderBy('title')->get();

        return view('admin.invoices.index', compact('invoices', 'students', 'courses'));
    }

    public function studentIndex(Request $request): View
    {
        $invoices = Invoice::with(['course', 'batch', 'payments.receiver', 'refunds'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('student.invoices.index', compact('invoices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isAccountant = $request->user()->isAccountant();
        $branchId = $request->user()->branch_id;
        if ($isAccountant) {
            abort_unless($branchId && \App\Models\Branch::whereKey($branchId)->where('is_active', true)->exists(), 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        $studentQuery = User::whereKey($validated['user_id'])->where('role', 'student');
        if ($isAccountant) {
            $studentQuery->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)
                    ->orWhereHas('enrollments.batch', fn ($batch) => $batch->where('branch_id', $branchId));
            });
        }
        abort_unless($studentQuery->exists(), 422, 'Student is not assigned to your branch.');

        if ($isAccountant && ! empty($validated['batch_id'])) {
            $batch = \App\Models\Batch::whereKey($validated['batch_id'])
                ->where('branch_id', $branchId)
                ->first();
            abort_unless($batch, 422, 'Batch is outside your branch.');

            $studentBelongsToBatchScope = User::whereKey($validated['user_id'])
                ->where('role', 'student')
                ->where(function ($query) use ($branchId, $batch): void {
                    $query->where('branch_id', $branchId)
                        ->orWhereHas('enrollments', fn ($enrollments) => $enrollments
                            ->where('batch_id', $batch->id)
                            ->whereIn('status', ['active', 'completed']));
                })->exists();

            abort_unless($studentBelongsToBatchScope, 422, 'Student is not assigned to this branch or enrolled in the selected batch.');
        }
        if (! empty($validated['batch_id']) && ! empty($validated['course_id'])) {
            abort_unless(
                \App\Models\Batch::whereKey($validated['batch_id'])->where('course_id', $validated['course_id'])->exists(),
                422
            );
        }

        $subtotal = round((float) $validated['amount'], 2);
        $discountType = $validated['discount_type'] ?? null;
        $discountValue = round((float) ($validated['discount_value'] ?? 0), 2);
        if (! $discountType && $discountValue > 0) {
            return back()->withErrors(['discount_type' => 'Choose a discount type before entering a discount value.'])->withInput();
        }
        if ($discountType === 'percentage' && $discountValue > 100) {
            return back()->withErrors(['discount_value' => 'Percentage discount cannot exceed 100%.'])->withInput();
        }
        $discountAmount = match ($discountType) {
            'fixed' => $discountValue,
            'percentage' => round($subtotal * $discountValue / 100, 2),
            default => 0,
        };
        if ($discountAmount > $subtotal) {
            return back()->withErrors(['discount_value' => 'Discount cannot exceed the invoice subtotal.'])->withInput();
        }
        $netAmount = round($subtotal - $discountAmount, 2);
        if ($netAmount <= 0) {
            return back()->withErrors(['discount_value' => 'The final invoice amount must be greater than zero.'])->withInput();
        }

        Invoice::create([
            ...$validated,
            'amount' => $netAmount,
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'created_by' => $request->user()->id,
            'invoice_number' => 'AEC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => 'unpaid',
        ]);

        return back()->with('success', 'Invoice created successfully.');
    }

    public function receivePayment(Invoice $invoice, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['required', 'date'],
        ]);

        if ($request->user()->isAccountant()) {
            $branchId = $request->user()->branch_id;
            abort_unless($branchId && Invoice::whereKey($invoice->id)->where(function ($query) use ($branchId): void {
                $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                    ->orWhere(function ($unbatched) use ($branchId): void {
                        $unbatched->whereNull('batch_id')
                            ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId));
                    });
            })->exists(), 404);
        }

        $result = DB::transaction(function () use ($invoice, $validated, $request): string {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $refunded = (float) $lockedInvoice->refunds()->where('status', 'approved')->sum('amount');
            $paid = max(0, (float) $lockedInvoice->payments()->sum('amount') - $refunded);
            $remaining = round((float) $lockedInvoice->amount - $paid, 2);
            $amount = round((float) $validated['amount'], 2);

            if ($lockedInvoice->status === 'cancelled' || $remaining <= 0) {
                return 'closed';
            }

            if ($amount > $remaining) {
                return 'excess';
            }

            InvoicePayment::create([
                'invoice_id' => $lockedInvoice->id,
                'received_by' => $request->user()->id,
                'amount' => $amount,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
                'paid_at' => $validated['paid_at'],
            ]);

            $newPaid = $paid + $amount;
            $lockedInvoice->update(['status' => $newPaid >= (float) $lockedInvoice->amount ? 'paid' : 'partial']);

            return 'recorded';
        });

        if ($result === 'excess') {
            return back()->withErrors(['amount' => 'Payment cannot exceed the outstanding invoice balance.']);
        }
        if ($result === 'closed') {
            return back()->with('error', 'This invoice is already settled or cancelled.');
        }

        return back()->with('success', 'Payment receipt recorded successfully.');
    }

    public function receipt(InvoicePayment $payment, Request $request): View
    {
        $payment->load(['invoice.user', 'invoice.course', 'invoice.batch', 'receiver']);

        if ($request->user()->isAccountant()) {
            $branchId = $request->user()->branch_id;
            abort_unless($branchId && \App\Models\Branch::whereKey($branchId)->where('is_active', true)->exists(), 403);
            $invoice = $payment->invoice;
            $inScope = $invoice && Invoice::whereKey($invoice->id)->where(function ($query) use ($branchId): void {
                $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                    ->orWhere(function ($unbatched) use ($branchId): void {
                        $unbatched->whereNull('batch_id')
                            ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId));
                    });
            })->exists();
            abort_unless($inScope, 404);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        return view('admin.invoices.receipt', compact('payment'));
    }
}
