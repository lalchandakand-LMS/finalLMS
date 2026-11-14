<?php

namespace App\Http\Controllers;

use App\Models\DownloadPermission;
use App\Models\EbookRefund;
use App\Models\Order;
use App\Services\Commerce\EbookOrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminEbookOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->whereHas('items', fn ($query) => $query->where('product_type', 'ebook'))
            ->with(['user', 'items.downloadPermission', 'refunds.processor'])
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
            default => back()->with('error', __('The eBook file or order item is invalid. No access was granted.')),
        };
    }

    public function reject(Order $order): RedirectResponse
    {
        abort_unless($order->items()->where('product_type', 'ebook')->exists(), 404);
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

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'refund_reference' => ['required', 'string', 'max:120', 'unique:ebook_refunds,refund_reference'],
            'reason' => ['required', 'string', 'max:2000'],
            'confirm_refund' => ['accepted'],
        ]);

        $result = DB::transaction(function () use ($order, $request, $validated): string {
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($locked->refunds()->exists()) {
                return 'already_refunded';
            }
            if ($locked->status !== 'completed') {
                return 'not_refundable';
            }

            $items = $locked->items->where('product_type', 'ebook');
            if ($locked->items->count() !== 1 || $items->count() !== 1 || (int) $items->first()->quantity !== 1 || (float) $locked->total <= 0) {
                return 'unsupported_order';
            }

            EbookRefund::create([
                'order_id' => $locked->id,
                'processed_by' => $request->user()->id,
                'amount' => $locked->total,
                'refund_reference' => $validated['refund_reference'],
                'reason' => $validated['reason'],
                'processed_at' => now(),
            ]);

            DownloadPermission::query()
                ->where('order_item_id', $items->first()->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (DownloadPermission $permission) => $permission->update([
                    'revoked_at' => now(),
                    'revoked_by' => $request->user()->id,
                    'revocation_reason' => 'Refund processed: '.$validated['reason'],
                ]));

            $locked->update(['status' => 'refunded']);

            return 'refunded';
        });

        return match ($result) {
            'refunded' => back()->with('success', __('Full refund recorded and eBook access revoked.')),
            'already_refunded' => back()->with('info', __('A refund has already been recorded for this order.')),
            'unsupported_order' => back()->with('error', __('Only a single paid eBook item can be refunded through this workflow.')),
            default => back()->with('error', __('Only completed eBook orders can be refunded. Verify that the external refund has been sent first.')),
        };
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
