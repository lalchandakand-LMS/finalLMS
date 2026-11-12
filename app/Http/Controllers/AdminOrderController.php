<?php

namespace App\Http\Controllers;

use App\Models\DownloadPermission;
use App\Models\Order;
use App\Services\Commerce\OrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::with(['user', 'items.downloadPermissions.logs', 'paymentTransactions'])->latest()->paginate(25);
        $summary = [
            'total' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'refunded' => Order::where('status', 'refunded')->count(),
            'revenue' => (float) Order::where('status', 'completed')->sum('total'),
            'downloads' => (int) \App\Models\EbookDownloadLog::count(),
        ];
        return view('admin.orders.index', compact('orders', 'summary'));
    }

    public function approve(Order $order, OrderFulfillmentService $fulfillment): RedirectResponse
    {
        $result = $fulfillment->fulfill($order);
        if ($result === 'reviewed') return back()->with('info', 'Order has already been reviewed.');
        if ($result === 'closed') return back()->with('error', 'Only pending orders can be approved.');
        if ($result === 'invalid') return back()->with('error', 'A course or eBook fulfillment resource is missing. Order was not approved.');
        if ($result === 'coupon_invalid') return back()->with('error', 'Coupon limits changed before verification. Please reconcile this order manually.');
        return back()->with('success', 'Order verified; course enrollment and eBook access granted.');
    }

    public function proof(Order $order)
    {
        abort_unless($order->payment_proof_path && Storage::disk('local')->exists($order->payment_proof_path), 404);
        return Storage::disk('local')->download($order->payment_proof_path, 'payment-proof-'.$order->order_number.'.pdf', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reject(Order $order): RedirectResponse
    {
        if ($order->status !== 'pending') return back()->with('info', 'Only pending orders can be rejected.');
        $order->update(['status' => 'cancelled']);
        return back()->with('success', 'Order cancelled.');
    }

    public function recordRefund(Order $order, Request $request): RedirectResponse
    {
        $data = $request->validate(['refund_note' => ['required', 'string', 'max:2000']]);
        $result = DB::transaction(function () use ($order, $request, $data): string {
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'completed') return 'not_completed';
            if ($locked->items->isEmpty() || $locked->items->contains(fn ($item) => $item->product_type !== 'ebook')) {
                return 'unsupported';
            }

            $permissions = DownloadPermission::query()->whereIn('order_item_id', $locked->items->pluck('id'))
                ->whereNull('revoked_at')->lockForUpdate()->get();
            foreach ($permissions as $permission) {
                $permission->update([
                    'revoked_at' => now(),
                    'revoked_by' => $request->user()->id,
                    'revocation_reason' => 'Order refund recorded: '.$data['refund_note'],
                ]);
            }

            $locked->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refunded_by' => $request->user()->id,
                'refund_note' => $data['refund_note'],
            ]);
            return 'refunded';
        });

        return match ($result) {
            'refunded' => back()->with('success', 'Refund recorded and eBook download access revoked. Process the actual money return through the original payment channel.'),
            'unsupported' => back()->with('error', 'This action only records eBook-only refunds. Course orders require the academy finance workflow.'),
            default => back()->with('error', 'Only completed orders can be refunded.'),
        };
    }

    public function revokeDownload(DownloadPermission $permission, Request $request): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $permission->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
            'revocation_reason' => $data['reason'],
        ]);
        return back()->with('success', 'eBook access revoked and the action recorded.');
    }
}
