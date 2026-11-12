<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Commerce\OrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::with(['user','items','paymentTransactions'])->latest()->paginate(25);
        return view('admin.orders.index', compact('orders'));
    }

    public function approve(Order $order, OrderFulfillmentService $fulfillment): RedirectResponse
    {
        $result = $fulfillment->fulfill($order);
        if ($result === 'reviewed') return back()->with('info','Order has already been reviewed.');
        if ($result === 'closed') return back()->with('error','Only pending orders can be approved.');
        if ($result === 'invalid') return back()->with('error','A course or eBook fulfillment resource is missing. Order was not approved.');
        if ($result === 'coupon_invalid') return back()->with('error','Coupon limits changed before verification. Please reconcile this order manually.');
        return back()->with('success','Order verified; course enrollment and eBook access granted.');
    }

    public function proof(Order $order)
    {
        abort_unless($order->payment_proof_path && Storage::disk('local')->exists($order->payment_proof_path), 404);
        return Storage::disk('local')->download($order->payment_proof_path, basename($order->payment_proof_path));
    }

    public function reject(Order $order): RedirectResponse
    {
        if ($order->status !== 'pending') return back()->with('info','Only pending orders can be rejected.');
        $order->update(['status'=>'cancelled']);
        return back()->with('success','Order cancelled.');
    }
}
