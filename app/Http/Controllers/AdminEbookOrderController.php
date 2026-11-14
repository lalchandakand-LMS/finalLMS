<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Commerce\EbookOrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminEbookOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::with(['user', 'items'])->latest()->paginate(25);
        return view('admin.ebooks.orders', compact('orders'));
    }

    public function proof(Order $order)
    {
        abort_unless($order->payment_proof_path && Storage::disk('local')->exists($order->payment_proof_path), 404);
        return Storage::disk('local')->download($order->payment_proof_path, 'payment-proof');
    }

    public function approve(Order $order, EbookOrderFulfillmentService $fulfillment): RedirectResponse
    {
        $result = $fulfillment->fulfill($order);
        return match ($result) {
            'approved' => back()->with('success', 'Payment verified and private eBook access granted.'),
            'reviewed' => back()->with('info', 'This order has already been completed.'),
            'closed' => back()->with('error', 'Only pending orders can be approved.'),
            default => back()->with('error', 'The eBook file or order item is invalid. No access was granted.'),
        };
    }

    public function reject(Order $order): RedirectResponse
    {
        if ($order->status !== 'pending') {
            return back()->with('info', 'Only pending orders can be rejected.');
        }
        $order->update(['status' => 'cancelled']);
        return back()->with('success', 'Order cancelled; no download access was granted.');
    }
}
