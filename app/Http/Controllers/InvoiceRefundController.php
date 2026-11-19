<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Models\PaymentAuditEvent;
use App\Services\Payments\BkashRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class InvoiceRefundController extends Controller
{
    private function scopedInvoices(Request $request)
    {
        $query = Invoice::query();
        if ($request->user()->isAccountant()) {
            $branchId = $request->user()->branch_id;
            abort_unless($branchId && Branch::whereKey($branchId)->where('is_active', true)->exists(), 403);
            $query->where(function ($scope) use ($branchId): void {
                $scope->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                    ->orWhere(fn ($unbatched) => $unbatched->whereNull('batch_id')
                        ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId)));
            });
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $invoiceIds = $this->scopedInvoices($request)->pluck('id');
        $refunds = InvoiceRefund::with(['invoice.user', 'payment', 'requester', 'approver'])
            ->whereIn('invoice_id', $invoiceIds)
            ->latest()
            ->paginate(30);
        $payments = InvoicePayment::with('invoice.user')
            ->whereIn('invoice_id', $invoiceIds)
            ->latest('paid_at')
            ->get()
            ->filter(function (InvoicePayment $payment): bool {
                $reserved = (float) InvoiceRefund::where('invoice_payment_id', $payment->id)
                    ->whereIn('status', ['pending', 'processing', 'gateway_unknown', 'approved'])->sum('amount');

                return round((float) $payment->amount - $reserved, 2) > 0;
            });

        return view('admin.refunds.index', [
            'refunds' => $refunds,
            'payments' => $payments,
            'isAdmin' => $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_payment_id' => ['required', 'integer', 'exists:invoice_payments,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'method' => ['required', 'in:cash,bkash,nagad,bank,other'],
            'reference' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($request, $validated): string {
            $payment = InvoicePayment::query()->lockForUpdate()->with('invoice')->findOrFail($validated['invoice_payment_id']);
            abort_unless($this->scopedInvoices($request)->whereKey($payment->invoice_id)->exists(), 404);

            $reserved = (float) InvoiceRefund::where('invoice_payment_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'gateway_unknown', 'approved'])->sum('amount');
            $available = round((float) $payment->amount - $reserved, 2);
            $amount = round((float) $validated['amount'], 2);

            if ($amount > $available) {
                return 'excess';
            }

            $refund = InvoiceRefund::create([
                'invoice_id' => $payment->invoice_id,
                'invoice_payment_id' => $payment->id,
                'requested_by' => $request->user()->id,
                'amount' => $amount,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);

            PaymentAuditEvent::create([
                'invoice_payment_id' => $payment->id,
                'invoice_refund_id' => $refund->id,
                'actor_id' => $request->user()->id,
                'provider' => $payment->gateway_provider,
                'event' => 'refund_requested',
                'source' => 'admin_refund',
                'after_state' => ['status' => 'pending', 'amount' => $refund->amount, 'method' => $refund->method],
                'ip_address' => $request->ip(),
            ]);

            return 'created';
        });

        if ($result === 'excess') {
            return back()->withErrors(['amount' => 'Refund cannot exceed the unrefunded amount of the selected payment.'])->withInput();
        }

        return back()->with('success', 'Refund request recorded and sent for admin approval.');
    }

    public function approve(InvoiceRefund $refund, Request $request, BkashRefundService $bkashRefunds): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $result = DB::transaction(function () use ($refund, $request): string {
            $locked = InvoiceRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'pending') {
                return 'closed';
            }

            $payment = InvoicePayment::query()->lockForUpdate()->findOrFail($locked->invoice_payment_id);
            $otherReserved = (float) InvoiceRefund::where('invoice_payment_id', $payment->id)
                ->whereIn('status', ['pending', 'processing', 'gateway_unknown', 'approved'])
                ->where('id', '!=', $locked->id)
                ->sum('amount');
            if (round($otherReserved + (float) $locked->amount, 2) > (float) $payment->amount) {
                return 'excess';
            }

            if ($locked->method === 'bkash') {
                if ($payment->gateway_provider !== 'bkash'
                    || ! $payment->gateway_payment_id
                    || ! $payment->gateway_transaction_id) {
                    return 'missing_gateway_reference';
                }

                $locked->update([
                    'status' => 'processing',
                    'approved_by' => $request->user()->id,
                    'gateway_status' => 'refund_submitted',
                    'failure_reason' => null,
                ]);

                PaymentAuditEvent::create([
                    'invoice_payment_id' => $payment->id,
                    'invoice_refund_id' => $locked->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'refund_submitted',
                    'source' => 'admin_refund',
                    'before_state' => ['status' => 'pending'],
                    'after_state' => ['status' => 'processing', 'amount' => $locked->amount],
                    'ip_address' => $request->ip(),
                ]);

                return 'gateway_processing';
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'gateway_status' => 'manual_payout_confirmed',
                'processed_at' => now(),
            ]);

            $netPaid = (float) InvoicePayment::where('invoice_id', $locked->invoice_id)->sum('amount')
                - (float) InvoiceRefund::where('invoice_id', $locked->invoice_id)->where('status', 'approved')->sum('amount');
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);
            $invoice->update(['status' => $netPaid >= (float) $invoice->amount ? 'paid' : ($netPaid > 0 ? 'partial' : 'unpaid')]);

            PaymentAuditEvent::create([
                'invoice_payment_id' => $payment->id,
                'invoice_refund_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => 'manual_refund_confirmed',
                'source' => 'admin_refund',
                'before_state' => ['status' => 'pending'],
                'after_state' => ['status' => 'approved', 'amount' => $locked->amount],
                'ip_address' => $request->ip(),
            ]);

            return 'approved';
        });

        if ($result === 'closed') {
            return back()->with('error', 'Only pending refund requests can be approved.');
        }
        if ($result === 'excess') {
            return back()->with('error', 'This refund would exceed the payment balance.');
        }
        if ($result === 'missing_gateway_reference') {
            return back()->with('error', 'The original bKash payment ID and transaction ID must be recorded before a gateway refund can be requested.');
        }

        if ($result === 'gateway_processing') {
            $lockedRefund = $refund->fresh(['payment']);
            try {
                $gatewayResult = $bkashRefunds->refund(
                    (string) $lockedRefund->payment->gateway_payment_id,
                    (string) $lockedRefund->payment->gateway_transaction_id,
                    number_format((float) $lockedRefund->amount, 2, '.', ''),
                    (string) $lockedRefund->reason
                );

                DB::transaction(function () use ($lockedRefund, $gatewayResult, $request): void {
                    $current = InvoiceRefund::query()->lockForUpdate()->findOrFail($lockedRefund->id);
                    if ($current->status !== 'processing') {
                        return;
                    }
                    $current->update([
                        'status' => 'approved',
                        'gateway_refund_id' => $gatewayResult['refund_id'],
                        'gateway_status' => $gatewayResult['status'],
                        'processed_at' => now(),
                        'failure_reason' => null,
                    ]);
                    $netPaid = (float) InvoicePayment::where('invoice_id', $current->invoice_id)->sum('amount')
                        - (float) InvoiceRefund::where('invoice_id', $current->invoice_id)->where('status', 'approved')->sum('amount');
                    $invoice = Invoice::query()->lockForUpdate()->findOrFail($current->invoice_id);
                    $invoice->update(['status' => $netPaid >= (float) $invoice->amount ? 'paid' : ($netPaid > 0 ? 'partial' : 'unpaid')]);
                    PaymentAuditEvent::create([
                        'invoice_payment_id' => $current->invoice_payment_id,
                        'invoice_refund_id' => $current->id,
                        'actor_id' => $request->user()->id,
                        'provider' => 'bkash',
                        'event' => 'gateway_refund_verified',
                        'source' => 'bkash_refund',
                        'after_state' => ['status' => 'approved', 'gateway_refund_id' => $gatewayResult['refund_id'], 'gateway_status' => $gatewayResult['status']],
                        'ip_address' => $request->ip(),
                    ]);
                });

                return back()->with('success', 'bKash refund completed and independently verified with the gateway.');
            } catch (Throwable $exception) {
                InvoiceRefund::query()->whereKey($refund->id)->where('status', 'processing')->update([
                    'status' => 'gateway_unknown',
                    'gateway_status' => 'verification_unknown',
                    'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                PaymentAuditEvent::create([
                    'invoice_payment_id' => $refund->invoice_payment_id,
                    'invoice_refund_id' => $refund->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'gateway_refund_unverified',
                    'source' => 'bkash_refund',
                    'after_state' => ['status' => 'gateway_unknown'],
                    'ip_address' => $request->ip(),
                ]);

                return back()->with('error', 'bKash refund could not be verified. The amount remains reserved and must be reconciled before retrying.');
            }
        }

        return back()->with('success', 'Manual refund payout confirmed and recorded.');
    }

    public function reconcile(InvoiceRefund $refund, Request $request, BkashRefundService $bkashRefunds): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless(in_array($refund->status, ['gateway_unknown', 'processing'], true)
            && $refund->method === 'bkash'
            && ($refund->status !== 'processing' || $refund->created_at?->lte(now()->subMinutes(5))), 409);

        $payment = InvoicePayment::query()->findOrFail($refund->invoice_payment_id);
        abort_unless($payment->gateway_provider === 'bkash'
            && $payment->gateway_payment_id && $payment->gateway_transaction_id, 422);

        try {
            $gatewayResult = $bkashRefunds->verifyExisting(
                $payment->gateway_payment_id,
                $payment->gateway_transaction_id,
                number_format((float) $refund->amount, 2, '.', '')
            );

            DB::transaction(function () use ($refund, $gatewayResult, $request): void {
                $locked = InvoiceRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if (! in_array($locked->status, ['gateway_unknown', 'processing'], true)) {
                    return;
                }
                $locked->update([
                    'status' => 'approved',
                    'gateway_refund_id' => $gatewayResult['refund_id'],
                    'gateway_status' => $gatewayResult['status'],
                    'processed_at' => now(),
                    'failure_reason' => null,
                ]);
                $netPaid = (float) InvoicePayment::where('invoice_id', $locked->invoice_id)->sum('amount')
                    - (float) InvoiceRefund::where('invoice_id', $locked->invoice_id)->where('status', 'approved')->sum('amount');
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);
                $invoice->update(['status' => $netPaid >= (float) $invoice->amount ? 'paid' : ($netPaid > 0 ? 'partial' : 'unpaid')]);
                PaymentAuditEvent::create([
                    'invoice_payment_id' => $locked->invoice_payment_id,
                    'invoice_refund_id' => $locked->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'gateway_refund_reconciled',
                    'source' => 'admin_refund_reconciliation',
                    'after_state' => ['status' => 'approved', 'gateway_refund_id' => $gatewayResult['refund_id']],
                    'ip_address' => $request->ip(),
                ]);
            });

            return back()->with('success', 'Existing bKash refund independently verified and reconciled.');
        } catch (Throwable $exception) {
            DB::transaction(function () use ($refund, $request, $exception): void {
                $locked = InvoiceRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if (! in_array($locked->status, ['gateway_unknown', 'processing'], true)) {
                    return;
                }

                $locked->update([
                    'status' => 'gateway_unknown',
                    'gateway_status' => 'verification_unknown',
                    'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                PaymentAuditEvent::create([
                    'invoice_payment_id' => $locked->invoice_payment_id,
                    'invoice_refund_id' => $locked->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'gateway_refund_reconciliation_unconfirmed',
                    'source' => 'admin_refund_reconciliation',
                    'after_state' => ['status' => 'gateway_unknown'],
                    'ip_address' => $request->ip(),
                ]);
            });

            return back()->with('error', 'Gateway still does not confirm this refund. The reserved amount remains unchanged.');
        }
    }

    public function reject(InvoiceRefund $refund, Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $rejected = DB::transaction(function () use ($refund, $request): bool {
            $locked = InvoiceRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'pending') {
                return false;
            }

            $locked->update([
                'status' => 'rejected',
                'approved_by' => $request->user()->id,
                'processed_at' => now(),
            ]);
            PaymentAuditEvent::create([
                'invoice_payment_id' => $locked->invoice_payment_id,
                'invoice_refund_id' => $locked->id,
                'actor_id' => $request->user()->id,
                'event' => 'refund_rejected',
                'source' => 'admin_refund',
                'before_state' => ['status' => 'pending'],
                'after_state' => ['status' => 'rejected'],
                'ip_address' => $request->ip(),
            ]);

            return true;
        });

        if (! $rejected) {
            return back()->with('error', 'Only pending refund requests can be rejected.');
        }

        return back()->with('success', 'Refund request rejected. The payment record remains unchanged.');
    }
}
