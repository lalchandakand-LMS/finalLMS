@extends('layouts.app')

@section('title', $course->title . ' | ' . config('app.name'))

@section('content')
    @php($activeFlashSale = \App\Models\FlashSale::activeForCourse($course->id, auth()->id()))
    @php($courseBasePrice = (float) $course->price)
    @php($courseOfferPrice = $activeFlashSale ? $activeFlashSale->discountedPrice($courseBasePrice) : $courseBasePrice)
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Course') }}</span>
            <h1 style="margin: 12px 0 0;">{{ $course->title }}</h1>
        </div>
    </div>

    <style>
        .course-detail-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 20px;padding:8px;border:1px solid var(--border);border-radius:13px;background:#fff}
        .course-detail-tabs a{padding:9px 14px;border-radius:9px;color:#31536c;font-size:.85rem;font-weight:800}
        .course-detail-tabs a:hover{background:#edf4fb}
        .course-detail-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:18px 0}
        .course-detail-stat{padding:13px;border-radius:11px;background:#f0f6fb}
        .course-detail-stat strong{display:block;color:#153e62;font-size:1.15rem}
        .course-detail-stat small{color:var(--muted)}
        .course-demo-frame{position:relative;aspect-ratio:16/9;overflow:hidden;border-radius:15px;background:#10213d}
        .course-demo-frame iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
        @media(max-width:600px){.course-detail-stats{grid-template-columns:1fr 1fr}.course-detail-tabs{gap:4px}.course-detail-tabs a{padding:8px 10px}}
    </style>
    <nav class="course-detail-tabs" aria-label="{{ __('Course sections') }}">
        <a href="#overview">{{ __('Overview') }}</a>
        @if($demoLesson)
            <a href="#demo-class">{{ __('Demo Class') }}</a>
        @endif
        <a href="#course-content">{{ __('Course Content') }}</a>
        <a href="#enrollment">{{ __('Enroll') }}</a>
    </nav>

    @if($demoLesson?->post?->video_embed_url)
        <section id="demo-class" class="panel" style="margin-bottom:20px;">
            <div class="page-header" style="margin-bottom:14px;">
                <div><span class="badge info">{{ __('Free preview') }}</span><h2 style="margin:8px 0 0;">{{ __('Demo Class') }}</h2><p class="muted">{{ $demoLesson->post->title }}</p></div>
                <a class="btn secondary" href="{{ route('courses.content.show', [$course, $demoLesson->post]) }}">{{ __('Open demo lesson') }}</a>
            </div>
            <div class="course-demo-frame">
                <iframe src="{{ $demoLesson->post->video_embed_url }}" title="{{ $demoLesson->post->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
            </div>
        </section>
    @endif

    <div class="grid grid-2" id="overview">
        <div class="panel">
            @if($course->image_path)
                <img src="{{ \\Illuminate\\Support\\Facades\\Storage::disk('public')->url($course->image_path) }}" alt="{{ $course->title }}" loading="lazy" style="width:100%;max-height:300px;aspect-ratio:16/9;object-fit:cover;border-radius:14px;margin-bottom:16px;">
            @endif
            <p class="muted">{{ __('Instructor:') }} {{ $course->teacher?->name ?? __('N/A') }}</p>
            <div class="course-detail-stats">
                <div class="course-detail-stat"><strong>{{ number_format($studentCount) }}</strong><small>{{ __('Students') }}</small></div>
                <div class="course-detail-stat"><strong>{{ number_format($courseContentCount) }}</strong><small>{{ __('Lessons') }}</small></div>
                <div class="course-detail-stat"><strong>{{ $course->teacher?->name ? __('Available') : __('Coming soon') }}</strong><small>{{ __('Instructor') }}</small></div>
            </div>
            <p>{{ $course->description }}</p>
            @if(auth()->check() && auth()->user()->isAdmin())
                <a class="btn secondary" href="{{ route('admin.courses.lessons.index', $course) }}">{{ __('Manage lessons') }}</a>
            @elseif(auth()->check() && auth()->user()->isTeacher() && $course->teacher_id === auth()->id())
                <a class="btn secondary" href="{{ route('teacher.courses.lessons.index', $course) }}">{{ __('Manage lessons') }}</a>
            @endif
            <div class="meta-row">
                <span class="badge success">{{ __('Price:') }} BDT {{ number_format($courseOfferPrice, 2) }}</span>
                @if($activeFlashSale)<span class="badge warning">🔥 {{ $activeFlashSale->badge }} · {{ __('Save') }} ৳{{ number_format($courseBasePrice-$courseOfferPrice,2) }}</span><del class="muted">৳{{ number_format($courseBasePrice,2) }}</del>@endif
                <span class="badge warning">{{ __('Status:') }} {{ __(ucfirst($course->status)) }}</span>
            </div>
            @auth
                @if(auth()->user()->isStudent())
                    @if($isWishlisted)
                        <form method="POST" action="{{ route('student.wishlist.destroy', $course) }}" style="margin-top:14px;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn secondary">{{ __('Remove from wishlist') }}</button>
                            <a class="btn secondary" href="{{ route('student.wishlist.index') }}">{{ __('View wishlist') }}</a>
                        </form>
                    @else
                        <form method="POST" action="{{ route('student.wishlist.store', $course) }}" style="margin-top:14px;">
                            @csrf
                            <button type="submit" class="btn secondary">{{ __('Add to wishlist') }} ♡</button>
                        </form>
                    @endif
                @endif
            @endauth

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

        <div class="panel" id="enrollment">
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
                <a href="{{ route('login', ['continue' => route('courses.show', $course, false)]) }}" class="btn primary">{{ __('Login to enroll') }}</a>
            @endauth
        </div>
    </div>
@if($course->curriculumItems->isNotEmpty())
<section class="panel" style="margin-top:22px;">
    <div class="page-header" style="margin-bottom:12px;"><h2>{{ __('Course curriculum') }}</h2><span class="muted">{{ $course->curriculumItems->count() }} {{ __('lessons') }}</span></div>
    @foreach($course->curriculumItems as $item)
        @if($item->post && $item->post->status === 'published')
        <div class="course-item" style="display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:10px;">
            <div><strong>{{ $item->post->title }}</strong>
                @if($item->section_title)<div class="muted">{{ $item->section_title }}</div>@endif
                @if($item->post->short_description)<p class="muted" style="margin:5px 0 0;">{{ $item->post->short_description }}</p>@endif
                @if($item->duration_minutes)<small class="muted">{{ $item->duration_minutes }} {{ __('minutes') }}</small>@endif
            </div>
            @if($item->is_preview || $canViewLessons)
                <a class="btn secondary" href="{{ route('courses.content.show', [$course, $item->post]) }}">{{ $item->is_preview ? __('Preview lesson') : __('Open lesson') }}</a>
            @else
                <span class="badge warning">{{ __('Enroll to unlock') }}</span>
            @endif
        </div>
        @endif
    @endforeach
</section>
@endif
<section class="panel" style="margin-top:22px;">
    <div class="page-header" style="margin-bottom:10px;"><h2>{{ __('Course discussion') }}</h2><span class="badge info">{{ number_format($course->views_count) }} {{ __('views') }}</span></div>
    @auth
    <form method="POST" action="{{ route('courses.comments.store', $course) }}" style="margin-bottom:20px;">@csrf
        <label>{{ __('Write a comment or question') }}<textarea name="body" minlength="2" maxlength="2000" required></textarea></label>
        <button class="btn primary" type="submit">{{ __('Submit comment') }}</button>
        <small class="muted">{{ __('Comments appear after admin approval.') }}</small>
    </form>
    @else
    <p class="muted"><a href="{{ route('login', ['continue' => route('courses.show', $course, false)]) }}">{{ __('Log in') }}</a> {{ __('to join the discussion.') }}</p>
    @endauth
    @forelse($comments as $comment)
        <div class="course-item" style="margin-bottom:10px;"><strong>{{ $comment->author?->name ?? __('User') }}</strong><small class="muted"> · {{ $comment->created_at->format('d M Y') }}</small><p style="margin-bottom:0;">{{ $comment->body }}</p></div>
    @empty<p class="muted">{{ __('No approved comments yet.') }}</p>@endforelse
    {{ $comments->links() }}
</section>
@endsection
