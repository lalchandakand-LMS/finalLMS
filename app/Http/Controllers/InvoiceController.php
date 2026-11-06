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
    public function adminIndex(): View
    {
        $invoices = Invoice::with(['user', 'course', 'batch', 'payments'])
            ->latest()
            ->paginate(25);
        $students = User::query()->where('role', 'student')->orderBy('name')->get();
        $courses = Course::query()->orderBy('title')->get();

        return view('admin.invoices.index', compact('invoices', 'students', 'courses'));
    }

    public function studentIndex(Request $request): View
    {
        $invoices = Invoice::with(['course', 'batch', 'payments.receiver'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('student.invoices.index', compact('invoices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'due_date' => ['nullable', 'date'],
        ]);

        abort_unless(User::whereKey($validated['user_id'])->where('role', 'student')->exists(), 422);

        if (! empty($validated['batch_id']) && ! empty($validated['course_id'])) {
            abort_unless(
                \App\Models\Batch::whereKey($validated['batch_id'])->where('course_id', $validated['course_id'])->exists(),
                422
            );
        }

        Invoice::create([
            ...$validated,
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

        $result = DB::transaction(function () use ($invoice, $validated, $request): string {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $paid = (float) $lockedInvoice->payments()->sum('amount');
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
}
