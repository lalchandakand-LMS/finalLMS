<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentAuditEvent;
use App\Models\User;
use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    public function accountantIndex(Request $request): View
    {
        $branchId = $request->user()->branch_id;
        abort_unless($this->activeBranchExists($branchId), 403, 'No active branch is assigned to this account.');

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
        $invoices = Invoice::with(['user', 'course', 'batch', 'payments', 'refunds'])->latest()->paginate(25);
        $students = User::query()->where('role', 'student')->orderBy('name')->get();
        $courses = Course::query()->orderBy('title')->get();

        return view('admin.invoices.index', compact('invoices', 'students', 'courses'));
    }

    public function studentIndex(Request $request): View
    {
        $invoices = Invoice::with(['course', 'batch', 'payments.receiver', 'refunds'])
            ->where('user_id', $request->user()->id)->latest()->paginate(20);

        return view('student.invoices.index', compact('invoices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isAccountant = $request->user()->isAccountant();
        $branchId = $request->user()->branch_id;
        if ($isAccountant) {
            abort_unless($this->activeBranchExists($branchId), 403, 'No active branch is assigned to this account.');
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
            $batch = \App\Models\Batch::whereKey($validated['batch_id'])->where('branch_id', $branchId)->first();
            abort_unless($batch, 422, 'Batch is outside your branch.');

            $studentBelongsToBatchScope = User::whereKey($validated['user_id'])
                ->where('role', 'student')
                ->where(function ($query) use ($branchId, $batch): void {
                    $query->where('branch_id', $branchId)
                        ->orWhereHas('enrollments', fn ($enrollments) => $enrollments
                            ->where('batch_id', $batch->id)->whereIn('status', ['active', 'completed']));
                })->exists();
            abort_unless($studentBelongsToBatchScope, 422, 'Student is not assigned to this branch or enrolled in the selected batch.');
        }
        if (! empty($validated['batch_id']) && ! empty($validated['course_id'])) {
            abort_unless(\App\Models\Batch::whereKey($validated['batch_id'])->where('course_id', $validated['course_id'])->exists(), 422);
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

    public function receivePayment(Invoice $invoice, Request $request, BkashCheckoutService $bkash): RedirectResponse
    {
        $isAccountant = $request->user()->isAccountant();
        $branchId = $request->user()->branch_id;
        if ($isAccountant) {
            abort_unless($this->activeBranchExists($branchId), 403, 'No active branch is assigned to this account.');
            abort_unless($this->invoiceBelongsToBranch($invoice, (int) $branchId), 404);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'method' => ['required', 'string', 'max:40'],
            'gateway_payment_id' => ['nullable', 'string', 'max:120', 'required_if:method,bkash', 'unique:invoice_payments,gateway_payment_id'],
            'gateway_transaction_id' => ['nullable', 'string', 'max:120', 'required_if:method,bkash', 'unique:invoice_payments,gateway_transaction_id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['required', 'date'],
        ]);

        if ($validated['method'] === 'bkash') {
            $alreadyRecorded = Payment::query()
                ->where('gateway_provider', 'bkash')
                ->where(function ($query) use ($validated): void {
                    $query->where('gateway_payment_id', $validated['gateway_payment_id'])
                        ->orWhere('gateway_transaction_id', $validated['gateway_transaction_id']);
                })
                ->exists();

            if ($alreadyRecorded) {
                return back()->withErrors([
                    'gateway_payment_id' => 'This bKash transaction is already recorded against another LMS payment.',
                ])->withInput();
            }

            try {
                if (! config('services.bkash.enabled') || ! config('services.bkash.verify_ssl', true)) {
                    throw new \RuntimeException('bKash server-side verification is not enabled with verified TLS.');
                }

                $verified = $bkash->queryPayment($bkash->grantToken(), $validated['gateway_payment_id']);
                $matches = strtolower((string) ($verified['transactionStatus'] ?? '')) === 'completed'
                    && ($verified['paymentID'] ?? null) === $validated['gateway_payment_id']
                    && ($verified['trxID'] ?? null) === $validated['gateway_transaction_id']
                    && round((float) ($verified['amount'] ?? -1), 2) === round((float) $validated['amount'], 2)
                    && strtoupper((string) ($verified['currency'] ?? '')) === 'BDT'
                    && ($verified['merchantInvoiceNumber'] ?? null) === $invoice->invoice_number;

                if (! $matches) {
                    return back()->withErrors([
                        'gateway_payment_id' => 'bKash payment verification did not match this invoice, amount, currency or transaction ID.',
                    ])->withInput();
                }
            } catch (Throwable) {
                return back()->withErrors([
                    'gateway_payment_id' => 'bKash could not independently verify this transaction. No invoice payment was recorded.',
                ])->withInput();
            }
        }

        $result = DB::transaction(function () use ($invoice, $validated, $request, $isAccountant, $branchId): string {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($isAccountant) {
                abort_unless($this->activeBranchExists($branchId) && $this->invoiceBelongsToBranch($lockedInvoice, (int) $branchId), 404);
            }

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

            $gatewayEvent = null;
            if ($validated['method'] === 'bkash') {
                $eventKey = hash('sha256', 'bkash:payment:'.$validated['gateway_payment_id'].':'.$validated['gateway_transaction_id']);
                $gatewayEvent = PaymentGatewayEvent::firstOrCreate(
                    ['event_key' => $eventKey],
                    [
                        'provider' => 'bkash',
                        'gateway_payment_id' => $validated['gateway_payment_id'],
                        'gateway_transaction_id' => $validated['gateway_transaction_id'],
                        'event_type' => 'invoice.payment.verified',
                        'verification_status' => 'verified',
                        'processing_status' => 'processing',
                        'payload_hash' => hash('sha256', json_encode([
                            'paymentID' => $validated['gateway_payment_id'],
                            'trxID' => $validated['gateway_transaction_id'],
                            'amount' => $validated['amount'],
                            'invoice' => $lockedInvoice->invoice_number,
                        ], JSON_THROW_ON_ERROR)),
                        'verified_payload' => [
                            'paymentID' => $validated['gateway_payment_id'],
                            'trxID' => $validated['gateway_transaction_id'],
                            'amount' => $validated['amount'],
                            'merchantInvoiceNumber' => $lockedInvoice->invoice_number,
                        ],
                        'received_at' => now(),
                        'verified_at' => now(),
                    ]
                );

                if (! $gatewayEvent->wasRecentlyCreated || $gatewayEvent->payment_id) {
                    return 'duplicate_gateway';
                }
            }

            $receipt = InvoicePayment::create([
                'invoice_id' => $lockedInvoice->id,
                'received_by' => $request->user()->id,
                'amount' => $amount,
                'method' => $validated['method'],
                'gateway_provider' => $validated['method'] === 'bkash' ? 'bkash' : null,
                'gateway_payment_id' => $validated['gateway_payment_id'] ?? null,
                'gateway_transaction_id' => $validated['gateway_transaction_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,

                'paid_at' => $validated['paid_at'],
            ]);

            if ($gatewayEvent) {
                $gatewayEvent->update([
                    'invoice_payment_id' => $receipt->id,
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                ]);
            }

            PaymentAuditEvent::create([
                'invoice_payment_id' => $receipt->id,
                'actor_id' => $request->user()->id,
                'provider' => $receipt->gateway_provider,
                'event' => 'invoice_payment_recorded',
                'source' => 'admin_invoice',
                'after_state' => [
                    'amount' => $receipt->amount,
                    'method' => $receipt->method,
                    'gateway_payment_id' => $receipt->gateway_payment_id,
                    'gateway_transaction_id' => $receipt->gateway_transaction_id,
                ],
                'ip_address' => $request->ip(),
            ]);

            $newPaid = $paid + $amount;
            $lockedInvoice->update(['status' => $newPaid >= (float) $lockedInvoice->amount ? 'paid' : 'partial']);

            return 'recorded';
        });

        if ($result === 'duplicate_gateway') {
            return back()->withErrors(['gateway_payment_id' => 'This bKash transaction has already been recorded in the LMS.'])->withInput();
        }
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
            abort_unless($this->activeBranchExists($branchId), 403, 'No active branch is assigned to this account.');
            abort_unless($payment->invoice && $this->invoiceBelongsToBranch($payment->invoice, (int) $branchId), 404);
        } else {
            abort_unless($request->user()->isAdmin(), 403);
        }

        return view('admin.invoices.receipt', compact('payment'));
    }

    private function activeBranchExists(?int $branchId): bool
    {
        return $branchId !== null && \App\Models\Branch::query()->whereKey($branchId)->where('is_active', true)->exists();
    }

    private function invoiceBelongsToBranch(Invoice $invoice, int $branchId): bool
    {
        return Invoice::query()->whereKey($invoice->id)->where(function ($query) use ($branchId): void {
            $query->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                ->orWhere(function ($unbatched) use ($branchId): void {
                    $unbatched->whereNull('batch_id')
                        ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId));
                });
        })->exists();
    }
}
