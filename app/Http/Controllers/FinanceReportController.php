<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $accountant = $request->user()->isAccountant();
        $branchId = $request->user()->branch_id;

        if ($accountant) {
            abort_unless(
                $branchId && Branch::whereKey($branchId)->where('is_active', true)->exists(),
                403,
                'No active branch is assigned to this account.'
            );
        }

        $applyDates = static function ($query, string $column) use ($validated): void {
            if (! empty($validated['date_from'])) {
                $query->where($column, '>=', Carbon::parse($validated['date_from'])->startOfDay());
            }
            if (! empty($validated['date_to'])) {
                $query->where($column, '<=', Carbon::parse($validated['date_to'])->endOfDay());
            }
        };

        $scopeInvoices = static function ($query) use ($accountant, $branchId): void {
            if (! $accountant) {
                return;
            }

            $query->where(function ($invoices) use ($branchId): void {
                $invoices->whereHas('batch', fn ($batch) => $batch->where('branch_id', $branchId))
                    ->orWhere(function ($unbatched) use ($branchId): void {
                        $unbatched->whereNull('batch_id')
                            ->whereHas('user', fn ($user) => $user->where('branch_id', $branchId));
                    });
            });
        };

        $paymentsQuery = InvoicePayment::query()
            ->with(['invoice.user', 'invoice.batch.branch', 'receiver'])
            ->whereHas('invoice', $scopeInvoices);
        $applyDates($paymentsQuery, 'paid_at');

        $payments = (clone $paymentsQuery)->latest('paid_at')->paginate(50)->withQueryString();
        $collected = (clone $paymentsQuery)->sum('amount');
        $paymentCount = (clone $paymentsQuery)->count();
        $methodTotals = (clone $paymentsQuery)
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as transactions')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        $issuedQuery = Invoice::query();
        $scopeInvoices($issuedQuery);
        $applyDates($issuedQuery, 'created_at');
        $issuedInvoices = (clone $issuedQuery)->count();
        $issuedAmount = (clone $issuedQuery)->sum('amount');

        $outstandingInvoices = Invoice::query()
            ->withSum('payments', 'amount')
            ->whereIn('status', ['unpaid', 'partial']);
        $scopeInvoices($outstandingInvoices);
        $outstandingRows = $outstandingInvoices->get(['id', 'amount']);
        $outstanding = $outstandingRows->sum(
            fn (Invoice $invoice): float => max(0, (float) $invoice->amount - (float) ($invoice->payments_sum_amount ?? 0))
        );

        return view('admin.finance-reports.index', [
            'payments' => $payments,
            'collected' => (float) $collected,
            'paymentCount' => $paymentCount,
            'methodTotals' => $methodTotals,
            'issuedInvoices' => $issuedInvoices,
            'issuedAmount' => (float) $issuedAmount,
            'outstanding' => (float) $outstanding,
            'dateFrom' => $validated['date_from'] ?? '',
            'dateTo' => $validated['date_to'] ?? '',
            'accountant' => $accountant,
            'branch' => $accountant ? Branch::find($branchId) : null,
        ]);
    }
}
