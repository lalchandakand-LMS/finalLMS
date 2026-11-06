@extends('layouts.app')

@section('title', \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::value('seo_title', config('app.name')) : config('app.name'))

@section('content')
    @php
        $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('settings');
        $hasCoursesTable = \Illuminate\Support\Facades\Schema::hasTable('courses');
        $hasUsersTable = \Illuminate\Support\Facades\Schema::hasTable('users');
        $hasCertificatesTable = \Illuminate\Support\Facades\Schema::hasTable('certificates');

        $homeTitle = $hasSettingsTable ? \App\Models\Setting::value('home_title', 'Grow your skills with a complete LMS experience.') : 'Grow your skills with a complete LMS experience.';
        $homeSubtitle = $hasSettingsTable ? \App\Models\Setting::value('home_subtitle', 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.') : 'Learn from expert-led courses, track your progress, enroll in structured programs, and earn certificates that help you move forward with confidence.';
        $homePrimaryLabel = $hasSettingsTable ? \App\Models\Setting::value('home_cta_primary', 'Browse courses') : 'Browse courses';
        $homeSecondaryLabel = $hasSettingsTable ? \App\Models\Setting::value('home_cta_secondary', 'Login') : 'Login';
        $homeFeaturedTitle = $hasSettingsTable ? \App\Models\Setting::value('home_featured_title', 'Popular learning tracks') : 'Popular learning tracks';
        $homeReasonTitle = $hasSettingsTable ? \App\Models\Setting::value('home_reason_title', 'Built to keep you moving forward') : 'Built to keep you moving forward';

        $metricOneValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_1_value', '12+') : '12+';
        $metricOneLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_1_label', 'Active courses') : 'Active courses';
        $metricTwoValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_2_value', '4.9/5') : '4.9/5';
        $metricTwoLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_2_label', 'Learner score') : 'Learner score';
        $metricThreeValue = $hasSettingsTable ? \App\Models\Setting::value('home_metric_3_value', '24/7') : '24/7';
        $metricThreeLabel = $hasSettingsTable ? \App\Models\Setting::value('home_metric_3_label', 'Support') : 'Support';

        $courseCount = $hasCoursesTable ? \App\Models\Course::count() : 0;
        $studentCount = $hasUsersTable ? \App\Models\User::where('role', 'student')->count() : 0;
        $certificateCount = $hasCertificatesTable ? \App\Models\Certificate::count() : 0;
        $featuredCourses = $hasCoursesTable ? \App\Models\Course::with('teacher')->withCount('lessons')->latest()->take(3)->get() : collect();
    @endphp

    <section class="hero">
        <div>
            <span class="badge info">{{ config('app.name') }}</span>
            <h1>{{ $homeTitle }}</h1>
            <p>{{ $homeSubtitle }}</p>
            <div class="cta-row">
                <a href="{{ route('courses.index') }}" class="btn primary">{{ $homePrimaryLabel }}</a>
                @guest
                    <a href="{{ route('login') }}" class="btn secondary">{{ $homeSecondaryLabel }}</a>
                @endguest
            </div>

            <div class="stats">
                <div class="stat">
                    <strong>{{ $metricOneValue ?: $courseCount }}</strong>
                    <span class="muted">{{ $metricOneLabel }}</span>
                </div>
                <div class="stat">
                    <strong>{{ $metricTwoValue ?: $studentCount }}</strong>
                    <span class="muted">{{ $metricTwoLabel }}</span>
                </div>
                <div class="stat">
                    <strong>{{ $metricThreeValue ?: $certificateCount }}</strong>
                    <span class="muted">{{ $metricThreeLabel }}</span>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 style="margin-top:0;">Platform overview</h2>
            <div class="grid grid-2">
                <div class="card">
                    <h3>For students</h3>
                    <p class="muted">Enroll in courses, follow structured lessons, and earn digital certificates.</p>
                </div>
                <div class="card">
                    <h3>For teachers</h3>
                    <p class="muted">Manage learning tracks, create lesson flow, and keep learners progressing.</p>
                </div>
                <div class="card">
                    <h3>For admins</h3>
                    <p class="muted">Review course access, maintain enrollment health, and control platform operations.</p>
                </div>
                <div class="card">
                    <h3>Built for growth</h3>
                    <p class="muted">The experience is designed to feel clean, polished, and easy to use daily.</p>
                </div>
            </div>
        </div>
    </section>

    <section style="margin-top: 28px;">
        <div class="page-header" style="margin-bottom: 16px;">
            <div>
                <span class="badge info">Featured</span>
                <h2 style="margin: 12px 0 0;">{{ $homeFeaturedTitle }}</h2>
            </div>
        </div>

        <div class="grid grid-3">
            @foreach($featuredCourses as $course)
                <div class="card">
                    <span class="badge success">{{ ucfirst($course->status) }}</span>
                    <h3 style="margin: 14px 0 8px;">{{ $course->title }}</h3>
                    <p class="muted" style="margin: 0 0 14px;">{{ \Illuminate\Support\Str::limit($course->description, 100) }}</p>
                    <div class="muted">Teacher: {{ $course->teacher?->name ?? 'N/A' }}</div>
                    <div class="meta-row" style="margin-top: 14px;">
                        <span class="muted">{{ $course->lessons_count }} lessons</span>
                        <strong>BDT {{ number_format($course->price, 2) }}</strong>
                    </div>
                    <div style="margin-top: 16px;">
                        <a href="{{ route('courses.show', $course) }}" class="btn secondary" style="width: 100%;">View course</a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section style="margin-top: 28px;">
        <div class="page-header" style="margin-bottom: 16px;">
            <div>
                <span class="badge info">Why learners stay</span>
                <h2 style="margin: 12px 0 0;">{{ $homeReasonTitle }}</h2>
            </div>
        </div>

        <div class="grid grid-3">
            <div class="card">
                <h3>Flexible learning</h3>
                <p class="muted">Learn at your own pace with lessons that fit your schedule and workflow.</p>
            </div>
            <div class="card">
                <h3>Clear progress</h3>
                <p class="muted">Track enrollments, completions, and certificate progress in one dashboard.</p>
            </div>
            <div class="card">
                <h3>Professional structure</h3>
                <p class="muted">Course pathways feel organized and simple for students, teachers, and admins alike.</p>
            </div>
        </div>
    </section>

    @php
        $builderSections = $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('website_sections', '[]'), true) : [];
        $builderSections = is_array($builderSections) ? $builderSections : [];
    @endphp
    @foreach($builderSections as $section)
        @if(($section['enabled'] ?? false) && (trim($section['title'] ?? '') !== '' || trim($section['body'] ?? '') !== ''))
            @php($sectionImage = !empty($section['image_id']) ? \App\Models\MediaAsset::find($section['image_id']) : null)
            <section class="panel" style="margin-top:28px;">
                <div class="grid grid-2" style="align-items:center;">
                    <div>
                        @if(!empty($section['title']))<h2>{{ $section['title'] }}</h2>@endif
                        @if(!empty($section['body']))<p style="white-space:pre-line;line-height:1.8;">{{ $section['body'] }}</p>@endif
                        @if(!empty($section['button_label']) && !empty($section['button_url']))
                            <a class="btn primary" href="{{ $section['button_url'] }}" rel="nofollow noopener">{{ $section['button_label'] }}</a>
                        @endif
                    </div>
                    @if($sectionImage && str_starts_with($sectionImage->mime_type, 'image/'))
                        <div><img src="{{ $sectionImage->url }}" alt="{{ $sectionImage->alt_text }}" loading="lazy" style="width:100%;max-height:340px;object-fit:cover;border-radius:14px;"></div>
                    @endif
                </div>
            </section>
        @endif
    @endforeach

@endsection
