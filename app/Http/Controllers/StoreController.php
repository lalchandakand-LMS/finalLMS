<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\DownloadPermission;
use App\Models\EbookDownloadLog;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Commerce\OrderFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::published()->with(['categories', 'course'])
            ->when($request->filled('category'), fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->where('product_categories.slug', (string) $request->string('category'))))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.trim((string) $request->string('q')).'%')->orWhere('short_description', 'like', '%'.trim((string) $request->string('q')).'%')))
            ->latest('published_at')->paginate(12)->withQueryString();
        $categories = ProductCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        return view('store.index', compact('products', 'categories'));
    }

    public function show(Product $product): View
    {
        abort_unless($product->status === 'published' && $product->published_at && $product->published_at->lte(now()), 404);
        $product->load(['categories', 'course']);
        return view('store.show', compact('product'));
    }

    public function preview(Product $product)
    {
        abort_unless($product->status === 'published' && $product->published_at && $product->published_at->lte(now()), 404);
        abort_unless($product->preview_path && Storage::disk('public')->exists($product->preview_path), 404);
        $filename = Str::slug($product->name).'-preview.pdf';
        return Storage::disk('public')->response($product->preview_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function cart(Request $request): View
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load('items.product');
        return view('store.cart', compact('cart'));
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'published' && $product->published_at && $product->published_at->lte(now()), 404);
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:10']]);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = min(10, ($item->exists ? $item->quantity : 0) + ($data['quantity'] ?? 1));
        $item->save();
        return back()->with('success', 'Product added to cart.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10']]);
        CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($item)->update($data);
        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $request->user()->id))->findOrFail($item)->delete();
        return back()->with('success', 'Item removed.');
    }

    public function checkout(Request $request, OrderFulfillmentService $fulfillment): RedirectResponse
    {
        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->firstOrFail();
        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');
        $methods = collect(\App\Models\Setting::paymentMethods())
            ->filter(fn ($method) => (bool) ($method['enabled'] ?? false))->keys()->all();
        $data = $request->validate([
            'payment_method' => ['required', Rule::in($methods)],
            'payment_reference' => ['nullable', 'required_unless:payment_method,bkash', 'string', 'max:120', Rule::unique('orders', 'payment_reference')],
            'payment_sender' => ['nullable', 'string', 'max:60'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
        ]);

        $order = DB::transaction(function () use ($cart, $request, $data) {
            $lockedCart = Cart::query()->whereKey($cart->id)->where('user_id', $request->user()->id)
                ->lockForUpdate()->with('items')->firstOrFail();
            abort_if($lockedCart->items->isEmpty(), 422, 'Your cart is empty.');

            $subtotal = 0.0;
            $linePrices = [];
            $lineFlashSales = [];
            foreach ($lockedCart->items as $item) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
                abort_unless($product && $product->status === 'published' && $product->published_at && $product->published_at->lte(now()), 422, 'A product is no longer available.');
                $item->setRelation('product', $product);

                $regularPrice = (float) $product->regular_price;
                $basePrice = $product->sale_price !== null && (float) $product->sale_price >= 0 && (float) $product->sale_price < $regularPrice
                    ? (float) $product->sale_price : $regularPrice;
                $flashSale = FlashSale::activeForProduct($product, $request->user()->id);
                if ($flashSale) {
                    $lockedSale = FlashSale::query()->lockForUpdate()->find($flashSale->id);
                    abort_unless($lockedSale && $lockedSale->isAvailableFor($request->user()->id) && $this->saleTargetsProduct($lockedSale, $product), 409, 'A Flash Sale changed while your cart was being checked. Refresh the cart and try again.');
                    $flashSale = $lockedSale;
                }
                $price = $flashSale ? $flashSale->discountedPrice($basePrice) : $basePrice;
                $linePrices[$product->id] = $price;
                $lineFlashSales[$product->id] = $flashSale?->id;
                $subtotal += $price * $item->quantity;
            }

            $coupon = null;
            $discount = 0.0;
            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::query()->where('code', strtoupper(trim($data['coupon_code'])))->lockForUpdate()->first();
                abort_unless($coupon && $coupon->isAvailableFor($request->user()->id, $subtotal), 422, 'This coupon is invalid, expired, or its usage limit has been reached.');
                $eligibleSubtotal = $coupon->eligibleSubtotal($lockedCart->items, $request->user()->id, $linePrices);
                abort_if($eligibleSubtotal <= 0, 422, 'This coupon does not apply to products in your cart.');
                $discount = $coupon->calculateDiscount($eligibleSubtotal);
            }

            $proofPath = $request->file('payment_proof')?->store('private/order-payment-proofs', 'local');
            $order = Order::create([
                'order_number' => 'ALM-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'coupon_id' => $coupon?->id,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? ('ONLINE-'.Str::upper(Str::random(24))),
                'payment_sender' => $data['payment_sender'] ?? null,
                'payment_proof_path' => $proofPath,
                'currency' => 'BDT',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'total' => max(0, $subtotal - $discount),
                'customer_snapshot' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone ?? null,
                ],
            ]);

            foreach ($lockedCart->items as $item) {
                $product = $item->product;
                $regularPrice = (float) $product->regular_price;
                $price = (float) $linePrices[$product->id];
                $order->items()->create([
                    'product_id' => $product->id,
                    'flash_sale_id' => $lineFlashSales[$product->id] ?? null,
                    'product_type' => $product->type,
                    'resource_id' => $product->course_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item->quantity,
                    'regular_price' => $regularPrice,
                    'discount_total' => round(max(0, $regularPrice - $price) * $item->quantity, 2),
                    'line_total' => round($price * $item->quantity, 2),
                    'product_snapshot' => ['slug' => $product->slug, 'downloadable' => (bool) $product->is_downloadable],
                ]);
            }
            $lockedCart->items()->delete();
            return $order;
        });

        if ((float) $order->total <= 0) {
            $result = $fulfillment->fulfill($order);
            abort_unless(in_array($result, ['approved', 'reviewed'], true), 409, 'Free order fulfillment needs administrator review.');
            return redirect()->route('store.orders')->with('success', 'Your free order is complete and access is ready.');
        }

        return redirect()->route('store.orders')->with('success', 'Order '.$order->order_number.' submitted. Complete payment from My Orders to receive access.');
    }

    public function orders(Request $request): View
    {
        $orders = Order::with(['items', 'paymentTransactions'])->where('user_id', $request->user()->id)->latest()->paginate(15);
        return view('store.orders', compact('orders'));
    }

    public function download(Request $request, DownloadPermission $permission)
    {
        $file = $this->authorizeAndLogFile($request, $permission, 'download');
        return Storage::disk('local')->download($file['path'], $file['filename'], [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reader(Request $request, DownloadPermission $permission)
    {
        $file = $this->authorizeAndLogFile($request, $permission, 'reader');
        return Storage::disk('local')->response($file['path'], $file['filename'], [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$file['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function authorizeAndLogFile(Request $request, DownloadPermission $permission, string $action): array
    {
        return DB::transaction(function () use ($request, $permission, $action): array {
            $locked = DownloadPermission::query()->with('product')->lockForUpdate()->findOrFail($permission->id);
            abort_unless($locked->user_id === $request->user()->id && ! $locked->revoked_at, 404);
            abort_if($locked->expires_at && $locked->expires_at->isPast(), 410, 'This download permission has expired.');
            abort_if($locked->downloads_remaining !== null && $locked->downloads_remaining < 1, 410, 'This download limit has been reached.');

            $product = $locked->product;
            abort_unless($product && $product->is_downloadable && $product->download_path && Storage::disk('local')->exists($product->download_path), 404);
            $locked->increment('download_count');
            if ($locked->downloads_remaining !== null) {
                $locked->decrement('downloads_remaining');
            }
            EbookDownloadLog::create([
                'download_permission_id' => $locked->id,
                'user_id' => $locked->user_id,
                'product_id' => $product->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                'downloaded_at' => now(),
            ]);

            return ['path' => $product->download_path, 'filename' => Str::slug($product->name).'.pdf'];
        });
    }

    private function saleTargetsProduct(FlashSale $sale, Product $product): bool
    {
        if ($product->course_id && $sale->courses()->whereKey($product->course_id)->exists()) {
            return true;
        }
        return $product->categories()->whereIn('product_categories.id', $sale->productCategories()->select('product_categories.id'))->exists();
    }
}
