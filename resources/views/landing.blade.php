@extends('layouts.app')

@section('title', \Illuminate\Support\Facades\Schema::hasTable('settings') ? \App\Models\Setting::value('seo_title', config('app.name')) : config('app.name'))

@section('content')
    @php
        $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('settings');
        $hasCoursesTable = \Illuminate\Support\Facades\Schema::hasTable('courses');
        $hasUsersTable = \Illuminate\Support\Facades\Schema::hasTable('users');
        $hasCertificatesTable = \Illuminate\Support\Facades\Schema::hasTable('certificates');

        $siteName = $hasSettingsTable ? \App\Models\Setting::value('site_name', config('app.name')) : config('app.name');
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
        $storedSlides = $hasSettingsTable ? json_decode((string) \App\Models\Setting::value('home_slides', '[]'), true) : [];
        $homeSlides = collect(is_array($storedSlides) ? $storedSlides : [])->filter(fn ($slide) => is_array($slide) && !empty($slide['enabled']) && !empty($slide['image_id']))->map(function ($slide) {
            $asset = \App\Models\MediaAsset::query()->whereKey($slide['image_id'])->where('mime_type', 'like', 'image/%')->first();
            return $asset ? ['asset' => $asset, 'title' => $slide['title'] ?? '', 'subtitle' => $slide['subtitle'] ?? '', 'button_label' => $slide['button_label'] ?? '', 'button_url' => $slide['button_url'] ?? ''] : null;
        })->filter()->values();
        $homepageTemplate = (string) ($hasSettingsTable ? \App\Models\Setting::value('homepage_template', 'academy-modern') : 'academy-modern');
        $homepageTemplate = array_key_exists($homepageTemplate, \App\Http\Controllers\WebsiteBuilderController::homepageTemplates()) ? $homepageTemplate : 'academy-modern';
    @endphp

    <div class="homepage-template homepage-template--{{ $homepageTemplate }}">
    <style>
        .homepage-template { --template-accent: var(--primary); --template-surface: #fff; --template-radius: 20px; }
        .homepage-template .hero { gap: clamp(20px,4vw,48px); }
        .homepage-template .hero > div:first-child { padding: clamp(8px,2vw,22px); }
        .homepage-template .hero h1 { letter-spacing: -.035em; }
        .homepage-template .panel, .homepage-template .card { border-radius: var(--template-radius); }
        .homepage-template .panel { border-color: color-mix(in srgb, var(--template-accent) 16%, #dbe2ee); }
        .homepage-template .btn.primary { background: var(--template-accent); }
        .homepage-template .badge.info { background: color-mix(in srgb, var(--template-accent) 12%, white); color: var(--template-accent); }
        .homepage-template .stat { border-radius: calc(var(--template-radius) * .7); }
        .homepage-template--academy-modern { --template-accent:#2457c5; --template-radius:20px; }
        .homepage-template--academy-modern .hero { background:linear-gradient(135deg,#eff6ff,#fff 60%); border-radius:26px; padding:clamp(18px,3vw,32px); }
        .homepage-template--bold-academy { --template-accent:#172554; --template-radius:10px; }
        .homepage-template--bold-academy .hero { background:#172554; color:#fff; border-radius:10px; padding:clamp(24px,4vw,48px); }
        .homepage-template--bold-academy .hero h1,.homepage-template--bold-academy .hero p { color:#fff; }
        .homepage-template--minimal-learning { --template-accent:#0f766e; --template-radius:8px; }
        .homepage-template--minimal-learning .hero { background:#f8fafc; padding:clamp(16px,3vw,30px); border-left:5px solid var(--template-accent); }
        .homepage-template--minimal-learning .card,.homepage-template--minimal-learning .panel { box-shadow:none; }
        .homepage-template--editorial { --template-accent:#92400e; --template-radius:4px; }
        .homepage-template--editorial .hero { grid-template-columns:1.3fr .7fr; border-top:5px solid var(--template-accent); padding:28px 0; }
        .homepage-template--editorial .hero h1 { font-family:Georgia,serif; font-weight:600; }
        .homepage-template--gradient-studio { --template-accent:#7c3aed; --template-radius:26px; }
        .homepage-template--gradient-studio .hero { background:linear-gradient(125deg,#ede9fe,#dbeafe 55%,#fce7f3); padding:clamp(20px,4vw,40px); border-radius:30px; }
        .homepage-template--gradient-studio .card { background:linear-gradient(145deg,#fff,#f5f3ff); }
        .homepage-template--corporate-training { --template-accent:#075985; --template-radius:6px; }
        .homepage-template--corporate-training .hero { background:#eaf2f8; padding:28px; border-bottom:5px solid var(--template-accent); }
        .homepage-template--corporate-training .card { border-top:3px solid var(--template-accent); }
        .homepage-template--playful-classroom { --template-accent:#db2777; --template-radius:28px; }
        .homepage-template--playful-classroom .hero { background:#fff1f2; border-radius:36px 12px 36px 12px; padding:28px; }
        .homepage-template--playful-classroom .card { border:2px solid #fbcfe8; }
        .homepage-template--midnight-campus { --template-accent:#38bdf8; --template-radius:16px; color:#e2e8f0; background:#0f172a; padding:22px; border-radius:24px; }
        .homepage-template--midnight-campus .hero { background:#1e293b; color:#f8fafc; padding:28px; border-radius:18px; }
        .homepage-template--midnight-campus .hero h1,.homepage-template--midnight-campus .hero p,.homepage-template--midnight-campus h2,.homepage-template--midnight-campus h3 { color:#f8fafc; }
        .homepage-template--midnight-campus .panel,.homepage-template--midnight-campus .card,.homepage-template--midnight-campus .stat { background:#1e293b; color:#e2e8f0; border-color:#334155; }
        .homepage-template--midnight-campus .muted { color:#cbd5e1; }
        .homepage-template--split-screen { --template-accent:#4338ca; --template-radius:14px; }
        .homepage-template--split-screen .hero { grid-template-columns:1fr 1fr; gap:0; background:#eef2ff; border-radius:16px; overflow:hidden; }
        .homepage-template--split-screen .hero > div:first-child { padding:clamp(24px,4vw,46px); }
        .homepage-template--split-screen .hero > .panel { margin:0; border:0; border-radius:0; }
        .homepage-template--classic-institute { --template-accent:#166534; --template-radius:2px; }
        .homepage-template--classic-institute .hero { border-top:4px double var(--template-accent); border-bottom:4px double var(--template-accent); padding:28px 8px; }
        .homepage-template--classic-institute .hero h1 { font-family:Georgia,serif; }
        .homepage-template--glass-campus { --template-accent:#0e7490; --template-radius:22px; }
        .homepage-template--glass-campus .hero { background:linear-gradient(135deg,#cffafeaa,#e0f2feaa); padding:28px; border:1px solid #bae6fd; }
        .homepage-template--glass-campus .panel,.homepage-template--glass-campus .card { background:#ffffffd9; backdrop-filter:blur(12px); border:1px solid #cbd5e1; }
        .homepage-template--vibrant-skills { --template-accent:#ea580c; --template-radius:18px; }
        .homepage-template--vibrant-skills .hero { background:linear-gradient(120deg,#ffedd5,#fef3c7); padding:28px; }
        .homepage-template--vibrant-skills .stat { background:#fff7ed; border:1px solid #fed7aa; }
        .homepage-template--focus-learning { --template-accent:#4338ca; --template-radius:12px; }
        .homepage-template--focus-learning .hero { grid-template-columns:1fr; max-width:900px; margin-inline:auto; text-align:center; }
        .homepage-template--focus-learning .hero .cta-row { justify-content:center; }
        .homepage-template--focus-learning .hero .stats { max-width:650px; margin:24px auto 0; }
        .homepage-template--elegant-academy { --template-accent:#9f1239; --template-radius:12px; }
        .homepage-template--elegant-academy .hero { background:#fff1f2; padding:30px; border:1px solid #fecdd3; }
        .homepage-template--elegant-academy .hero h1 { font-family:Georgia,serif; font-weight:500; }
        .homepage-template--community-campus { --template-accent:#0f766e; --template-radius:24px; }
        .homepage-template--community-campus .hero { background:linear-gradient(135deg,#ccfbf1,#f0fdfa); padding:30px; border-radius:32px 32px 12px 32px; }
        .homepage-template--community-campus .card { border-bottom:4px solid #99f6e4; }
        @media(max-width:700px) {
            .homepage-template--editorial .hero,.homepage-template--split-screen .hero { grid-template-columns:1fr; }
            .homepage-template--midnight-campus { padding:10px; }
            .homepage-template--focus-learning .hero { text-align:left; }
            .homepage-template--focus-learning .hero .cta-row { justify-content:flex-start; }
        }
    </style>

    @if($homeSlides->isNotEmpty())
        <section aria-label="{{ __('Homepage highlights') }}" style="margin-bottom:28px;">
            <div id="home-slider-track" style="display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;border-radius:22px;gap:12px;">
                @foreach($homeSlides as $index => $slide)
                    <article id="home-slide-{{ $index + 1 }}" style="position:relative;isolation:isolate;flex:0 0 100%;min-height:320px;display:flex;align-items:center;overflow:hidden;border-radius:22px;background:#172554;scroll-snap-align:start;">
                        <img src="{{ $slide['asset']->url }}" alt="{{ $slide['asset']->alt_text ?: $slide['title'] }}" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2;">
                        <div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,15,35,.88),rgba(8,15,35,.56) 55%,rgba(8,15,35,.12));z-index:-1;"></div>
                        <div style="padding:clamp(28px,6vw,64px);max-width:760px;color:#fff;">
                            @if($slide['title'] !== '')<h2 style="font-size:clamp(28px,4vw,48px);line-height:1.12;margin:0 0 14px;color:#fff;">{{ $slide['title'] }}</h2>@endif
                            @if($slide['subtitle'] !== '')<p style="font-size:clamp(15px,1.6vw,19px);line-height:1.7;margin:0 0 22px;color:rgba(255,255,255,.9);">{{ $slide['subtitle'] }}</p>@endif
                            @if($slide['button_label'] !== '' && $slide['button_url'] !== '')
                                <a class="btn primary" href="{{ $slide['button_url'] }}" rel="noopener">{{ $slide['button_label'] }}</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if($homeSlides->count() > 1)
                <nav aria-label="{{ __('Slider navigation') }}" style="display:flex;justify-content:center;gap:8px;margin-top:12px;">
                    @foreach($homeSlides as $index => $slide)
                        <a href="#home-slide-{{ $index + 1 }}" aria-label="{{ __('Go to slide') }} {{ $index + 1 }}" style="display:block;width:11px;height:11px;border-radius:50%;background:var(--primary);opacity:.7;"></a>
                    @endforeach
                </nav>
            @endif
        </section>
    @endif

    <section class="hero">
        <div>
            <span class="badge info">{{ $siteName }}</span>
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
            <h2 style="margin-top:0;">{{ __('Platform overview') }}</h2>
            <div class="grid grid-2">
                <div class="card">
                    <h3>{{ __('For students') }}</h3>
                    <p class="muted">{{ __('Enroll in courses, follow structured lessons, and earn digital certificates.') }}</p>
                </div>
                <div class="card">
                    <h3>{{ __('For teachers') }}</h3>
                    <p class="muted">{{ __('Manage learning tracks, create lesson flow, and keep learners progressing.') }}</p>
                </div>
                <div class="card">
                    <h3>{{ __('For admins') }}</h3>
                    <p class="muted">{{ __('Review course access, maintain enrollment health, and control platform operations.') }}</p>
                </div>
                <div class="card">
                    <h3>{{ __('Built for growth') }}</h3>
                    <p class="muted">{{ __('The experience is designed to feel clean, polished, and easy to use daily.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section style="margin-top: 28px;">
        <div class="page-header" style="margin-bottom: 16px;">
            <div>
                <span class="badge info">{{ __('Featured') }}</span>
                <h2 style="margin: 12px 0 0;">{{ $homeFeaturedTitle }}</h2>
            </div>
        </div>

        <div class="grid grid-3">
            @foreach($featuredCourses as $course)
                <div class="card">
                    <span class="badge success">{{ ucfirst($course->status) }}</span>
                    <h3 style="margin: 14px 0 8px;">{{ $course->title }}</h3>
                    <p class="muted" style="margin: 0 0 14px;">{{ \Illuminate\Support\Str::limit($course->description, 100) }}</p>
                    <div class="muted">{{ __('Teacher:') }} {{ $course->teacher?->name ?? 'N/A' }}</div>
                    <div class="meta-row" style="margin-top: 14px;">
                        <span class="muted">{{ $course->lessons_count }} {{ __('lessons') }}</span>
                        <strong>BDT {{ number_format($course->price, 2) }}</strong>
                    </div>
                    <div style="margin-top: 16px;">
                        <a href="{{ route('courses.show', $course) }}" class="btn secondary" style="width: 100%;">{{ __('View course') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section style="margin-top: 28px;">
        <div class="page-header" style="margin-bottom: 16px;">
            <div>
                <span class="badge info">{{ __('Why learners stay') }}</span>
                <h2 style="margin: 12px 0 0;">{{ $homeReasonTitle }}</h2>
            </div>
        </div>

        <div class="grid grid-3">
            <div class="card">
                <h3>{{ __('Flexible learning') }}</h3>
                <p class="muted">{{ __('Learn at your own pace with lessons that fit your schedule and workflow.') }}</p>
            </div>
            <div class="card">
                <h3>{{ __('Clear progress') }}</h3>
                <p class="muted">{{ __('Track enrollments, completions, and certificate progress in one dashboard.') }}</p>
            </div>
            <div class="card">
                <h3>{{ __('Professional structure') }}</h3>
                <p class="muted">{{ __('Course pathways feel organized and simple for students, teachers, and admins alike.') }}</p>
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
    </div>

@endsection
