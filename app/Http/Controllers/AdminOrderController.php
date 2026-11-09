<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Course;
use App\Models\CouponRedemption;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function index(): View
    {
        $orders = Order::with(['user','items'])->latest()->paginate(25);
        return view('admin.orders.index', compact('orders'));
    }

    public function approve(Order $order): RedirectResponse
    {
        $result = DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->with('items.product')->findOrFail($order->id);
            if ($order->status !== 'pending') return 'reviewed';
            if ($order->coupon_id) {
                $coupon = \App\Models\Coupon::query()->lockForUpdate()->find($order->coupon_id);
                if (!$coupon || !$coupon->isAvailableFor($order->user_id, (float) $order->subtotal)) return 'coupon_invalid';
            }
            foreach ($order->items as $item) {
                if ($item->product_type === 'course' && (!$item->resource_id || !Course::whereKey($item->resource_id)->exists())) return 'invalid';
                if ($item->product_type === 'ebook' && (!$item->product || !$item->product->is_downloadable || !$item->product->download_path)) {
                    return 'invalid';
                }
            }
            foreach ($order->items as $item) {
                if ($item->product_type === 'course' && $item->resource_id) {
                    $enrollment = Enrollment::firstOrNew([
                        'user_id'=>$order->user_id,
                        'course_id'=>$item->resource_id,
                    ]);
                    if (!$enrollment->exists || !in_array($enrollment->status, ['active','completed'], true)) {
                        $enrollment->status = 'active';
                        $enrollment->save();
                    }
                }
                if ($item->product_type === 'ebook' && $item->product_id) {
                    $product = $item->product;
                    if (!$product || !$product->is_downloadable || !$product->download_path) return 'invalid';
                    DownloadPermission::firstOrCreate(
                        ['order_item_id'=>$item->id],
                        ['user_id'=>$order->user_id,'product_id'=>$product->id,'downloads_remaining'=>$product->download_limit,'expires_at'=>$product->download_expires_at]
                    );
                }
            }
            if ($order->coupon_id) {
                $coupon = \App\Models\Coupon::query()->lockForUpdate()->findOrFail($order->coupon_id);
                $redemption = CouponRedemption::firstOrCreate(
                    ['coupon_id'=>$coupon->id,'order_id'=>$order->id],
                    ['user_id'=>$order->user_id,'discount_amount'=>$order->discount_total]
                );
                if ($redemption->wasRecentlyCreated) $coupon->increment('usage_count');
            }
            $order->update(['status'=>'completed','paid_at'=>now(),'completed_at'=>now()]);
            return 'approved';
        });
        if ($result === 'reviewed') return back()->with('info','Order has already been reviewed.');
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
