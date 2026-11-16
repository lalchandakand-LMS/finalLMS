<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminCouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::query()->withCount([
            'redemptions as reserved_count' => fn ($query) => $query->where('status', 'reserved'),
            'redemptions as redeemed_count' => fn ($query) => $query->where('status', 'redeemed'),
        ])->with('courses:id,title')->latest()->paginate(25);
        $courses = Course::query()->where('status', 'published')->orderBy('title')->get(['id', 'title']);

        return view('admin.coupons.index', compact('coupons', 'courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $courseIds = $data['course_ids'] ?? [];
        unset($data['course_ids']);
        $data['code'] = Str::upper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = $request->user()->id;
        $coupon = Coupon::create($data);
        $coupon->courses()->sync($courseIds);

        return back()->with('success', 'Coupon created successfully.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $courseIds = $data['course_ids'] ?? [];
        unset($data['course_ids']);
        $data['code'] = Str::upper(trim($data['code']));
        $data['is_active'] = $request->boolean('is_active');
        $coupon->update($data);
        $coupon->courses()->sync($courseIds);

        return back()->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['is_active' => false]);
            return back()->with('info', 'Coupon has redemption history and was disabled instead of deleted.');
        }

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'.($coupon ? ','.$coupon->id : '')],
            'name' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'gt:0', $request->input('discount_type') === 'percentage' ? 'max:99.99' : 'max:1000000'],
            'minimum_subtotal' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'maximum_discount' => ['nullable', 'numeric', 'gt:0', 'max:100000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'per_user_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'course_ids' => ['nullable', 'array', 'max:500'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
        ]);
    }
}
