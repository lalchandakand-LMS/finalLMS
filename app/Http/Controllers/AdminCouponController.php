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
        $coupons = Coupon::withCount('redemptions')->latest()->paginate(25);
        return view('admin.coupons.index', compact('coupons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper(trim($data['code']));
        Coupon::create($data);
        return back()->with('success','Coupon created.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $data['code'] = strtoupper(trim($data['code']));
        $coupon->update($data);
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
        ]);
    }
}
