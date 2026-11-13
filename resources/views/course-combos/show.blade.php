@extends('layouts.app')

@section('title', $courseCombo->title . ' | ' . config('app.name'))

@section('content')
    @php($regularPrice = (float) $courseCombo->courses->sum('price'))
    <div class="page-header">
        <div><span class="badge info">{{ __('Course combo') }}</span><h1 style="margin:12px 0 0;">{{ $courseCombo->title }}</h1><p class="muted">{{ $courseCombo->description }}</p></div>
        <a class="btn secondary" href="{{ route('course-combos.index') }}">{{ __('All combos') }}</a>
    </div>
    <div class="grid grid-2" style="align-items:start;">
        <section class="panel">
            <h2 style="margin-top:0;">{{ __('Included courses') }}</h2>
            <div class="course-list">
                @foreach($courseCombo->courses as $course)
                    <article class="course-item" style="display:flex;gap:14px;align-items:center;margin-bottom:12px;">
                        @if($course->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:112px;aspect-ratio:16/9;object-fit:cover;border-radius:9px;">@endif
                        <div style="min-width:0;flex:1;"><strong><a href="{{ route('courses.show', $course) }}">{{ $course->title }}</a></strong><p class="muted" style="margin:5px 0 0;">{{ $course->teacher?->name ?? __('Instructor') }} · ৳{{ number_format((float) $course->price, 2) }}</p></div>
                    </article>
                @endforeach
            </div>
        </section>
        <aside class="panel">
            <span class="muted">{{ __('Combo price') }}</span>
            <h2 style="font-size:2rem;margin:5px 0;">৳{{ number_format((float) $courseCombo->price, 2) }}</h2>
            @if($regularPrice > (float) $courseCombo->price)
                <p class="muted"><del>৳{{ number_format($regularPrice, 2) }}</del> · {{ __('You save') }} ৳{{ number_format($regularPrice - (float) $courseCombo->price, 2) }}</p>
            @endif
            <p class="muted">{{ $courseCombo->courses->count() }} {{ __('courses in this combo') }}</p>
            @if(!$purchaseAvailable)
                <div class="flash warning" role="status">{{ __('This combo cannot be purchased while an included course has an open batch. Please contact the academy or enroll in the course individually.') }}</div>
            @elseif(auth()->check() && auth()->user()->isStudent())
                @if((float) $courseCombo->price <= 0)
                    <form method="POST" action="{{ route('course-combos.enroll', $courseCombo) }}">
                        @csrf
                        <button class="btn primary" type="submit" style="width:100%;">{{ __('Enroll in all courses') }}</button>
                    </form>
                @elseif($paymentMethods->isEmpty())
                    <p class="muted">{{ __('Payment is not available right now. Please contact the academy.') }}</p>
                @else
                    <form method="POST" action="{{ route('course-combos.enroll', $courseCombo) }}" enctype="multipart/form-data">
                        @csrf
                        <label for="combo-payment-method">{{ __('Payment method') }}</label>
                        <select id="combo-payment-method" name="payment_method" required>
                            @foreach($paymentMethods as $key => $method)
                                <option value="{{ $key }}" data-account="{{ $method['account'] }}" data-instructions="{{ $method['instructions'] }}">{{ $method['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="course-item" style="margin:12px 0;">
                            <strong>{{ __('Send payment to') }}</strong>
                            <p id="combo-payment-account" style="margin:8px 0;"></p>
                            <p id="combo-payment-instructions" class="muted" style="margin:0;"></p>
                        </div>
                        <label for="combo-sender-account">{{ __('Your sending number / account') }}</label>
                        <input id="combo-sender-account" type="text" name="sender_account" value="{{ old('sender_account') }}" maxlength="50">
                        <label for="combo-transaction-reference" style="margin-top:12px;">{{ __('Transaction reference') }}</label>
                        <input id="combo-transaction-reference" type="text" name="transaction_reference" value="{{ old('transaction_reference') }}" maxlength="120" required>
                        <label for="combo-payment-proof" style="margin-top:12px;">{{ __('Receipt or screenshot (optional)') }}</label>
                        <input id="combo-payment-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                        @if($errors->any())<div class="flash error" role="alert">{{ __('Please check the payment details and try again.') }}</div>@endif
                        <button class="btn primary" type="submit" style="width:100%;margin-top:14px;">{{ __('Submit combo payment') }}</button>
                    </form>
                    <script>
                        (() => {
                            const select = document.getElementById('combo-payment-method');
                            const account = document.getElementById('combo-payment-account');
                            const instructions = document.getElementById('combo-payment-instructions');
                            const update = () => {
                                const option = select.options[select.selectedIndex];
                                account.textContent = option?.dataset.account || '—';
                                instructions.textContent = option?.dataset.instructions || '';
                            };
                            select.addEventListener('change', update);
                            update();
                        })();
                    </script>
                @endif
            @elseif(auth()->guest())
                <a class="btn primary" style="display:block;text-align:center;" href="{{ route('login', ['continue' => route('course-combos.show', $courseCombo)]) }}">{{ __('Login to buy this combo') }}</a>
            @else
                <p class="muted">{{ __('Only student accounts can purchase course combos.') }}</p>
            @endif
            <p class="muted" style="font-size:.82rem;margin:14px 0 0;">{{ __('Paid combo access is granted after the academy verifies the payment. All included courses are added to My Courses together.') }}</p>
        </aside>
    </div>
@endsection
