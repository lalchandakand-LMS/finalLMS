@extends('layouts.app')

@section('title', $course->title . ' | LMS Academy')

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Course</span>
            <h1 style="margin: 12px 0 0;">{{ $course->title }}</h1>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="panel">
            <p class="muted">Instructor: {{ $course->teacher?->name ?? 'N/A' }}</p>
            <p>{{ $course->description }}</p>
            <div class="meta-row">
                <span class="badge success">Price: BDT {{ number_format($course->price, 2) }}</span>
                <span class="badge warning">Status: {{ ucfirst($course->status) }}</span>
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
                                            <strong>{{ $lesson->order }}. {{ $lesson->title }}</strong><br>
                                            <span class="muted">{{ $lesson->duration_minutes }} minutes</span>
                                        </div>
                                        @if($lesson->is_preview)
                                            <span class="badge info">{{ __('Preview') }}</span>
                                        @endif
                                    </div>
                                    <p style="margin: 10px 0 0;">{{ $lesson->content }}</p>
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
                            <p class="muted">You completed this course and received a certificate.</p>
                        @else
                            <p class="muted">You are enrolled and the course is active.</p>
                            <form method="POST" action="{{ route('courses.complete', $course) }}">
                                @csrf
                                <button type="submit" class="btn primary" style="margin-top:12px;">Mark as complete</button>
                            </form>
                        @endif
                    @else
                        @if($payment?->status === 'pending')
                            <span class="badge warning">Payment under review</span>
                            <p class="muted">Your {{ $payment->transaction_reference }} payment is awaiting verification. Access will be enabled after approval.</p>
                        @else
                            @if($payment?->status === 'rejected')
                                <span class="badge warning">Payment needs attention</span>
                                <p class="muted">{{ $payment->admin_note ?: 'Your previous payment could not be verified. Please check the details and submit again.' }}</p>
                            @endif

                            @if((float) $course->price <= 0)
                                <form method="POST" action="{{ route('courses.enroll', $course) }}">
                                    @csrf
                                    @if($batches->isNotEmpty())
                                        <label for="free-batch">Choose a batch</label>
                                        <select id="free-batch" name="batch_id" required>
                                            <option value="">Select batch</option>
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
                                <p class="muted">Payment is not available for this course yet. Please contact the academy.</p>
                            @else
                                <form method="POST" action="{{ route('courses.enroll', $course) }}" enctype="multipart/form-data">
                                    @csrf
                                    <p class="muted">Pay <strong>BDT {{ number_format($course->price, 2) }}</strong> using one of the methods below. Your enrollment starts after admin verification.</p>
                                    @if($batches->isNotEmpty())
                                        <label for="paid-batch">Choose a batch</label>
                                        <select id="paid-batch" name="batch_id" required>
                                            <option value="">Select batch</option>
                                            @foreach($batches as $batch)
                                                @if($batch->enrollments_count < $batch->capacity)
                                                    <option value="{{ $batch->id }}">{{ $batch->title }} · {{ $batch->branch->name }} · {{ $batch->starts_at->format('M j') }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    @endif
                                    @if($errors->any())
                                        <div class="flash error">Please check the payment details and try again.</div>
                                    @endif
                                    <label for="payment-method">Payment method</label>
                                    <select id="payment-method" name="payment_method" required>
                                        @foreach($paymentMethods as $key => $method)
                                            <option value="{{ $key }}" data-account="{{ $method['account'] }}" data-instructions="{{ $method['instructions'] }}">{{ $method['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="course-item" style="margin: 14px 0;">
                                        <strong>Send payment to</strong>
                                        <p id="payment-account" style="margin: 8px 0;"></p>
                                        <p id="payment-instructions" class="muted" style="margin: 0;"></p>
                                    </div>
                                    <label for="sender-account">Your sending number / account</label>
                                    <input id="sender-account" type="text" name="sender_account" value="{{ old('sender_account') }}" placeholder="Optional">
                                    <label for="transaction-reference" style="margin-top:14px;">Transaction reference</label>
                                    <input id="transaction-reference" type="text" name="transaction_reference" value="{{ old('transaction_reference') }}" maxlength="120" required>
                                    <label for="payment-proof" style="margin-top:14px;">Receipt or screenshot (optional)</label>
                                    <input id="payment-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf">
                                    <button type="submit" class="btn primary" style="margin-top:16px;">Submit payment for review</button>
                                </form>
                                <script>
                                    const paymentMethod = document.getElementById('payment-method');
                                    const updatePaymentDetails = () => {
                                        const selected = paymentMethod.options[paymentMethod.selectedIndex];
                                        document.getElementById('payment-account').textContent = selected.dataset.account || 'Contact the academy for account details.';
                                        document.getElementById('payment-instructions').textContent = selected.dataset.instructions || '';
                                    };
                                    paymentMethod.addEventListener('change', updatePaymentDetails);
                                    updatePaymentDetails();
                                </script>
                            @endif
                        @endif
                    @endif
                @else
                    <p class="muted">This action is designed for students.</p>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn primary">Login to enroll</a>
            @endauth
        </div>
    </div>
@endsection
