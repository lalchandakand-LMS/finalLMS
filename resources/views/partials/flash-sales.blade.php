@if($flashSales->isNotEmpty())
<section id="flash-sale" class="panel" style="margin-bottom:30px;background:linear-gradient(125deg,#102b63,#1546a8);color:#fff;">
    <div class="page-header">
        <div>
            <span class="badge warning">🔥 {{ __('Limited-time offers') }}</span>
            <h2 style="color:#fff;margin:10px 0;">{{ __('Flash Sale') }}</h2>
            <p style="color:#dbe7ff;">{{ __('Special course discounts, controlled by the academy.') }}</p>
        </div>
        <strong>{{ __('Limited time only') }}</strong>
    </div>
    @foreach($flashSales as $sale)
        <div style="margin:18px 0;">
            @if($sale->banner_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($sale->banner_path) }}" alt="{{ $sale->title }}" style="width:100%;max-height:180px;object-fit:cover;border-radius:14px;margin-bottom:12px;">
            @endif
            <h3 style="color:#fff;">{{ $sale->title }} <span class="badge warning">{{ $sale->badge }}</span></h3>
            @if($sale->description)
                <p style="color:#dbe7ff;">{{ $sale->description }}</p>
            @endif
            <div class="grid grid-3">
                @foreach($sale->courses as $saleCourse)
                    <article class="card">
                        @if($saleCourse->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($saleCourse->image_path) }}" alt="{{ $saleCourse->title }}" style="width:100%;height:150px;object-fit:cover;border-radius:10px;">
                        @endif
                        <span class="badge warning">{{ $sale->badge }}</span>
                        <h3>{{ $saleCourse->title }}</h3>
                        <div class="meta-row">
                            <del class="muted">৳{{ number_format((float) $saleCourse->price, 2) }}</del>
                            <strong>৳{{ number_format($sale->discountedPrice((float) $saleCourse->price), 2) }}</strong>
                        </div>
                        <p class="muted">{{ __('Save') }} ৳{{ number_format(max(0, (float) $saleCourse->price - $sale->discountedPrice((float) $saleCourse->price)), 2) }}</p>
                        <a href="{{ route('courses.show', $saleCourse) }}" class="btn primary" style="width:100%;">{{ __('Get this offer') }}</a>
                    </article>
                @endforeach
            </div>
            <p style="color:#dbe7ff;">{{ __('Offer ends') }}: {{ $sale->ends_at->format('M d, Y h:i A') }}</p>
        </div>
    @endforeach
</section>
@endif
