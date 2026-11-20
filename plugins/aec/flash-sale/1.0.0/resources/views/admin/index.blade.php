@extends('layouts.app')
@section('title', __('Flash Sale Management') . ' | ' . config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge warning">🔥 {{ __('Admin Marketing') }}</span><h1 style="margin:10px 0 0;">{{ __('Flash Sale Management') }}</h1><p class="muted">{{ __('Schedule special course discounts and control the homepage offer tab.') }}</p></div></div>
<div class="panel" style="margin-bottom:24px;">
    <h2>{{ __('Create campaign') }}</h2>
    <form method="POST" action="{{ route('admin.flash-sales.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-2">
            <label>{{ __('Campaign title') }}<input name="title" maxlength="180" required placeholder="English Learning Mega Sale"></label>
            <label>{{ __('Badge text') }}<input name="badge" maxlength="60" value="FLASH SALE" required></label>
            <label>{{ __('Discount type') }}<select name="discount_type" required><option value="percentage">{{ __('Percentage discount (%)') }}</option><option value="fixed_price">{{ __('Set special final price (BDT)') }}</option></select></label>
            <label>{{ __('Discount value') }}<input type="number" name="discount_value" min="0.01" max="1000000" step="0.01" required></label>
            <label>{{ __('Starts at (Bangladesh time)') }}<input type="datetime-local" name="starts_at" required></label>
            <label>{{ __('Ends at (Bangladesh time)') }}<input type="datetime-local" name="ends_at" required></label>
            <label>{{ __('Status') }}<select name="status"><option value="draft">{{ __('Draft') }}</option><option value="active">{{ __('Active / Scheduled') }}</option><option value="paused">{{ __('Paused') }}</option><option value="archived">{{ __('Archived') }}</option></select></label>
            <label>{{ __('Maximum successful/pending orders (optional)') }}<input type="number" name="max_redemptions" min="1" placeholder="{{ __('Unlimited') }}"></label>
            <label>{{ __('Purchase limit per student') }}<input type="number" name="per_student_limit" min="1" max="100" value="1" required></label>
            <label>{{ __('Display priority') }}<input type="number" name="priority" min="0" max="10000" value="0" required></label>
            <label>{{ __('Campaign banner (optional)') }}<input type="file" name="banner" accept="image/jpeg,image/png,image/webp"></label>
        </div>
        <label>{{ __('Description') }}<textarea name="description" rows="3" placeholder="{{ __('Describe this limited-time offer') }}"></textarea></label>
        <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;">
            <legend style="font-weight:700;padding:0 8px;">{{ __('Select published courses (optional when categories are selected)') }}</legend>
            <div class="grid grid-3">
                @foreach($courses as $course)
                    <label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="course_ids[]" value="{{ $course->id }}"> {{ $course->title }} · ৳{{ number_format($course->price,2) }}</label>
                @endforeach
            </div>
        </fieldset>
        <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;">
            <legend style="font-weight:700;padding:0 8px;">{{ __('Or target content categories') }}</legend>
            <div class="grid grid-3">
                @forelse($categories as $category)
                    <label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="category_ids[]" value="{{ $category->id }}"> {{ $category->name }} @unless($category->is_visible)<small class="muted">{{ __('(hidden)') }}</small>@endunless</label>
                @empty
                    <p class="muted">{{ __('No course categories have been created yet.') }}</p>
                @endforelse
            </div>
        </fieldset>
        <button class="btn primary" type="submit">{{ __('Create Flash Sale') }}</button>
    </form>
</div>
<div class="page-header"><h2>{{ __('Campaigns') }}</h2><span class="badge info">{{ __(':count total', ['count' => $sales->total()]) }}</span></div>
@forelse($sales as $sale)
<div class="panel" style="margin-bottom:18px;">
    <div class="meta-row" style="margin-top:0;"><div><h3 style="margin:0;">{{ $sale->title }}</h3><p class="muted" style="margin:6px 0;">{{ $sale->badge }} · {{ strtoupper($sale->status) }} · {{ __(':count reserved/approved/free redemptions', ['count' => $sale->reserved_orders + $sale->free_redemptions]) }}</p></div><span class="badge {{ $sale->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($sale->status) }}</span></div>
    <p>{{ $sale->description }}</p>
    <p class="muted">{{ __('Courses:') }} {{ $sale->courses->pluck('title')->join(', ') ?: __('Category-targeted') }} · {{ __('Categories:') }} {{ $sale->categories->pluck('name')->join(', ') ?: '—' }} · {{ $sale->starts_at->format('M d, Y H:i') }} – {{ $sale->ends_at->format('M d, Y H:i') }}</p>
    <details><summary style="cursor:pointer;font-weight:700;margin:12px 0;">{{ __('Edit campaign') }}</summary>
        <form method="POST" action="{{ route('admin.flash-sales.update',$sale) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="grid grid-2">
                <label>{{ __('Title') }}<input name="title" value="{{ $sale->title }}" required></label>
                <label>{{ __('Badge') }}<input name="badge" value="{{ $sale->badge }}" required></label>
                <label>{{ __('Discount type') }}<select name="discount_type"><option value="percentage" @selected($sale->discount_type==='percentage')>{{ __('Percentage') }}</option><option value="fixed_price" @selected($sale->discount_type==='fixed_price')>{{ __('Fixed final price') }}</option></select></label>
                <label>{{ __('Discount value') }}<input type="number" name="discount_value" min="0.01" step="0.01" value="{{ $sale->discount_value }}" required></label>
                <label>{{ __('Starts at') }}<input type="datetime-local" name="starts_at" value="{{ $sale->starts_at->format('Y-m-d\TH:i') }}" required></label>
                <label>{{ __('Ends at') }}<input type="datetime-local" name="ends_at" value="{{ $sale->ends_at->format('Y-m-d\TH:i') }}" required></label>
                <label>{{ __('Status') }}<select name="status">@foreach(['draft','active','paused','archived'] as $status)<option value="{{ $status }}" @selected($sale->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label>{{ __('Max redemptions') }}<input type="number" name="max_redemptions" min="1" value="{{ $sale->max_redemptions }}"></label>
                <label>{{ __('Per student limit') }}<input type="number" name="per_student_limit" min="1" max="100" value="{{ $sale->per_student_limit }}" required></label>
                <label>{{ __('Priority') }}<input type="number" name="priority" min="0" value="{{ $sale->priority }}" required></label>
                <label>{{ __('Replace banner') }}<input type="file" name="banner" accept="image/jpeg,image/png,image/webp"></label>
            </div>
            <label>{{ __('Description') }}<textarea name="description">{{ $sale->description }}</textarea></label>
            <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;"><legend>{{ __('Courses in campaign') }}</legend><div class="grid grid-3">
                @foreach($courses as $course)<label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="course_ids[]" value="{{ $course->id }}" @checked($sale->courses->contains('id',$course->id))> {{ $course->title }}</label>@endforeach
            </div></fieldset>
            <fieldset style="border:1px solid var(--border);border-radius:12px;padding:14px;margin:16px 0;"><legend>{{ __('Categories in campaign') }}</legend><div class="grid grid-3">
                @foreach($categories as $category)<label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input style="width:auto;" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked($sale->categories->contains('id', $category->id))> {{ $category->name }}</label>@endforeach
            </div></fieldset>
            <button class="btn primary" type="submit">{{ __('Save changes') }}</button>
        </form>
    </details>
    <form method="POST" action="{{ route('admin.flash-sales.destroy',$sale) }}" onsubmit="return confirm('{{ __('Delete this campaign? Campaigns with payment history must be archived.') }}')" style="margin-top:12px;">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete campaign') }}</button></form>
</div>
@empty
<div class="panel"><p class="muted">{{ __('No campaigns yet. Create your first Flash Sale above.') }}</p></div>
@endforelse
<div>{{ $sales->links() }}</div>
@endsection
