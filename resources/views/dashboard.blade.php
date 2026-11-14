@extends('layouts.app')

@section('title', __('Admin Dashboard') . ' | Alom’s English Center')

@section('content')
    @include('components.dashboard-announcement-slider')
    @php
        $roleLabel = ucfirst($user->role);
        $isAdmin = $user->role === 'admin';
    @endphp

    @if($isAdmin)
        <style>
            .aec-admin { --aec-navy:#102b63; --aec-blue:#1769e8; --aec-muted:#64748b; color:#14213d; }
            .aec-admin * { box-sizing:border-box; }
            .aec-hero { display:flex; justify-content:space-between; align-items:center; gap:20px; padding:28px 30px; margin-bottom:22px; border-radius:20px; color:#fff; background:linear-gradient(115deg,#0d2b68,#0759c8 65%,#2388f5); box-shadow:0 14px 32px rgba(20,83,170,.18); }
            .aec-hero h1 { margin:8px 0; font-size:clamp(1.45rem,3vw,2.15rem); color:#fff; }
            .aec-hero p { margin:0; color:#e2edff; }
            .aec-hero-mark { font-size:58px; opacity:.9; }
            .aec-eyebrow { font-size:.76rem; text-transform:uppercase; letter-spacing:.13em; font-weight:800; color:#d8e7ff; }
            .aec-heading { display:flex; justify-content:space-between; align-items:end; gap:12px; margin:22px 0 13px; }
            .aec-heading h2 { margin:0; color:#142b55; font-size:1.18rem; }
            .aec-heading p { margin:4px 0 0; color:var(--aec-muted); font-size:.9rem; }
            .aec-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
            .aec-stat { padding:18px; border:1px solid #e1eaf8; border-radius:16px; background:#fff; box-shadow:0 5px 18px rgba(15,42,90,.045); }
            .aec-stat-top { display:flex; justify-content:space-between; align-items:center; gap:8px; color:#536782; font-size:.88rem; }
            .aec-stat-icon { display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:#eaf2ff; font-size:1.25rem; }
            .aec-stat strong { display:block; margin-top:11px; color:#102b63; font-size:1.85rem; line-height:1.1; }
            .aec-stat small { display:block; margin-top:6px; color:#7b8ba3; }
            .aec-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:13px; }
            .aec-link { display:flex; flex-direction:column; min-height:138px; padding:17px; border:1px solid #e1eaf8; border-radius:15px; background:#fff; color:#152b50; text-decoration:none; box-shadow:0 5px 18px rgba(15,42,90,.04); transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease; }
            .aec-link:hover { transform:translateY(-3px); border-color:#9bbdf5; box-shadow:0 12px 24px rgba(15,42,90,.09); }
            .aec-link-icon { display:grid; place-items:center; width:39px; height:39px; border-radius:12px; background:#edf4ff; font-size:1.25rem; }
            .aec-link strong { margin-top:12px; font-size:.98rem; }
            .aec-link span:last-child { margin-top:5px; color:#71819a; font-size:.82rem; line-height:1.4; }
            .aec-bottom { display:grid; grid-template-columns:1.2fr .8fr; gap:15px; margin-top:18px; }
            .aec-panel { padding:20px; border:1px solid #e1eaf8; border-radius:16px; background:#fff; }
            .aec-panel h3 { margin:0 0 13px; color:#142b55; font-size:1.05rem; }
            .aec-row { display:flex; justify-content:space-between; gap:12px; padding:11px 0; border-top:1px solid #edf1f7; }
            .aec-row:first-of-type { border-top:0; }
            .aec-row span { color:#71819a; font-size:.88rem; }
            .aec-row strong { color:#18335f; font-size:.9rem; text-align:right; }
            .aec-admin .btn { border-radius:10px; }
            @media(max-width:900px) { .aec-stats,.aec-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media(max-width:600px) { .aec-hero { padding:22px; } .aec-hero-mark { display:none; } .aec-stats,.aec-grid,.aec-bottom { grid-template-columns:1fr; } .aec-stat { padding:15px; } }
        </style>

        <div class="aec-admin">
            <section class="aec-hero">
                <div>
                    <span class="aec-eyebrow">Alom’s English Center · Learning Management System</span>
                    <h1>{{ __('Welcome back,') }} {{ $user->name }}!</h1>
                    <p>{{ __('Manage your academy, courses, learners and daily operations from one place.') }}</p>
                </div>
                <div class="aec-hero-mark" aria-hidden="true">📘</div>
            </section>

            <div class="aec-heading">
                <div><h2>{{ __('Analytics & academy overview') }}</h2><p>{{ __('Live totals from your current LMS records') }}</p></div>
                <span class="badge success">{{ $roleLabel }}</span>
            </div>

            <section class="aec-stats" aria-label="{{ __('Academy statistics') }}">
                <article class="aec-stat"><div class="aec-stat-top"><span>{{ __('Total students') }}</span><span class="aec-stat-icon">🎓</span></div><strong>{{ number_format($stats['students']) }}</strong><small>{{ __('Registered student accounts') }}</small></article>
                <article class="aec-stat"><div class="aec-stat-top"><span>{{ __('Total teachers') }}</span><span class="aec-stat-icon">👩‍🏫</span></div><strong>{{ number_format($stats['teachers']) }}</strong><small>{{ __('Teacher accounts') }}</small></article>
                <article class="aec-stat"><div class="aec-stat-top"><span>{{ __('Total courses') }}</span><span class="aec-stat-icon">📚</span></div><strong>{{ number_format($stats['courses']) }}</strong><small>{{ __('All courses in the catalog') }}</small></article>
                <article class="aec-stat"><div class="aec-stat-top"><span>{{ __('Certificates') }}</span><span class="aec-stat-icon">🏅</span></div><strong>{{ number_format($stats['certificates']) }}</strong><small>{{ __('Certificates issued') }}</small></article>
            </section>

            <div class="aec-heading"><div><h2>{{ __('Administration') }}</h2><p>{{ __('Open a module to manage that part of your academy') }}</p></div></div>
            <section class="aec-grid">
                <a class="aec-link" href="{{ route('admin.courses.index') }}"><span class="aec-link-icon">📚</span><strong>{{ __('Courses & lessons') }}</strong><span>{{ __('Create courses and manage learning content') }}</span></a>
                <a class="aec-link" href="{{ route('admin.users.index') }}"><span class="aec-link-icon">👥</span><strong>{{ __('Users & roles') }}</strong><span>{{ __('Manage student, teacher and staff accounts') }}</span></a>
                <a class="aec-link" href="{{ route('admin.batches.index') }}"><span class="aec-link-icon">🗓️</span><strong>{{ __('Batches & branches') }}</strong><span>{{ __('Organize classes, batches and branches') }}</span></a>
                <a class="aec-link" href="{{ route('admin.payments.index') }}"><span class="aec-link-icon">💳</span><strong>{{ __('Payments') }}</strong><span>{{ __('Review submitted course payments') }}</span></a>
                <a class="aec-link" href="{{ route('live-classes.index') }}"><span class="aec-link-icon">🎥</span><strong>{{ __('Live classes') }}</strong><span>{{ __('View scheduled online classes') }}</span></a>
                <a class="aec-link" href="{{ route('admin.website-builder.index') }}"><span class="aec-link-icon">🖥️</span><strong>{{ __('Website builder') }}</strong><span>{{ __('Manage public website content') }}</span></a>
                <a class="aec-link" href="{{ route('admin.media.index') }}"><span class="aec-link-icon">🖼️</span><strong>{{ __('Media library') }}</strong><span>{{ __('Upload and organize media files') }}</span></a>
                <a class="aec-link" href="{{ route('admin.news.index') }}"><span class="aec-link-icon">📰</span><strong>{{ __('Blog & news') }}</strong><span>{{ __('Publish academy news and articles') }}</span></a>
                <a class="aec-link" href="{{ route('admin.backups.index') }}"><span class="aec-link-icon">💾</span><strong>{{ __('Backup & restore') }}</strong><span>{{ __('Create and manage system backups') }}</span></a>
                <a class="aec-link" href="{{ route('admin.updates.index') }}"><span class="aec-link-icon">🔄</span><strong>{{ __('System updates') }}</strong><span>{{ __('Check trusted LMS releases and update history') }}</span></a>
                <a class="aec-link" href="{{ route('admin.activity-logs.index') }}"><span class="aec-link-icon">📋</span><strong>{{ __('Activity logs') }}</strong><span>{{ __('Review recorded account activity') }}</span></a>
                <a class="aec-link" href="{{ route('admin.invoices.index') }}"><span class="aec-link-icon">🧾</span><strong>{{ __('Invoices') }}</strong><span>{{ __('Manage invoices and fee records') }}</span></a>
                <a class="aec-link" href="{{ route('admin.settings.index') }}"><span class="aec-link-icon">⚙️</span><strong>{{ __('System settings') }}</strong><span>{{ __('Configure academy and platform settings') }}</span></a>
                <a class="aec-link" href="{{ route('admin.sms-campaigns.index') }}"><span class="aec-link-icon">✉️</span><strong>{{ __('SMS campaigns') }}</strong><span>{{ __('Manage learner communication campaigns') }}</span></a>
                <a class="aec-link" href="{{ route('landing') }}"><span class="aec-link-icon">🌐</span><strong>{{ __('Homepage / Public website') }}</strong><span>{{ __('Open the visitor-facing homepage') }}</span></a>
            </section>

            <section class="aec-bottom">
                <div class="aec-panel">
                    <h3>{{ __('Quick actions') }}</h3>
                    <div class="aec-row"><span>{{ __('Create a course') }}</span><strong><a href="{{ route('admin.courses.create') }}">{{ __('Open') }} →</a></strong></div>
                    <div class="aec-row"><span>{{ __('Manage learners and staff') }}</span><strong><a href="{{ route('admin.users.index') }}">{{ __('Open') }} →</a></strong></div>
                    <div class="aec-row"><span>{{ __('Review pending payments') }}</span><strong><a href="{{ route('admin.payments.index') }}">{{ __('Open') }} →</a></strong></div>
                    <div class="aec-row"><span>{{ __('Create a system backup') }}</span><strong><a href="{{ route('admin.backups.index') }}">{{ __('Open') }} →</a></strong></div>
                </div>
                <div class="aec-panel">
                    <h3>{{ __('System snapshot') }}</h3>
                    <div class="aec-row"><span>{{ __('Signed-in account') }}</span><strong>{{ $user->name }}</strong></div>
                    <div class="aec-row"><span>{{ __('Access role') }}</span><strong>{{ $roleLabel }}</strong></div>
                    <div class="aec-row"><span>{{ __('Published course list') }}</span><strong>{{ number_format($courses->count()) }}</strong></div>
                    <div class="aec-row"><span>{{ __('Recent certificates shown') }}</span><strong>{{ number_format($recentCertificates->count()) }}</strong></div>
                </div>
            </section>
        </div>
    @else
        <div class="page-header">
            <div><span class="badge info">{{ __('Dashboard') }}</span><h1 style="margin:12px 0 0;">{{ __('Hello,') }} {{ $user->name }}</h1></div>
            <span class="badge success">{{ $roleLabel }} {{ __('account') }}</span>
        </div>
        <div class="dash-summary">
            <div class="summary-box"><span class="muted">{{ __('Courses') }}</span><strong>{{ $courses->count() }}</strong></div>
            <div class="summary-box"><span class="muted">{{ __('Enrolled') }}</span><strong>{{ $enrolledCourses->count() }}</strong></div>
            <div class="summary-box"><span class="muted">{{ __('Certificates') }}</span><strong>{{ $recentCertificates->count() }}</strong></div>
            <div class="summary-box"><span class="muted">{{ __('Role') }}</span><strong>{{ $roleLabel }}</strong></div>
        </div>
        <div class="panel" style="margin-bottom:20px;">
            <div class="page-header" style="margin-bottom:0;"><div><span class="badge info">{{ __('Quick start') }}</span><h2 style="margin:12px 0 0;">{{ __('Your learning path') }}</h2></div>@if($user->isStudent())<a href="{{ route('student.courses.index') }}" class="btn primary">{{ __('My Courses & Progress') }}</a>@endif
                @if($user->isStudent())
                    <a href="{{ route('student.question-set-attempts.index') }}" class="btn secondary">{{ __('Quiz attempts') }}</a>
                @endif
                <a href="{{ route('courses.index') }}" class="btn secondary">{{ __('Explore catalog') }}</a></div>
            <p class="muted" style="margin:12px 0 0;">{{ __('Keep momentum going with a clear set of next actions, from enrollment to course completion.') }}</p>
        </div>
    @endif

    <div class="grid grid-2">
        <div class="panel">
            <h2 style="margin-top:0;">{{ $user->role === 'teacher' ? __('Assigned courses') : __('Your enrolled courses') }}</h2>
            @if($enrolledCourses->isEmpty())
                <p class="muted">{{ $user->role === 'teacher' ? __('No courses assigned yet.') : __('You are not enrolled in any courses yet.') }}</p>
            @else
                <div class="course-list">
                    @foreach($enrolledCourses as $course)
                        <div class="course-item"><div class="meta-row"><div><h3 style="margin:0 0 8px;">{{ $course->title }}</h3><div class="muted">{{ __('Teacher:') }} {{ optional($course->teacher)->name ?? 'N/A' }}</div></div><a href="{{ route('courses.show', $course) }}" class="btn secondary">{{ __('Open') }}</a></div></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="panel">
            <h2 style="margin-top:0;">{{ __('Recent certificates') }}</h2>
            @if($recentCertificates->isEmpty())
                <p class="muted">{{ __('No certificates issued yet.') }}</p>
            @else
                @foreach($recentCertificates as $certificate)
                    <div class="course-item" style="margin-bottom:12px;"><strong>{{ $certificate->course->title }}</strong><br><span class="muted">{{ $certificate->certificate_number }}</span><div style="margin-top:10px;"><a href="{{ route('certificates.show', $certificate) }}" class="btn secondary">{{ __('View') }}</a></div></div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
