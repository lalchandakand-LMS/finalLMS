<?php

namespace App\Services\Commerce;

use App\Models\DownloadPermission;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EbookOrderFulfillmentService
{
    public function fulfill(Order|int $order): string
    {
        return DB::transaction(function () use ($order): string {
            $locked = Order::query()->lockForUpdate()
                ->with(['items.product'])
                ->findOrFail($order instanceof Order ? $order->id : $order);

            if ($locked->status === 'completed') return 'reviewed';
            if ($locked->status !== 'pending') return 'closed';
            if ($locked->items->isEmpty()) return 'invalid';

            foreach ($locked->items as $item) {
                $product = $item->product;
                if ($item->product_type !== 'ebook' || !$product || !$product->is_downloadable
                    || !$product->download_path || !Storage::disk('local')->exists($product->download_path)) {
                    return 'invalid';
                }
            }

            foreach ($locked->items as $item) {
                $product = $item->product;
                DownloadPermission::firstOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'user_id' => $locked->user_id,
                        'product_id' => $product->id,
                        'downloads_remaining' => $product->download_limit,
                        'expires_at' => $product->download_expires_at,
                    ]
                );
            }

            $locked->update(['status' => 'completed', 'paid_at' => now(), 'completed_at' => now()]);
            return 'approved';
        });
    }
}
