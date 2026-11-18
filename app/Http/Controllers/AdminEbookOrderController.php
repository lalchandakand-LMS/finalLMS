<?php

namespace App\Http\Controllers;

use App\Models\DownloadPermission;
use App\Models\EbookRefund;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAuditEvent;
use App\Services\Payments\BkashRefundService;
use App\Services\Commerce\EbookOrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminEbookOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->whereHas('items', fn ($query) => $query->where('product_type', 'ebook'))
            ->with(['user', 'items.downloadPermission', 'refunds.processor', 'payments'])
            ->latest()
            ->paginate(25);

        return view('admin.ebooks.orders', compact('orders'));
    }

    public function proof(Order $order)
    {
        abort_unless($order->items()->where('product_type', 'ebook')->exists(), 404);
        abort_unless($order->payment_proof_path && Storage::disk('local')->exists($order->payment_proof_path), 404);

        return Storage::disk('local')->download($order->payment_proof_path, 'payment-proof', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Order $order, EbookOrderFulfillmentService $fulfillment): RedirectResponse
    {
        abort_unless($order->items()->where('product_type', 'ebook')->exists(), 404);
        $result = $fulfillment->fulfill($order);

        return match ($result) {
            'approved' => back()->with('success', __('Payment verified and private eBook access granted.')),
            'reviewed' => back()->with('info', __('This order has already been completed.')),
            'closed' => back()->with('error', __('Only pending orders can be approved.')),
            'gateway' => back()->with('error', __('Gateway orders must be completed by verified provider callback.')),
            default => back()->with('error', __('The eBook file or order item is invalid. No access was granted.')),
        };
    }

    public function reject(Order $order): RedirectResponse
    {
        abort_unless($order->items()->where('product_type', 'ebook')->exists(), 404);
        if ($order->payment_method === 'bkash' || $order->payments()->whereNotNull('gateway_provider')->exists()) {
            return back()->with('error', __('Gateway orders cannot be manually rejected. Reconcile them with the provider.'));
        }
        $cancelled = DB::transaction(function () use ($order): bool {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'pending') {
                return false;
            }
            $locked->update(['status' => 'cancelled']);

            return true;
        });

        return $cancelled
            ? back()->with('success', __('Order cancelled; no download access was granted.'))
            : back()->with('info', __('Only pending orders can be rejected.'));
    }

    public function revokePermission(Request $request, DownloadPermission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $revoked = DB::transaction(function () use ($permission, $request, $validated): bool {
            $locked = DownloadPermission::query()->lockForUpdate()->findOrFail($permission->id);
            if ($locked->revoked_at) {
                return false;
            }

            $locked->update([
                'revoked_at' => now(),
                'revoked_by' => $request->user()->id,
                'revocation_reason' => $validated['reason'],
            ]);

            return true;
        });

        return $revoked
            ? back()->with('success', __('Private eBook access revoked and recorded.'))
            : back()->with('info', __('This download permission was already revoked.'));
    }

    public function refund(Request $request, Order $order, BkashRefundService $bkashRefunds): RedirectResponse
    {
        $validated = $request->validate([
            'refund_reference' => ['nullable', 'string', 'max:120', 'unique:ebook_refunds,refund_reference'],
            'reason' => ['required', 'string', 'max:2000'],
            'confirm_refund' => ['nullable', 'accepted'],
        ]);

        $result = DB::transaction(function () use ($order, $request, $validated): array {
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($locked->refunds()->exists()) {
                return ['status' => 'already_refunded'];
            }

            $verifiedGatewayPayment = $locked->payments()
                ->where('gateway_provider', 'bkash')
                ->where('status', 'approved')
                ->whereNotNull('gateway_payment_id')
                ->whereNotNull('gateway_transaction_id')
                ->first();

            if ($locked->status !== 'completed'
                && ! ($locked->status === 'cancelled' && $locked->payments()->whereNotNull('gateway_transaction_id')->exists())) {
                return ['status' => 'not_refundable'];
            }

            $items = $locked->items->where('product_type', 'ebook');
            if ($locked->items->count() !== 1 || $items->count() !== 1 || (int) $items->first()->quantity !== 1 || (float) $locked->total <= 0) {
                return ['status' => 'unsupported_order'];
            }

            $hasBkashPayment = $locked->payment_method === 'bkash'
                || $locked->payments()->where('gateway_provider', 'bkash')->exists();

            if ($hasBkashPayment) {
                if (! $verifiedGatewayPayment
                    || round((float) $verifiedGatewayPayment->amount, 2) !== round((float) $locked->total, 2)
                    || strtoupper((string) $verifiedGatewayPayment->currency) !== 'BDT') {
                    return ['status' => 'gateway_mismatch'];
                }

                $refund = EbookRefund::create([
                    'order_id' => $locked->id,
                    'processed_by' => $request->user()->id,
                    'amount' => $locked->total,
                    'refund_reference' => 'PENDING-BKASH-ORDER-'.$locked->id,
                    'reason' => $validated['reason'],
                    'status' => 'processing',
                    'gateway_status' => 'refund_submitted',
                ]);

                PaymentAuditEvent::create([
                    'payment_id' => $verifiedGatewayPayment->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'ebook_gateway_refund_submitted',
                    'source' => 'admin_ebook_refund',
                    'after_state' => ['ebook_refund_id' => $refund->id, 'status' => 'processing', 'amount' => $refund->amount],
                    'ip_address' => $request->ip(),
                ]);

                return ['status' => 'gateway_processing', 'refund_id' => $refund->id, 'payment_id' => $verifiedGatewayPayment->id];
            }

            if (empty($validated['refund_reference']) || empty($validated['confirm_refund'])) {
                return ['status' => 'manual_confirmation_required'];
            }

            $refund = EbookRefund::create([
                'order_id' => $locked->id,
                'processed_by' => $request->user()->id,
                'amount' => $locked->total,
                'refund_reference' => $validated['refund_reference'],
                'reason' => $validated['reason'],
                'status' => 'refunded',
                'processed_at' => now(),
            ]);

            $locked->update(['status' => 'refunded']);
            $this->revokeEbookAccess($locked, $request->user()->id, $validated['reason']);
            PaymentAuditEvent::create([
                'actor_id' => $request->user()->id,
                'event' => 'ebook_manual_refund_confirmed',
                'source' => 'admin_ebook_refund',
                'after_state' => ['ebook_refund_id' => $refund->id, 'status' => 'refunded', 'amount' => $refund->amount, 'reference' => $refund->refund_reference],
                'ip_address' => $request->ip(),
            ]);

            return ['status' => 'refunded'];
        });

        if ($result['status'] === 'gateway_processing') {
            $refund = EbookRefund::findOrFail($result['refund_id']);
            $payment = Payment::findOrFail($result['payment_id']);

            try {
                $gatewayResult = $bkashRefunds->refund(
                    (string) $payment->gateway_payment_id,
                    (string) $payment->gateway_transaction_id,
                    number_format((float) $refund->amount, 2, '.', ''),
                    (string) $refund->reason
                );

                DB::transaction(function () use ($refund, $gatewayResult, $request): void {
                    $lockedRefund = EbookRefund::query()->lockForUpdate()->findOrFail($refund->id);
                    if ($lockedRefund->status !== 'processing') {
                        return;
                    }
                    $lockedRefund->update([
                        'refund_reference' => $gatewayResult['refund_id'],
                        'gateway_refund_id' => $gatewayResult['refund_id'],
                        'gateway_status' => $gatewayResult['status'],
                        'status' => 'refunded',
                        'processed_at' => now(),
                        'failure_reason' => null,
                    ]);
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedRefund->order_id);
                    $lockedOrder->update(['status' => 'refunded']);
                    $this->revokeEbookAccess($lockedOrder, $request->user()->id, (string) $lockedRefund->reason);
                    PaymentAuditEvent::create([
                        'actor_id' => $request->user()->id,
                        'provider' => 'bkash',
                        'event' => 'ebook_gateway_refund_verified',
                        'source' => 'bkash_refund',
                        'after_state' => ['ebook_refund_id' => $lockedRefund->id, 'status' => 'refunded', 'gateway_refund_id' => $gatewayResult['refund_id']],
                        'ip_address' => $request->ip(),
                    ]);
                });

                return back()->with('success', __('bKash refund completed and independently verified. eBook access has been revoked.'));
            } catch (Throwable $exception) {
                $refund->update([
                    'status' => 'gateway_unknown',
                    'gateway_status' => 'verification_unknown',
                    'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                PaymentAuditEvent::create([
                    'payment_id' => $payment->id,
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'ebook_gateway_refund_unverified',
                    'source' => 'bkash_refund',
                    'after_state' => ['ebook_refund_id' => $refund->id, 'status' => 'gateway_unknown'],
                    'ip_address' => $request->ip(),
                ]);

                return back()->with('error', __('bKash refund could not be verified. The order remains reserved for reconciliation and eBook access has not been revoked.'));
            }
        }

        return match ($result['status']) {
            'refunded' => back()->with('success', __('Full refund recorded and eBook access revoked.')),
            'already_refunded' => back()->with('info', __('A refund has already been recorded for this order.')),
            'unsupported_order' => back()->with('error', __('Only a single paid eBook item can be refunded through this workflow.')),
            'gateway_mismatch' => back()->with('error', __('The original bKash payment does not match this eBook order. No refund was sent.')),
            'manual_confirmation_required' => back()->withErrors(['refund_reference' => __('Provide the external refund reference and confirm the manual refund was sent.')])->withInput(),
            default => back()->with('error', __('Only completed eBook orders or cancelled orders with a verified gateway transaction can be refunded.')),
        };
    }

    public function reconcileRefund(Request $request, EbookRefund $refund, BkashRefundService $bkashRefunds): RedirectResponse
    {
        abort_unless($refund->status === 'gateway_unknown', 409);
        $order = Order::query()->with('payments')->findOrFail($refund->order_id);
        $payment = $order->payments->first(fn ($candidate) => $candidate->gateway_provider === 'bkash'
            && filled($candidate->gateway_payment_id) && filled($candidate->gateway_transaction_id));
        abort_unless($payment, 422);

        try {
            $gatewayResult = $bkashRefunds->verifyExisting(
                (string) $payment->gateway_payment_id,
                (string) $payment->gateway_transaction_id,
                number_format((float) $refund->amount, 2, '.', '')
            );

            DB::transaction(function () use ($refund, $gatewayResult, $request): void {
                $lockedRefund = EbookRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if ($lockedRefund->status !== 'gateway_unknown') {
                    return;
                }
                $lockedRefund->update([
                    'refund_reference' => $gatewayResult['refund_id'],
                    'gateway_refund_id' => $gatewayResult['refund_id'],
                    'gateway_status' => $gatewayResult['status'],
                    'status' => 'refunded',
                    'processed_at' => now(),
                    'failure_reason' => null,
                ]);
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedRefund->order_id);
                $lockedOrder->update(['status' => 'refunded']);
                $this->revokeEbookAccess($lockedOrder, $request->user()->id, (string) $lockedRefund->reason);
                PaymentAuditEvent::create([
                    'actor_id' => $request->user()->id,
                    'provider' => 'bkash',
                    'event' => 'ebook_gateway_refund_reconciled',
                    'source' => 'admin_ebook_refund_reconciliation',
                    'after_state' => ['ebook_refund_id' => $lockedRefund->id, 'status' => 'refunded', 'gateway_refund_id' => $gatewayResult['refund_id']],
                    'ip_address' => $request->ip(),
                ]);
            });

            return back()->with('success', __('Existing bKash refund verified and reconciled.'));
        } catch (Throwable $exception) {
            $refund->update(['failure_reason' => mb_substr($exception->getMessage(), 0, 2000)]);
            PaymentAuditEvent::create([
                'actor_id' => $request->user()->id,
                'provider' => 'bkash',
                'event' => 'ebook_gateway_refund_reconciliation_unconfirmed',
                'source' => 'admin_ebook_refund_reconciliation',
                'after_state' => ['ebook_refund_id' => $refund->id, 'status' => 'gateway_unknown'],
                'ip_address' => $request->ip(),
            ]);

            return back()->with('error', __('bKash has not confirmed the refund. The order remains reserved for reconciliation.'));
        }
    }

    private function revokeEbookAccess(Order $order, int $actorId, string $reason): void
    {
        $itemIds = $order->items()->where('product_type', 'ebook')->pluck('id');
        DownloadPermission::query()
            ->whereIn('order_item_id', $itemIds)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->get()
            ->each(fn (DownloadPermission $permission) => $permission->update([
                'revoked_at' => now(),
                'revoked_by' => $actorId,
                'revocation_reason' => 'Refund processed: '.$reason,
            ]));
    }

    public function salesReport(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $paidOrders = Order::query()
            ->whereIn('status', ['completed', 'refunded'])
            ->where('total', '>', 0)
            ->whereHas('items', fn ($query) => $query->where('product_type', 'ebook'));
        if (! empty($filters['from'])) {
            $paidOrders->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $paidOrders->whereDate('created_at', '<=', $filters['to']);
        }

        $orderCount = (clone $paidOrders)->count();
        $grossSales = (float) (clone $paidOrders)->sum('total');

        $refundQuery = EbookRefund::query()->whereHas('order', function ($query) use ($filters) {
            $query->whereHas('items', fn ($items) => $items->where('product_type', 'ebook'));
            if (! empty($filters['from'])) {
                $query->whereDate('created_at', '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->whereDate('created_at', '<=', $filters['to']);
            }
        });
        $refundTotal = (float) (clone $refundQuery)->sum('amount');
        $refundCount = (clone $refundQuery)->count();

        $productSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('ebook_refunds', 'ebook_refunds.order_id', '=', 'orders.id')
            ->where('order_items.product_type', 'ebook')
            ->whereIn('orders.status', ['completed', 'refunded'])
            ->where('orders.total', '>', 0)
            ->when(! empty($filters['from']), fn ($query) => $query->whereDate('orders.created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($query) => $query->whereDate('orders.created_at', '<=', $filters['to']))
            ->select([
                'order_items.product_id',
                'order_items.product_name',
            ])
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw('SUM(order_items.line_total) as gross_sales')
            ->selectRaw('SUM(COALESCE(ebook_refunds.amount * (order_items.line_total / NULLIF(orders.total, 0)), 0)) as refunded_sales')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('gross_sales')
            ->get();

        $refunds = (clone $refundQuery)->with(['order.user', 'processor'])->latest('processed_at')->paginate(20);
        $netSales = max(0, $grossSales - $refundTotal);

        return view('admin.ebooks.sales-report', compact(
            'filters', 'orderCount', 'grossSales', 'refundTotal', 'refundCount',
            'netSales', 'productSales', 'refunds'
        ));
    }
}
