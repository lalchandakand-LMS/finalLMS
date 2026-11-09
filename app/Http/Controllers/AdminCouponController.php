<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::with(['products','categories'])->withCount('redemptions')->latest()->paginate(25);
        $products = \\App\\Models\\Product::published()->orderBy('name')->get();
        $categories = \\App\\Models\\ProductCategory::where('is_active',true)->orderBy('name')->get();
        return view('admin.coupons.index', compact('coupons','products','categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $productIds = $data['product_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['product_ids'], $data['category_ids']);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon = Coupon::create($data);
        $coupon->products()->sync($productIds);
        $coupon->categories()->sync($categoryIds);
        return back()->with('success','Coupon created.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $productIds = $data['product_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['product_ids'], $data['category_ids']);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon->update($data);
        $coupon->products()->sync($productIds);
        $coupon->categories()->sync($categoryIds);
        return back()->with('success','Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['is_active'=>false]);
            return back()->with('success','Coupon has order history and was deactivated.');
        }
        $coupon->delete();
        return back()->with('success','Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code'=>['required','string','max:60',Rule::unique('coupons','code')->ignore($coupon?->id)],
            'discount_type'=>['required','in:percentage,fixed'],
            'discount_value'=>['required','numeric','gt:0',$request->input('discount_type')==='percentage'?'max:99':'max:999999999'],
            'minimum_order'=>['nullable','numeric','min:0','max:999999999'],
            'starts_at'=>['nullable','date'],
            'ends_at'=>['nullable','date','after:starts_at'],
            'usage_limit'=>['nullable','integer','min:1','max:10000000'],
            'per_user_limit'=>['required','integer','min:1','max:1000'],
            'is_active'=>['required','boolean'],
            'product_ids'=>['nullable','array','max:200'],
            'product_ids.*'=>['required','integer','distinct','exists:products,id'],
            'category_ids'=>['nullable','array','max:100'],
            'category_ids.*'=>['required','integer','distinct','exists:product_categories,id'],
        ]);
    }
}
