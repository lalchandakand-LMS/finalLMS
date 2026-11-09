<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function index(): View
    {
        $products = Product::published()->with(['categories','course'])->latest('published_at')->paginate(12);
        return view('store.index', compact('products'));
    }

    public function cart(Request $request): View
    {
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $cart->load('items.product');
        return view('store.cart', compact('cart'));
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'published', 404);
        $data = $request->validate(['quantity' => ['nullable','integer','min:1','max:10']]);
        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);
        $item = $cart->items()->firstOrNew(['product_id' => $product->id]);
        $item->quantity = min(10, ($item->exists ? $item->quantity : 0) + ($data['quantity'] ?? 1));
        $item->save();
        return back()->with('success', 'Product added to cart.');
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required','integer','min:1','max:10']]);
        $cartItem = \App\Models\CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id',$request->user()->id))->findOrFail($item);
        $cartItem->update($data);
        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, int $item): RedirectResponse
    {
        \App\Models\CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id',$request->user()->id))->findOrFail($item)->delete();
        return back()->with('success', 'Item removed.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $cart = Cart::with('items.product')->where('user_id',$request->user()->id)->firstOrFail();
        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');
        $methods = collect(\App\Models\Setting::paymentMethods())->filter(fn ($method) => (bool)($method['enabled'] ?? false))->keys()->all();
        $data = $request->validate([
            'payment_method' => ['required', Rule::in($methods)],
            'payment_reference' => ['required','string','max:120',Rule::unique('orders','payment_reference')],
        ]);
        $order = DB::transaction(function () use ($cart,$request,$data) {
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->with('items.product')->firstOrFail();
            $subtotal = 0;
            foreach ($cart->items as $item) {
                abort_unless($item->product && $item->product->status === 'published', 422, 'A product is no longer available.');
                $subtotal += (float)$item->product->currentPrice() * $item->quantity;
            }
            $order = Order::create([
                'order_number' => 'ALM-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'],
                'currency' => 'BDT',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'customer_snapshot' => ['name'=>$request->user()->name,'email'=>$request->user()->email,'phone'=>$request->user()->phone ?? null],
            ]);
            foreach ($cart->items as $item) {
                $product = $item->product;
                $price = (float)$product->currentPrice();
                $order->items()->create([
                    'product_id'=>$product->id,'product_type'=>$product->type,
                    'resource_id'=>$product->course_id,'product_name'=>$product->name,
                    'product_sku'=>$product->sku,'quantity'=>$item->quantity,
                    'regular_price'=>$price,'discount_total'=>0,'line_total'=>$price*$item->quantity,
                    'product_snapshot'=>['slug'=>$product->slug,'downloadable'=>(bool)$product->is_downloadable],
                ]);
            }
            $cart->items()->delete();
            return $order;
        });
        return redirect()->route('store.orders')->with('success','Order '.$order->order_number.' submitted. Access is granted after payment verification.');
    }

    public function orders(Request $request): View
    {
        $orders = Order::with('items')->where('user_id',$request->user()->id)->latest()->paginate(15);
        return view('store.orders', compact('orders'));
    }

    public function download(Request $request, DownloadPermission $permission)
    {
        abort_unless($permission->user_id === $request->user()->id && !$permission->revoked_at, 404);
        abort_if($permission->expires_at && $permission->expires_at->isPast(), 410);
        abort_if($permission->downloads_remaining !== null && $permission->downloads_remaining < 1, 410);
        $product = $permission->product;
        abort_unless($product && $product->is_downloadable && $product->download_path && Storage::disk('local')->exists($product->download_path), 404);
        DB::transaction(function () use ($permission) {
            $locked = DownloadPermission::query()->lockForUpdate()->findOrFail($permission->id);
            abort_unless(!$locked->revoked_at && (!$locked->expires_at || !$locked->expires_at->isPast()) && ($locked->downloads_remaining === null || $locked->downloads_remaining > 0), 410);
            $locked->increment('download_count');
            if ($locked->downloads_remaining !== null) $locked->decrement('downloads_remaining');
        });
        return Storage::disk('local')->download($product->download_path, basename($product->download_path));
    }
}
