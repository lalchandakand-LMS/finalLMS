<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
                    ->whereIn('status', ['pending', 'approved'])->sum('amount');

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
                ->whereIn('status', ['pending', 'approved'])->sum('amount');
            $available = round((float) $payment->amount - $reserved, 2);
            $amount = round((float) $validated['amount'], 2);

            if ($amount > $available) {
                return 'excess';
            }

            InvoiceRefund::create([
                'invoice_id' => $payment->invoice_id,
                'invoice_payment_id' => $payment->id,
                'requested_by' => $request->user()->id,
                'amount' => $amount,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);

            return 'created';
        });

        if ($result === 'excess') {
            return back()->withErrors(['amount' => 'Refund cannot exceed the unrefunded amount of the selected payment.'])->withInput();
        }

        return back()->with('success', 'Refund request recorded and sent for admin approval.');
    }

    public function approve(InvoiceRefund $refund, Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $result = DB::transaction(function () use ($refund, $request): string {
            $locked = InvoiceRefund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'pending') {
                return 'closed';
            }

            $payment = InvoicePayment::query()->lockForUpdate()->findOrFail($locked->invoice_payment_id);
            $otherReserved = (float) InvoiceRefund::where('invoice_payment_id', $payment->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereKeyNot($locked->id)
                ->sum('amount');
            if (round($otherReserved + (float) $locked->amount, 2) > (float) $payment->amount) {
                return 'excess';
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            return 'approved';
        });

        if ($result === 'closed') {
            return back()->with('error', 'Only pending refund requests can be approved.');
        }
        if ($result === 'excess') {
            return back()->with('error', 'This refund would exceed the payment balance.');
        }

        return back()->with('success', 'Refund approved and recorded.');
    }

    public function reject(InvoiceRefund $refund, Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($refund->status !== 'pending') {
            return back()->with('error', 'Only pending refund requests can be rejected.');
        }

        $refund->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Refund request rejected. The payment record remains unchanged.');
    }
}
