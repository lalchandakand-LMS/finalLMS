@extends('layouts.app')

@section('title', $course->title . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Course') }}</span>
            <h1 style="margin: 12px 0 0;">{{ $course->title }}</h1>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="panel">
            <p class="muted">{{ __('Instructor:') }} {{ $course->teacher?->name ?? __('N/A') }}</p>
            <p>{{ $course->description }}</p>
            @if(auth()->check() && auth()->user()->isAdmin())
                <a class="btn secondary" href="{{ route('admin.courses.lessons.index', $course) }}">{{ __('Manage lessons') }}</a>
            @elseif(auth()->check() && auth()->user()->isTeacher() && $course->teacher_id === auth()->id())
                <a class="btn secondary" href="{{ route('teacher.courses.lessons.index', $course) }}">{{ __('Manage lessons') }}</a>
            @endif
            <div class="meta-row">
                <span class="badge success">{{ __('Price:') }} BDT {{ number_format($course->price, 2) }}</span>
                <span class="badge warning">{{ __('Status:') }} {{ __(ucfirst($course->status)) }}</span>
            </div>

            <div style="margin-top: 28px;">
                <h2 style="margin-top:0;">{{ __('Learning path') }}</h2>
                @if($course->lessons->isEmpty())
                    <p class="muted">{{ __('No lessons are available for this course yet.') }}</p>
                @else
                    <div class="course-list">
                        @foreach($course->lessons as $lesson)
                            @if($canViewLessons || $lesson->is_preview)
                                <div class="course-item" style="margin-bottom: 14px;">
                                    <div class="meta-row">
                                        <div>
                                            <strong><a href="{{ route('courses.lessons.show', [$course, $lesson]) }}">{{ $lesson->order }}. {{ $lesson->title }}</a></strong><br>
                                            <span class="muted">{{ $lesson->duration_minutes }} minutes</span>
                                        </div>
                                        @if($lesson->is_preview)
                                            <span class="badge info">{{ __('Preview') }}</span>
                                        @endif
                                    </div>
                                    <p style="margin: 10px 0 0;">{{ \Illuminate\Support\Str::limit($lesson->content, 140) }}</p>
                                    <a class="btn secondary" style="margin-top:10px;" href="{{ route('courses.lessons.show', [$course, $lesson]) }}">{{ __('Open lesson') }}</a>
                                    @auth
                                        @if(auth()->user()->isStudent() && $canViewLessons && ! $lesson->is_preview)
                                            @if($lessonProgress->has($lesson->id))
                                                <span class="badge success" style="margin-top:12px;">{{ __('Completed') }}</span>
                                            @else
                                                <form method="POST" action="{{ route('courses.lessons.complete', [$course, $lesson]) }}" style="margin-top:12px;">
                                                    @csrf
                                                    <button type="submit" class="btn secondary">{{ __('Mark lesson complete') }}</button>
                                                </form>
                                            @endif
                                        @endif
                                    @endauth
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @unless($canViewLessons)
                        <p class="muted">{{ __('Enroll in this course to unlock the full learning path.') }}</p>
                    @endunless
                @endif
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">{{ __('Enrollment') }}</h2>
            @auth
                @if(auth()->user()->isStudent())
                    @if($enrollment)
                        @if($enrollment->status === 'completed')
                            <p class="muted">{{ __('You completed this course and received a certificate.') }}</p>
                        @else
                            <p class="muted">{{ __('You are enrolled and the course is active.') }}</p>
                            <form method="POST" action="{{ route('courses.complete', $course) }}">
                                @csrf
                                <button type="submit" class="btn primary" style="margin-top:12px;">{{ __('Mark as complete') }}</button>
                            </form>
                        @endif
                    @else
                        @if($payment?->status === 'pending')
                            <span class="badge warning">{{ __('Payment under review') }}</span>
                            <p class="muted">{{ __('Your :reference payment is awaiting verification. Access will be enabled after approval.', ['reference' => $payment->transaction_reference]) }}</p>
                        @else
                            @if($payment?->status === 'rejected')
                                <span class="badge warning">{{ __('Payment needs attention') }}</span>
                                <p class="muted">{{ $payment->admin_note ?: __('Your previous payment could not be verified. Please check the details and submit again.') }}</p>
                            @endif

                            @if((float) $course->price <= 0 && $batches->every(fn ($batch) => (float) $batch->fee <= 0))
                                <form method="POST" action="{{ route('courses.enroll', $course) }}">
                                    @csrf
                                    @if($batches->isNotEmpty())
                                        <label for="free-batch">{{ __('Choose a batch') }}</label>
                                        <select id="free-batch" name="batch_id" required>
                                            <option value="">{{ __('Select batch') }}</option>
                                            @foreach($batches as $batch)
                                                @if($batch->enrollments_count < $batch->capacity)
                                                    <option value="{{ $batch->id }}">{{ $batch->title }} · {{ $batch->branch->name }} · {{ $batch->starts_at->format('M j') }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="submit" class="btn primary">{{ __('Enroll now') }}</button>
                                </form>
                            @elseif($paymentMethods->isEmpty())
                                @unless(config('services.bkash.enabled'))
                                    <p class="muted">{{ __('Payment is not available for this course yet. Please contact the academy.') }}</p>
                                @endunless
                            @else
                                <form method="POST" action="{{ route('courses.enroll', $course) }}" enctype="multipart/form-data">
                                    @csrf
                                    <p class="muted">{{ __('The payable amount is based on the selected batch fee, or the course price if no batch fee applies. Your enrollment starts after admin verification.') }}</p>
                                    @if($batches->isNotEmpty())
                                        <label for="paid-batch">{{ __('Choose a batch') }}</label>
                                        <select id="paid-batch" name="batch_id" required>
                                            <option value="">{{ __('Select batch') }}</option>
                                            @foreach($batches as $batch)
                                                @if($batch->enrollments_count < $batch->capacity)
                                                    <option value="{{ $batch->id }}">{{ $batch->title }} · {{ $batch->branch->name }} · {{ $batch->starts_at->format('M j') }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif
                                    @if($errors->any())
                                        <div class="flash error">{{ __('Please check the payment details and try again.') }}</div>
                                    @endif
                                    <label for="payment-method">{{ __('Payment method') }}</label>
                                    <select id="payment-method" name="payment_method" required>
                                        @foreach($paymentMethods as $key => $method)
                                            <option value="{{ $key }}" data-account="{{ $method['account'] }}" data-instructions="{{ $method['instructions'] }}">{{ $method['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="course-item" style="margin: 14px 0;">
                                        <strong>{{ __('Send payment to') }}</strong>
                                        <p id="payment-account" style="margin: 8px 0;"></p>
                                        <p id="payment-instructions" class="muted" style="margin: 0;"></p>
                                    </div>
                                    <label for="sender-account">{{ __('Your sending number / account') }}</label>
                                    <input id="sender-account" type="text" name="sender_account" value="{{ old('sender_account') }}" placeholder="{{ __('Optional') }}">
                                    <label for="transaction-reference" style="margin-top:14px;">{{ __('Transaction reference') }}</label>
                                    <input id="transaction-reference" type="text" name="transaction_reference" value="{{ old('transaction_reference') }}" maxlength="120" required>
                                    <label for="payment-proof" style="margin-top:14px;">{{ __('Receipt or screenshot (optional)') }}</label>
                                    <input id="payment-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                                    <button type="submit" class="btn primary" style="margin-top:16px;">{{ __('Submit payment for review') }}</button>
                                </form>
                                <script>
                                    const paymentMethod = document.getElementById('payment-method');
                                    const updatePaymentDetails = () => {
                                        const selected = paymentMethod.options[paymentMethod.selectedIndex];
                                        document.getElementById('payment-account').textContent = selected.dataset.account || '{{ __('Contact the academy for account details.') }}';
                                        document.getElementById('payment-instructions').textContent = selected.dataset.instructions || '';
                                    };
                                    paymentMethod.addEventListener('change', updatePaymentDetails);
                                    updatePaymentDetails();
                                </script>
                            @endif
                            @if((float) $course->price > 0 || $batches->contains(fn ($batch) => (float) $batch->fee > 0))
                                @if(config('services.bkash.enabled'))
                                    <section class="course-item" style="margin-top:18px;">
                                        <h3>{{ __('Pay securely with bKash') }}</h3>
                                        <p class="muted">{{ __('You will be redirected to the official bKash checkout. Enrollment is enabled only after server-side payment verification.') }}</p>
                                        <form method="POST" action="{{ route('courses.payments.bkash', $course) }}">
                                            @csrf
                                            @if($batches->isNotEmpty())
                                                <label for="bkash-batch">{{ __('Choose a batch') }}</label>
                                                <select id="bkash-batch" name="batch_id" required>
                                                    <option value="">{{ __('Select batch') }}</option>
                                                    @foreach($batches as $batch)
                                                        @if($batch->enrollments_count < $batch->capacity)
                                                            <option value="{{ $batch->id }}">{{ $batch->title }} · {{ $batch->branch->name }} · {{ $batch->starts_at->format('M j') }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            @endif
                                            <button type="submit" class="btn primary" style="margin-top:12px;">{{ __('Continue to bKash checkout') }}</button>
                                        </form>
                                    </section>
                                @endif
                            @endif
                        @endif
                    @endif
                @else
                    <p class="muted">{{ __('This action is designed for students.') }}</p>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn primary">{{ __('Login to enroll') }}</a>
            @endauth
        </div>
    </div>
<section class="panel" style="margin-top:22px;">
    <div class="page-header" style="margin-bottom:10px;"><h2>{{ __('Course discussion') }}</h2><span class="badge info">{{ number_format($course->views_count) }} {{ __('views') }}</span></div>
    @auth
    <form method="POST" action="{{ route('courses.comments.store', $course) }}" style="margin-bottom:20px;">@csrf
        <label>{{ __('Write a comment or question') }}<textarea name="body" minlength="2" maxlength="2000" required></textarea></label>
        <button class="btn primary" type="submit">{{ __('Submit comment') }}</button>
        <small class="muted">{{ __('Comments appear after admin approval.') }}</small>
    </form>
    @else
    <p class="muted"><a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to join the discussion.') }}</p>
    @endauth
    @forelse($comments as $comment)
        <div class="course-item" style="margin-bottom:10px;"><strong>{{ $comment->author?->name ?? __('User') }}</strong><small class="muted"> · {{ $comment->created_at->format('d M Y') }}</small><p style="margin-bottom:0;">{{ $comment->body }}</p></div>
    @empty<p class="muted">{{ __('No approved comments yet.') }}</p>@endforelse
    {{ $comments->links() }}
</section>
@endsection
