@extends('layouts.app')
@section('title', 'Coupon Management | '.config('app.name'))
@section('content')
<div class="page-header"><div><h1>Coupon Management</h1><p class="muted">Create discount codes, limit redemptions and target selected courses.</p></div></div>
<div class="panel" style="margin-bottom:24px;">
    <h2>Create coupon</h2>
    <form method="POST" action="{{ route('admin.coupons.store') }}">@csrf
        <div class="grid grid-2">
            <label>Coupon code<input name="code" maxlength="40" required placeholder="WELCOME20" pattern="[A-Za-z0-9_-]+"></label>
            <label>Display name<input name="name" maxlength="120" required placeholder="New student offer"></label>
            <label>Discount type<select name="discount_type"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed amount (BDT)</option></select></label>
            <label>Discount value<input type="number" name="discount_value" min="0.01" step="0.01" required></label>
            <label>Minimum cart subtotal<input type="number" name="minimum_subtotal" min="0" step="0.01" value="0"></label>
            <label>Maximum discount (optional)<input type="number" name="maximum_discount" min="0.01" step="0.01"></label>
            <label>Starts at<input type="datetime-local" name="starts_at"></label>
            <label>Expires at<input type="datetime-local" name="ends_at"></label>
            <label>Total usage limit<input type="number" name="usage_limit" min="1" placeholder="Unlimited"></label>
            <label>Per-student limit<input type="number" name="per_user_limit" min="1" max="1000" value="1" required></label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" value="1" checked style="width:auto;"> Active immediately</label>
        </div>
        <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;"><legend>Eligible courses (leave empty for all courses)</legend><div class="grid grid-3">
            @foreach($courses as $course)<label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="course_ids[]" value="{{ $course->id }}"> {{ $course->title }}</label>@endforeach
        </div></fieldset>
        <button class="btn primary" type="submit">Create coupon</button>
    </form>
</div>
@forelse($coupons as $coupon)
<div class="panel" style="margin-bottom:16px;">
    <div class="meta-row"><div><h3 style="margin:0;font-family:monospace;">{{ $coupon->code }}</h3><p class="muted">{{ $coupon->name }} · {{ $coupon->discount_type === 'percentage' ? $coupon->discount_value.'%' : '৳'.number_format($coupon->discount_value,2) }} · {{ $coupon->reserved_count }} pending / {{ $coupon->redeemed_count }} redeemed</p></div><span class="badge {{ $coupon->is_active ? 'success' : 'warning' }}">{{ $coupon->is_active ? 'Active' : 'Disabled' }}</span></div>
    <details><summary style="cursor:pointer;font-weight:700;">Edit coupon</summary>
    <form method="POST" action="{{ route('admin.coupons.update',$coupon) }}">@csrf @method('PUT')
        <div class="grid grid-2">
            <label>Code<input name="code" value="{{ $coupon->code }}" required></label>
            <label>Name<input name="name" value="{{ $coupon->name }}" required></label>
            <label>Discount type<select name="discount_type"><option value="percentage" @selected($coupon->discount_type==='percentage')>Percentage</option><option value="fixed" @selected($coupon->discount_type==='fixed')>Fixed amount</option></select></label>
            <label>Discount value<input type="number" name="discount_value" value="{{ $coupon->discount_value }}" min="0.01" step="0.01" required></label>
            <label>Minimum subtotal<input type="number" name="minimum_subtotal" value="{{ $coupon->minimum_subtotal }}" min="0" step="0.01"></label>
            <label>Maximum discount<input type="number" name="maximum_discount" value="{{ $coupon->maximum_discount }}" min="0.01" step="0.01"></label>
            <label>Starts at<input type="datetime-local" name="starts_at" value="{{ $coupon->starts_at?->format('Y-m-d\\TH:i') }}"></label>
            <label>Expires at<input type="datetime-local" name="ends_at" value="{{ $coupon->ends_at?->format('Y-m-d\\TH:i') }}"></label>
            <label>Usage limit<input type="number" name="usage_limit" value="{{ $coupon->usage_limit }}" min="1"></label>
            <label>Per-student limit<input type="number" name="per_user_limit" value="{{ $coupon->per_user_limit }}" min="1" max="1000" required></label>
            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" value="1" @checked($coupon->is_active) style="width:auto;"> Active</label>
        </div>
        <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;"><legend>Eligible courses (none means all)</legend><div class="grid grid-3">
            @foreach($courses as $course)<label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked($coupon->courses->contains('id',$course->id))> {{ $course->title }}</label>@endforeach
        </div></fieldset>
        <button class="btn primary" type="submit">Save coupon</button>
    </form></details>
    <form method="POST" action="{{ route('admin.coupons.destroy',$coupon) }}" style="margin-top:12px;" onsubmit="return confirm('Delete or disable this coupon?')">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete / disable</button></form>
</div>
@empty<div class="panel"><p class="muted">No coupons created.</p></div>@endforelse
<div>{{ $coupons->links() }}</div>
@endsection
