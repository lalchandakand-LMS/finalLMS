<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentReconciliation;
use App\Services\Payments\BkashReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentReconciliationController extends Controller
{
    public function index(): View
    {
        return view('admin.payment-reconciliation.index', [
            'records' => PaymentReconciliation::with('payment.user')->latest('checked_at')->paginate(50),
            'pending' => Payment::where('gateway_provider', 'bkash')
                ->whereNotNull('gateway_payment_id')
                ->whereIn('status', ['pending', 'approved', 'rejected'])
                ->count(),
        ]);
    }

    public function run(Request $request, BkashReconciliationService $reconciler): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'after_payment_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = Payment::query()
            ->where('gateway_provider', 'bkash')
            ->whereNotNull('gateway_payment_id')
            ->when($validated['payment_id'] ?? null, fn ($payments, $id) => $payments->whereKey($id))
            ->when(! empty($validated['after_payment_id']), fn ($payments) => $payments->where('id', '>', $validated['after_payment_id']))
            ->orderBy('id')
            ->limit(100);

        $count = 0;
        $lastId = (int) ($validated['after_payment_id'] ?? 0);
        foreach ($query->get() as $payment) {
            $lastId = $payment->id;
            $reconciler->reconcile($payment);
            $count++;
        }

        return back()->with('success', "{$count} bKash transaction(s) checked through payment ID {$lastId}. Enter this ID in the next run to continue.");
    }
}
