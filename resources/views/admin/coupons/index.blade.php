@extends('layouts.app')
@section('title','Coupon Management')
@section('content')
<div class="container" style="max-width:1150px;margin:30px auto;padding:0 18px">
<h1>Coupon Management</h1><p>Percentage and fixed discounts, validity windows, minimum spend and usage limits.</p>
<form method="POST" action="{{ route('admin.coupons.store') }}" class="panel" style="padding:18px;border:1px solid var(--border);border-radius:12px;margin-bottom:22px">
@csrf<h3>Create coupon</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
<label>Code<input name="code" required maxlength="60" placeholder="WELCOME10" style="display:block;width:100%;padding:9px"></label>
<label>Discount type<select name="discount_type" style="display:block;width:100%;padding:9px"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed amount (৳)</option></select></label>
<label>Discount value<input name="discount_value" type="number" step=".01" min=".01" required style="display:block;width:100%;padding:9px"></label>
<label>Minimum order<input name="minimum_order" type="number" step=".01" min="0" value="0" style="display:block;width:100%;padding:9px"></label>
<label>Starts at<input name="starts_at" type="datetime-local" style="display:block;width:100%;padding:9px"></label>
<label>Ends at<input name="ends_at" type="datetime-local" style="display:block;width:100%;padding:9px"></label>
<label>Total uses<input name="usage_limit" type="number" min="1" placeholder="Unlimited" style="display:block;width:100%;padding:9px"></label>
<label>Uses per student<input name="per_user_limit" type="number" min="1" value="1" required style="display:block;width:100%;padding:9px"></label>
</div><input type="hidden" name="is_active" value="1"><button class="btn btn-primary" style="margin-top:12px">Create coupon</button>
</form>
@foreach($coupons as $coupon)
<section class="panel" style="padding:16px;border:1px solid var(--border);border-radius:12px;margin:12px 0">
<form method="POST" action="{{ route('admin.coupons.update',$coupon) }}">@csrf @method('PUT')
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
<label>Code<input name="code" value="{{ $coupon->code }}" required style="width:100%;padding:8px"></label>
<label>Type<select name="discount_type" style="width:100%;padding:8px"><option value="percentage" @selected($coupon->discount_type==='percentage')>Percentage</option><option value="fixed" @selected($coupon->discount_type==='fixed')>Fixed</option></select></label>
<label>Value<input name="discount_value" type="number" step=".01" value="{{ $coupon->discount_value }}" required style="width:100%;padding:8px"></label>
<label>Minimum order<input name="minimum_order" type="number" step=".01" value="{{ $coupon->minimum_order }}" style="width:100%;padding:8px"></label>
<label>Starts<input name="starts_at" type="datetime-local" value="{{ $coupon->starts_at?->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
<label>Ends<input name="ends_at" type="datetime-local" value="{{ $coupon->ends_at?->format('Y-m-d\TH:i') }}" style="width:100%;padding:8px"></label>
<label>Usage limit<input name="usage_limit" type="number" min="1" value="{{ $coupon->usage_limit }}" style="width:100%;padding:8px"></label>
<label>Per student<input name="per_user_limit" type="number" min="1" value="{{ $coupon->per_user_limit }}" required style="width:100%;padding:8px"></label>
<label>Status<select name="is_active" style="width:100%;padding:8px"><option value="1" @selected($coupon->is_active)>Active</option><option value="0" @selected(!$coupon->is_active)>Inactive</option></select></label>
</div><p>Used {{ $coupon->usage_count }} / {{ $coupon->usage_limit ?? '∞' }} · {{ $coupon->redemptions_count }} verified orders</p><button class="btn btn-primary">Save changes</button>
</form>
<form method="POST" action="{{ route('admin.coupons.destroy',$coupon) }}" style="margin-top:8px">@csrf @method('DELETE')<button class="btn">Delete / deactivate</button></form>
</section>
@endforeach
{{ $coupons->links() }}
</div>
@endsection