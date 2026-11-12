<?php

namespace App\Services\Commerce;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    /**
     * Fulfill an order exactly once after a trusted payment verification or admin review.
     * Returns approved, reviewed, invalid, or coupon_invalid.
     */
    public function fulfill(Order|int $order): string
    {
        return DB::transaction(function () use ($order): string {
            $lockedOrder = Order::query()->lockForUpdate()
                ->with(['items.product'])
                ->findOrFail($order instanceof Order ? $order->id : $order);

            if ($lockedOrder->status !== 'pending') {
                return in_array($lockedOrder->status, ['completed'], true) ? 'reviewed' : 'closed';
            }

            if ($lockedOrder->coupon_id) {
                $coupon = Coupon::query()->lockForUpdate()->find($lockedOrder->coupon_id);
                if (!$coupon || !$coupon->isAvailableFor($lockedOrder->user_id, (float) $lockedOrder->subtotal)) {
                    return 'coupon_invalid';
                }
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->product_type === 'course' && (!$item->resource_id || !Course::whereKey($item->resource_id)->exists())) {
                    return 'invalid';
                }
                if ($item->product_type === 'ebook' && (!$item->product || !$item->product->is_downloadable || !$item->product->download_path)) {
                    return 'invalid';
                }
            }

            foreach ($lockedOrder->items as $item) {
                if ($item->product_type === 'course') {
                    $enrollment = Enrollment::firstOrNew([
                        'user_id' => $lockedOrder->user_id,
                        'course_id' => $item->resource_id,
                    ]);
                    if (!$enrollment->exists || !in_array($enrollment->status, ['active', 'completed'], true)) {
                        $enrollment->status = 'active';
                        $enrollment->save();
                    }
                }

                if ($item->product_type === 'ebook') {
                    $product = $item->product;
                    DownloadPermission::firstOrCreate(
                        ['order_item_id' => $item->id],
                        [
                            'user_id' => $lockedOrder->user_id,
                            'product_id' => $product->id,
                            'downloads_remaining' => $product->download_limit,
                            'expires_at' => $product->download_expires_at,
                        ]
                    );
                }
            }

            if ($lockedOrder->coupon_id) {
                $coupon = Coupon::query()->lockForUpdate()->findOrFail($lockedOrder->coupon_id);
                $redemption = CouponRedemption::firstOrCreate(
                    ['coupon_id' => $coupon->id, 'order_id' => $lockedOrder->id],
                    ['user_id' => $lockedOrder->user_id, 'discount_amount' => $lockedOrder->discount_total]
                );
                if ($redemption->wasRecentlyCreated) {
                    $coupon->increment('usage_count');
                }
            }

            $lockedOrder->update([
                'status' => 'completed',
                'paid_at' => $lockedOrder->paid_at ?? now(),
                'completed_at' => now(),
            ]);

            return 'approved';
        });
    }
}
