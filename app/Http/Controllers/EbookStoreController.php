<?php

namespace App\Http\Controllers;

use App\Models\DownloadPermission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EbookStoreController extends Controller
{
    public function index(): View
    {
        $products = Product::published()->with('categories')->latest('published_at')->paginate(12);
        return view('ebooks.index', compact('products'));
    }

    public function show(Product $product): View
    {
        abort_unless($product->type === 'ebook' && $product->status === 'published'
            && $product->published_at && $product->published_at->lte(now()) && $product->is_downloadable, 404);
        $product->load('categories');
        return view('ebooks.show', compact('product'));
    }

    public function checkout(Product $product, Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:120', 'unique:orders,payment_reference'],
            'payment_sender' => ['nullable', 'string', 'max:60'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $order = DB::transaction(function () use ($product, $request, $validated): Order {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->type === 'ebook' && $locked->is_downloadable
                && $locked->status === 'published' && $locked->published_at && $locked->published_at->lte(now())
                && $locked->download_path && Storage::disk('local')->exists($locked->download_path), 422,
                'This eBook is not currently available.');

            $alreadyPending = Order::query()->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->whereHas('items', fn ($query) => $query->where('product_id', $locked->id))
                ->exists();
            abort_if($alreadyPending, 409, 'You already have a pending order for this eBook.');

            $alreadyOwned = DownloadPermission::query()
                ->where('user_id', $request->user()->id)
                ->where('product_id', $locked->id)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('downloads_remaining')->orWhere('downloads_remaining', '>', 0))
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))
                ->exists();
            abort_if($alreadyOwned, 409, 'This eBook is already available in your library.');

            $price = (float) $locked->sale_price >= 0 && $locked->sale_price !== null
                && (float) $locked->sale_price < (float) $locked->regular_price
                ? (float) $locked->sale_price : (float) $locked->regular_price;
            abort_if($price > 0 && blank($validated['payment_reference'] ?? null), 422, 'A payment reference is required for paid eBooks.');
            $proofPath = $request->file('payment_proof')?->store('private/ebook-payment-proofs', 'local');

            $order = Order::create([
                'order_number' => 'AEC-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'status' => $price <= 0 ? 'completed' : 'pending',
                'payment_method' => 'manual',
                'payment_reference' => $validated['payment_reference'] ?? ('FREE-'.Str::upper(Str::random(24))),
                'payment_sender' => $validated['payment_sender'] ?? null,
                'payment_proof_path' => $proofPath,
                'currency' => 'BDT',
                'subtotal' => $price,
                'discount_total' => 0,
                'total' => $price,
                'paid_at' => $price <= 0 ? now() : null,
                'completed_at' => $price <= 0 ? now() : null,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $locked->id,
                'product_type' => 'ebook',
                'product_name' => $locked->name,
                'quantity' => 1,
                'regular_price' => $price,
                'line_total' => $price,
                'product_snapshot' => [
                    'name' => $locked->name,
                    'slug' => $locked->slug,
                    'download_path' => $locked->download_path,
                ],
            ]);

            if ($price <= 0) {
                DownloadPermission::create([
                    'order_item_id' => $item->id,
                    'user_id' => $request->user()->id,
                    'product_id' => $locked->id,
                    'downloads_remaining' => $locked->download_limit,
                    'expires_at' => $locked->download_expires_at,
                ]);
            }

            return $order;
        });

        return redirect()->route('ebooks.orders')->with(
            'success',
            $order->status === 'completed'
                ? 'Free eBook added to your library.'
                : 'Order submitted. Download access will be enabled after payment verification.'
        );
    }

    public function orders(Request $request): View
    {
        $orders = Order::with(['items.product'])
            ->where('user_id', $request->user()->id)
            ->latest()->paginate(15);
        $permissions = DownloadPermission::query()->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')->with('product')->get()->keyBy('order_item_id');
        return view('ebooks.orders', compact('orders', 'permissions'));
    }

    public function download(DownloadPermission $permission, Request $request)
    {
        abort_unless((int) $permission->user_id === (int) $request->user()->id, 404);
        $permission->load(['product', 'orderItem.order']);
        $product = $permission->product;

        abort_unless($permission->orderItem?->order?->status === 'completed'
            && (int) $permission->orderItem->order->user_id === (int) $request->user()->id, 404);
        abort_unless($product && $product->is_downloadable && $product->download_path
            && Storage::disk('local')->exists($product->download_path), 404);

        DB::transaction(function () use ($permission): void {
            $locked = DownloadPermission::query()->lockForUpdate()->findOrFail($permission->id);
            abort_if($locked->revoked_at, 410);
            abort_if($locked->expires_at && $locked->expires_at->isPast(), 410);
            abort_if($locked->downloads_remaining !== null && $locked->downloads_remaining < 1, 410);

            $locked->increment('download_count');
            if ($locked->downloads_remaining !== null) {
                $locked->decrement('downloads_remaining');
            }
        });

        return Storage::disk('local')->download(
            $product->download_path,
            Str::slug($product->name).'.pdf',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
