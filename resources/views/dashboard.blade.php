@extends('layouts.app')

@section('title', '{{ __('Dashboard') }} | LMS Academy')

@section('content')
    @php
        $roleLabel = ucfirst($user->role);
    @endphp

    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Dashboard') }}</span>
            <h1 style="margin: 12px 0 0;">Hello, {{ $user->name }}</h1>
        </div>
        <span class="badge success">{{ $roleLabel }} account</span>
    </div>

    <div class="dash-summary">
        <div class="summary-box">
            <span class="muted">{{ __('Courses') }}</span>
            <strong>{{ $courses->count() }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Enrolled') }}</span>
            <strong>{{ $enrolled{{ __('Courses') }}->count() }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Certificates') }}</span>
            <strong>{{ $recent{{ __('Certificates') }}->count() }}</strong>
        </div>
        <div class="summary-box">
            <span class="muted">{{ __('Role') }}</span>
            <strong>{{ $roleLabel }}</strong>
        </div>
    </div>

    @if($user->role === 'admin')
        <div class="panel" style="margin-bottom: 20px;">
            <div class="page-header" style="margin-bottom: 0;">
                <div>
                    <span class="badge info">{{ __('Admin panel') }}</span>
                    <h2 style="margin: 12px 0 0;">{{ __('Operations overview') }}</h2>
                </div>
                <a href="{{ route('admin.courses.index') }}" class="btn primary">{{ __('Manage courses') }}</a>
            </div>

            <div class="grid grid-3" style="margin-top: 20px;">
                <div class="card">
                    <span class="badge info">{{ __('Homepage') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Landing page') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Edit hero copy, CTAs, and key stats for the public-facing homepage.') }}</p>
                    <a href="{{ route('admin.settings.index') }}" class="btn secondary" style="width: 100%;">{{ __('Open homepage') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Users') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Manage roles') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Review learners, teachers, and admins and change access levels.') }}</p>
                    <a href="{{ route('admin.users.index') }}" class="btn secondary" style="width: 100%;">{{ __('Open users') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Courses') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Course catalog') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Create, edit, publish, and manage course content and lessons.') }}</p>
                    <a href="{{ route('admin.courses.index') }}" class="btn secondary" style="width: 100%;">{{ __('Open courses') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Payments') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Review payments') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Verify bKash, Rocket, bank, and manual payment submissions before granting course access.') }}</p>
                    <a href="{{ route('admin.payments.index') }}" class="btn secondary" style="width: 100%;">{{ __('Open payments') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Marketing') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('SMS campaigns') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Send updates to verified learners who have opted in to academy messages.') }}</p>
                    <a href="{{ route('admin.sms-campaigns.index') }}" class="btn secondary" style="width: 100%;">{{ __('Manage SMS') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Settings') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('System settings') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Control core homepage configuration and brand messaging.') }}</p>
                    <a href="{{ route('admin.settings.index') }}" class="btn secondary" style="width: 100%;">{{ __('Open settings') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Analytics') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Platform insight') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Monitor learners, certificates, and catalog health from one view.') }}</p>
                    <a href="#analytics" class="btn secondary" style="width: 100%;">{{ __('Open analytics') }}</a>
                </div>
                <div class="card">
                    <span class="badge info">{{ __('Live') }}</span>
                    <h3 style="margin: 12px 0 8px;">{{ __('Public site') }}</h3>
                    <p class="muted" style="margin: 0 0 12px;">{{ __('Preview the configured public landing page as visitors see it.') }}</p>
                    <a href="{{ route('landing') }}" class="btn secondary" style="width: 100%;">{{ __('Preview home') }}</a>
                </div>
            </div>

            <div class="dash-summary" style="margin-top: 18px;">
                <div class="summary-box">
                    <span class="muted">{{ __('Students') }}</span>
                    <strong>{{ $stats['students'] }}</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">{{ __('Teachers') }}</span>
                    <strong>{{ $stats['teachers'] }}</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">{{ __('Certificates') }}</span>
                    <strong>{{ $stats['certificates'] }}</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">{{ __('Catalog') }}</span>
                    <strong>{{ $stats['courses'] }}</strong>
                </div>
            </div>
        </div>

        <div id="analytics" class="panel" style="margin-bottom: 20px;">
            <div class="page-header" style="margin-bottom: 0;">
                <div>
                    <span class="badge info">{{ __('Analytics') }}</span>
                    <h2 style="margin: 12px 0 0;">{{ __('Platform metrics') }}</h2>
                </div>
            </div>

            <div class="dash-summary" style="margin-top: 18px;">
                <div class="summary-box">
                    <span class="muted">{{ __('Completion rate') }}</span>
                    <strong>{{ $stats['courses'] > 0 ? round((min($stats['certificates'], $stats['courses']) / max($stats['courses'], 1)) * 100) : 0 }}%</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">{{ __('Learner base') }}</span>
                    <strong>{{ $stats['students'] + $stats['teachers'] }}</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">Active catalog</span>
                    <strong>{{ $stats['courses'] }}</strong>
                </div>
                <div class="summary-box">
                    <span class="muted">Issued certs</span>
                    <strong>{{ $stats['certificates'] }}</strong>
                </div>
            </div>
        </div>
    @else
        <div class="panel" style="margin-bottom: 20px;">
            <div class="page-header" style="margin-bottom: 0;">
                <div>
                    <span class="badge info">Quick start</span>
                    <h2 style="margin: 12px 0 0;">Your learning path</h2>
                </div>
                <a href="{{ route('courses.index') }}" class="btn secondary">Explore catalog</a>
            </div>
            <p class="muted" style="margin: 12px 0 0;">Keep momentum going with a clear set of next actions, from enrollment to course completion.</p>
        </div>
    @endif

    <div class="grid grid-2">
        <div class="panel">
            <h2 style="margin-top: 0;">{{ $user->role === 'teacher' ? 'Assigned courses' : 'Your enrolled courses' }}</h2>
            @if($enrolled{{ __('Courses') }}->isEmpty())
                <p class="muted">{{ $user->role === 'teacher' ? 'No courses assigned yet.' : 'You are not enrolled in any courses yet.' }}</p>
            @else
                <div class="course-list">
                    @foreach($enrolled{{ __('Courses') }} as $course)
                        <div class="course-item">
                            <div class="meta-row">
                                <div>
                                    <h3 style="margin: 0 0 8px;">{{ $course->title }}</h3>
                                    <div class="muted">Teacher: {{ $course->teacher?->name ?? 'N/A' }}</div>
                                </div>
                                <a href="{{ route('courses.show', $course) }}" class="btn secondary">Open</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel">
            <h2 style="margin-top: 0;">Recent certificates</h2>
            @if($recent{{ __('Certificates') }}->isEmpty())
                <p class="muted">No certificates issued yet.</p>
            @else
                @foreach($recent{{ __('Certificates') }} as $certificate)
                    <div class="course-item" style="margin-bottom: 12px;">
                        <strong>{{ $certificate->course->title }}</strong><br>
                        <span class="muted">{{ $certificate->certificate_number }}</span>
                        <div style="margin-top: 10px;">
                            <a href="{{ route('certificates.show', $certificate) }}" class="btn secondary">View</a>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
