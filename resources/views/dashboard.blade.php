@extends('layouts.app')

@section('title', __('Admin Dashboard') . ' | ' . $institutionName)

@section('content')
    @include('components.dashboard-announcement-slider')
    @php
        $roleLabel = ucfirst($user->role);
        $isAdmin = $user->role === 'admin';
    @endphp

    @if($isAdmin)
        <style>
            .aec-admin { --aec-primary:{{ $dashboardAppearance['primary'] }}; --aec-secondary:{{ $dashboardAppearance['secondary'] }}; --aec-accent:{{ $dashboardAppearance['accent'] }}; --aec-background:{{ $dashboardAppearance['background'] }}; --aec-surface:{{ $dashboardAppearance['surface'] }}; --aec-stat-students:{{ $dashboardAppearance['students_color'] }}; --aec-stat-teachers:{{ $dashboardAppearance['teachers_color'] }}; --aec-stat-courses:{{ $dashboardAppearance['courses_color'] }}; --aec-stat-certificates:{{ $dashboardAppearance['certificates_color'] }}; --aec-ink:#10234e; --aec-muted:#687895; --aec-line:#e4ebfa; color:var(--aec-ink); background:var(--aec-background); padding:4px; border-radius:22px; }
            .aec-admin * { box-sizing:border-box; }
            .aec-admin[data-theme="dark"] { --aec-ink:#e8efff; --aec-muted:#a7b7d6; --aec-line:#26385e; color:var(--aec-ink); background:#0b1530; }
            .aec-admin[data-theme="dark"] .aec-panel,
            .aec-admin[data-theme="dark"] .aec-stat { background:#101d3b; border-color:var(--aec-line); color:var(--aec-ink); }
            .aec-admin[data-theme="dark"] .aec-heading h2,
            .aec-admin[data-theme="dark"] .aec-panel h3,
            .aec-admin[data-theme="dark"] .aec-stat strong,
            .aec-admin[data-theme="dark"] .aec-snapshot-row strong { color:#edf3ff; }
            .aec-admin[data-theme="dark"] .aec-stat-top,
            .aec-admin[data-theme="dark"] .aec-stat small,
            .aec-admin[data-theme="dark"] .aec-snapshot-row span { color:#a7b7d6; }
            .aec-admin[data-theme="dark"] .aec-action,
            .aec-admin[data-theme="dark"] .aec-module { background:#142447; border-color:#2a3e68; color:#e8efff; }
            .aec-admin[data-theme="dark"] .aec-action small { color:#a7b7d6; }
            .aec-admin[data-shadow="medium"] .aec-panel,
            .aec-admin[data-shadow="medium"] .aec-stat { box-shadow:0 14px 32px rgba(12,35,82,.13); }
            .aec-admin[data-shadow="glow"] .aec-panel,
            .aec-admin[data-shadow="glow"] .aec-stat { box-shadow:0 0 0 1px color-mix(in srgb,var(--aec-secondary) 18%,transparent),0 12px 34px color-mix(in srgb,var(--aec-secondary) 22%,transparent); }
            .aec-admin .aec-hero,.aec-admin .aec-stat,.aec-admin .aec-panel,.aec-admin .aec-action,.aec-admin .aec-module { border-radius:var(--aec-radius); }
            .aec-admin-shell { display:grid; gap:18px; }
            .aec-hero { position:relative; isolation:isolate; overflow:hidden; display:flex; align-items:center; justify-content:space-between; gap:24px; min-height:190px; padding:30px clamp(22px,4vw,42px); border-radius:24px; color:#fff; background:radial-gradient(circle at 80% 20%,rgba(54,178,255,.4),transparent 28%),linear-gradient(115deg,var(--aec-primary) 0%,var(--aec-secondary) 100%); box-shadow:0 18px 42px rgba(14,62,151,.22); }
            .aec-hero:after { content:""; position:absolute; z-index:-1; width:330px; height:330px; right:8%; top:-190px; border:1px solid rgba(255,255,255,.2); border-radius:50%; box-shadow:0 0 0 34px rgba(255,255,255,.04),0 0 0 72px rgba(255,255,255,.035); }
            .aec-hero-copy { max-width:760px; }
            .aec-eyebrow { display:inline-flex; align-items:center; gap:8px; padding:6px 11px; border:1px solid rgba(255,255,255,.25); border-radius:999px; background:rgba(255,255,255,.1); color:#dbeafe; font-size:.74rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
            .aec-hero h1 { margin:13px 0 7px; color:#fff; font-size:clamp(1.55rem,3vw,2.45rem); line-height:1.12; letter-spacing:-.035em; }
            .aec-hero p { max-width:650px; margin:0; color:#dbeafe; font-size:.98rem; }
            .aec-hero-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
            .aec-hero-btn { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 17px; border-radius:999px; background:var(--aec-accent); color:#14234b; font-weight:800; text-decoration:none; box-shadow:0 6px 18px rgba(0,0,0,.14); }
            .aec-hero-btn.secondary { border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.12); color:#fff; box-shadow:none; }
            .aec-hero-mark { flex:0 0 auto; display:grid; place-items:center; width:112px; height:112px; border:1px solid rgba(255,255,255,.22); border-radius:30px; background:rgba(255,255,255,.12); font-size:3.8rem; transform:rotate(5deg); }
            .aec-heading { display:flex; align-items:end; justify-content:space-between; gap:12px; margin:2px 0 12px; }
            .aec-heading h2 { margin:0; color:#142b55; font-size:1.12rem; letter-spacing:-.02em; }
            .aec-heading p { margin:4px 0 0; color:var(--aec-muted); font-size:.86rem; }
            .aec-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
            .aec-stat { position:relative; overflow:hidden; min-width:0; padding:18px; border:1px solid var(--aec-line); border-radius:19px; background:linear-gradient(145deg,var(--aec-surface),var(--aec-background)); box-shadow:0 8px 22px rgba(18,48,102,.055); }
            .aec-stat:after { content:""; position:absolute; right:-28px; bottom:-44px; width:125px; height:125px; border:18px solid rgba(35,112,235,.055); border-radius:50%; }
            .aec-stat-top { display:flex; align-items:center; justify-content:space-between; gap:10px; color:#5d6f8d; font-size:.84rem; font-weight:700; }
            .aec-stat-icon { display:grid; place-items:center; flex:0 0 auto; width:43px; height:43px; border-radius:14px; background:#e8f1ff; font-size:1.35rem; }
            .aec-stat strong { display:block; margin-top:12px; color:#102b63; font-size:clamp(1.65rem,2.4vw,2.15rem); line-height:1; letter-spacing:-.04em; }
            .aec-stat small { display:block; margin-top:8px; color:#8190a8; font-size:.76rem; }
            .aec-stat.blue { background:linear-gradient(135deg,color-mix(in srgb,var(--aec-stat-students) 17%,white),var(--aec-surface)); } .aec-stat.green { background:linear-gradient(135deg,color-mix(in srgb,var(--aec-stat-teachers) 17%,white),var(--aec-surface)); } .aec-stat.purple { background:linear-gradient(135deg,color-mix(in srgb,var(--aec-stat-courses) 17%,white),var(--aec-surface)); } .aec-stat.gold { background:linear-gradient(135deg,color-mix(in srgb,var(--aec-stat-certificates) 17%,white),var(--aec-surface)); } .aec-stat.blue .aec-stat-icon { background:color-mix(in srgb,var(--aec-stat-students) 22%,white); } .aec-stat.green .aec-stat-icon { background:color-mix(in srgb,var(--aec-stat-teachers) 22%,white); } .aec-stat.purple .aec-stat-icon { background:color-mix(in srgb,var(--aec-stat-courses) 22%,white); } .aec-stat.gold .aec-stat-icon { background:color-mix(in srgb,var(--aec-stat-certificates) 22%,white); }
            .aec-live-class { display:grid; grid-template-columns:minmax(180px,280px) minmax(0,1fr); align-items:center; gap:18px; }
            .aec-live-class img { width:100%; height:150px; object-fit:cover; border-radius:14px; }
            .aec-content-grid { display:grid; grid-template-columns:minmax(0,1.45fr) minmax(270px,.85fr); gap:15px; }
            .aec-panel { min-width:0; padding:20px; border:1px solid var(--aec-line); border-radius:19px; background:var(--aec-surface); box-shadow:0 7px 22px rgba(18,48,102,.045); }
            .aec-panel h3 { display:flex; align-items:center; gap:9px; margin:0 0 15px; color:#172e5b; font-size:1rem; }
            .aec-panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:13px; }
            .aec-panel-head h3 { margin:0; }
            .aec-pill { display:inline-flex; align-items:center; padding:5px 9px; border-radius:999px; background:#e9f2ff; color:#1858b5; font-size:.72rem; font-weight:800; }
            .aec-action-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:11px; }
            .aec-action { display:flex; flex-direction:column; align-items:flex-start; min-height:112px; padding:14px; border:1px solid #e6edfa; border-radius:15px; background:linear-gradient(145deg,#fff,#f8faff); color:#17305f; text-decoration:none; transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease; }
            .aec-action:hover { transform:translateY(-2px); border-color:#a9c7f8; box-shadow:0 9px 20px rgba(22,83,171,.09); }
            .aec-action-icon { display:grid; place-items:center; width:36px; height:36px; border-radius:11px; background:#eaf2ff; font-size:1.15rem; }
            .aec-action strong { margin-top:10px; font-size:.83rem; }
            .aec-action small { margin-top:3px; color:#7c8ba5; font-size:.72rem; line-height:1.35; }
            .aec-module-list { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:9px; }
            .aec-module { display:flex; align-items:center; gap:10px; min-width:0; padding:11px 12px; border:1px solid #edf1f8; border-radius:12px; color:#24395f; text-decoration:none; font-size:.83rem; font-weight:700; }
            .aec-module:hover { border-color:#b6cdf4; background:#f7faff; }
            .aec-module span { font-size:1.1rem; }
            .aec-snapshot { display:grid; gap:0; }
            .aec-snapshot-row { display:flex; justify-content:space-between; gap:12px; padding:11px 0; border-top:1px solid #edf1f7; }
            .aec-snapshot-row:first-child { border-top:0; }
            .aec-snapshot-row span { color:#71819a; font-size:.83rem; }
            .aec-snapshot-row strong { color:#18335f; font-size:.83rem; text-align:right; overflow-wrap:anywhere; }
            .aec-admin .btn { border-radius:10px; }
            @media(max-width:1050px) { .aec-stats { grid-template-columns:repeat(2,minmax(0,1fr)); } .aec-action-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media(max-width:760px) { .aec-live-class { grid-template-columns:1fr; } .aec-live-class img { height:190px; } .aec-content-grid { grid-template-columns:1fr; } .aec-hero-mark { width:82px;height:82px;font-size:2.7rem;border-radius:23px; } }
            @media(max-width:520px) { .aec-hero { min-height:0; padding:22px; } .aec-hero-mark { display:none; } .aec-stats { gap:9px; } .aec-stat { padding:14px; } .aec-stat-icon { width:36px;height:36px; } .aec-module-list { grid-template-columns:1fr; } .aec-panel { padding:16px; } }
            @media(prefers-reduced-motion:reduce) { .aec-action { transition:none; } .aec-action:hover { transform:none; } }
        </style>

        <div class="aec-admin aec-admin-shell" data-theme="{{ $dashboardAppearance['theme_mode'] }}" data-shadow="{{ $dashboardAppearance['card_shadow'] }}" style="--aec-radius:{{ $dashboardAppearance['card_radius'] }}px;">
            @if($dashboardAppearance['show_hero'])
            <section class="aec-hero" style="order:{{ $dashboardAppearance['order_hero'] }};@if($dashboardAppearance['hero_image'])background-image:linear-gradient(90deg,rgba(5,22,68,.94),rgba(7,48,118,.72) 58%,rgba(7,48,118,.25)),url('{{ $dashboardAppearance['hero_image'] }}');background-size:cover;background-position:{{ $dashboardAppearance['hero_image_position'] }};@endif">
                <div class="aec-hero-copy">
                    <span class="aec-eyebrow">{{ $dashboardAppearance['hero_eyebrow'] ?: $institutionName . ' · ' . __('Learning Management System') }}</span>
                    <h1>{{ $dashboardAppearance['hero_title'] ?: __('Welcome back,') . ' ' . $user->name . '! 👋' }}</h1>
                    <p>{{ $dashboardAppearance['hero_subtitle'] ?: __('Your success is our mission. Manage students, teachers, courses and daily learning operations from one dashboard.') }}</p>@if($dashboardAppearance['hero_quote'])<p style="margin-top:8px;font-weight:800;color:var(--aec-accent)">{{ $dashboardAppearance['hero_quote'] }}</p>@endif
                    <div class="aec-hero-actions">
                        <a class="aec-hero-btn" href="{{ route('admin.courses.index') }}">{{ $dashboardAppearance['hero_cta_label'] ?: __('View courses') }} →</a>
                        <a class="aec-hero-btn secondary" href="{{ route('admin.users.index') }}">{{ __('Manage students') }}</a>
                    </div>
                </div>
                <div class="aec-hero-mark" aria-hidden="true">
                    @if($dashboardAppearance['brand_logo'])
                        <img src="{{ $dashboardAppearance['brand_logo'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:contain;padding:12px;">
                    @else
                        🎓
                    @endif
                </div>
            </section>

            @endif

            @if($setupWizardPending)
                <section class="aec-panel" style="border-color:#9bbdf5;background:#f5f9ff">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
                        <div><strong>{{ __('Finish setting up your institution') }}</strong><p class="muted" style="margin:5px 0 0">{{ __('Your setup is saved step by step. Complete the institution profile, academic year and payment methods when ready.') }}</p></div>
                        <a class="btn primary" href="{{ route('admin.setup-wizard.index') }}">{{ __('Continue setup') }}</a>
                    </div>
                </section>
            @endif

            @if($dashboardAppearance['show_statistics'])
            <section style="order:{{ $dashboardAppearance['order_statistics'] }}">
                <div class="aec-heading"><div><h2>{{ __('Academy overview') }}</h2><p>{{ __('Live totals from your current LMS records') }}</p></div><span class="badge success">{{ $roleLabel }}</span></div>
                <div class="aec-stats" aria-label="{{ __('Academy statistics') }}">
                    <article class="aec-stat blue"><div class="aec-stat-top"><span>{{ __('Total students') }}</span><span class="aec-stat-icon" aria-hidden="true">👥</span></div><strong>{{ number_format($stats['students']) }}</strong><small>{{ __('Registered student accounts') }}</small></article>
                    <article class="aec-stat green"><div class="aec-stat-top"><span>{{ __('Total teachers') }}</span><span class="aec-stat-icon" aria-hidden="true">👨‍🏫</span></div><strong>{{ number_format($stats['teachers']) }}</strong><small>{{ __('Teacher accounts') }}</small></article>
                    <article class="aec-stat purple"><div class="aec-stat-top"><span>{{ __('Total courses') }}</span><span class="aec-stat-icon" aria-hidden="true">📚</span></div><strong>{{ number_format($stats['courses']) }}</strong><small>{{ __('All courses in the catalog') }}</small></article>
                    <article class="aec-stat gold"><div class="aec-stat-top"><span>{{ __('Certificates issued') }}</span><span class="aec-stat-icon" aria-hidden="true">🏅</span></div><strong>{{ number_format($stats['certificates']) }}</strong><small>{{ __('Certificates generated by the LMS') }}</small></article>
                </div>
            </section>

            @endif

            @if($dashboardAppearance['show_live_class'] && $nextLiveClass)
                <section class="aec-panel aec-live-class" style="order:{{ $dashboardAppearance['order_live_class'] }}">
                    @if($dashboardAppearance['live_class_image'])
                        <img src="{{ $dashboardAppearance['live_class_image'] }}" alt="{{ $nextLiveClass->title }}" loading="lazy" style="width:100%;height:150px;object-fit:cover;border-radius:14px;">
                    @else
                        <div aria-hidden="true" style="display:grid;place-items:center;width:100%;height:150px;border-radius:14px;background:linear-gradient(135deg,var(--aec-primary),var(--aec-secondary));color:#fff;font-size:3rem;">🎥</div>
                    @endif
                    <div>
                        <div class="aec-panel-head"><h3>🎥 {{ __('Upcoming live class') }}</h3><span class="aec-pill">{{ __('Scheduled') }}</span></div>
                        <h2 style="margin:0 0 7px;color:var(--aec-primary);font-size:1.2rem;">{{ $nextLiveClass->title }}</h2>
                        <p class="muted" style="margin:0 0 7px;">{{ optional($nextLiveClass->batch)->title }} · {{ optional(optional($nextLiveClass->batch)->teacher)->name }}</p>
                        <p class="muted" style="margin:0 0 13px;">{{ $nextLiveClass->starts_at?->format('d M Y · h:i A') }}</p>
                        <a class="btn primary" href="{{ route('live-classes.index') }}">{{ __('Open live classes') }} →</a>
                    </div>
                </section>
            @endif

            <div class="aec-content-grid" style="order:{{ min($dashboardAppearance['order_quick_actions'], $dashboardAppearance['order_system_snapshot']) }}">
                @if($dashboardAppearance['show_quick_actions'])
                <section class="aec-panel" style="order:{{ $dashboardAppearance['order_quick_actions'] }}">
                    <div class="aec-panel-head"><h3>⚡ {{ __('Quick actions') }}</h3><span class="aec-pill">{{ __('Frequently used') }}</span></div>
                    <div class="aec-action-grid">
                        <a class="aec-action" href="{{ route('admin.users.index') }}"><span class="aec-action-icon">👥</span><strong>{{ __('Add / manage students') }}</strong><small>{{ __('Student and staff accounts') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.courses.create') }}"><span class="aec-action-icon">📘</span><strong>{{ __('Create course') }}</strong><small>{{ __('Build a learning course') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.batches.index') }}"><span class="aec-action-icon">🗓️</span><strong>{{ __('Manage batches') }}</strong><small>{{ __('Class groups and schedules') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.payments.index') }}"><span class="aec-action-icon">💳</span><strong>{{ __('Review payments') }}</strong><small>{{ __('Payment records and review') }}</small></a>
                        <a class="aec-action" href="{{ route('live-classes.index') }}"><span class="aec-action-icon">🎥</span><strong>{{ __('Live classes') }}</strong><small>{{ __('Open online class area') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.invoices.index') }}"><span class="aec-action-icon">🧾</span><strong>{{ __('Invoices & fees') }}</strong><small>{{ __('Manage fee records') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.backups.index') }}"><span class="aec-action-icon">💾</span><strong>{{ __('Create backup') }}</strong><small>{{ __('Protect LMS data') }}</small></a>
                        <a class="aec-action" href="{{ route('admin.analytics.index') }}"><span class="aec-action-icon">📊</span><strong>{{ __('View analytics') }}</strong><small>{{ __('Website and course insights') }}</small></a>
                    </div>
                </section>
                @endif

                @if($dashboardAppearance['show_system_snapshot'])
                <section class="aec-panel" style="order:{{ $dashboardAppearance['order_system_snapshot'] }}">
                    <h3>🏆 {{ __('System snapshot') }}</h3>
                    <div class="aec-snapshot">
                        <div class="aec-snapshot-row"><span>{{ __('Signed-in account') }}</span><strong>{{ $user->name }}</strong></div>
                        <div class="aec-snapshot-row"><span>{{ __('Access role') }}</span><strong>{{ $roleLabel }}</strong></div>
                        <div class="aec-snapshot-row"><span>{{ __('Published course list') }}</span><strong>{{ number_format($courses->count()) }}</strong></div>
                        <div class="aec-snapshot-row"><span>{{ __('Recent certificates shown') }}</span><strong>{{ number_format($recentCertificates->count()) }}</strong></div>
                    </div>
                    <div style="margin-top:16px;padding:13px;border-radius:13px;background:#f1f6ff;color:#31517f;font-size:.82rem;line-height:1.5">
                        <strong>{{ __('Keep your academy running smoothly') }}</strong><br>{{ __('Review system health, recent activity and payment records regularly.') }}
                    </div>
                    <a class="btn secondary" style="margin-top:14px" href="{{ route('admin.system-health.index') }}">🩺 {{ __('Check website health') }}</a>
                </section>
                @endif
            </div>

            @if($dashboardAppearance['show_learning_operations'])
            <section class="aec-panel" style="order:{{ $dashboardAppearance['order_learning_operations'] }}">
                <div class="aec-panel-head"><h3>🧭 {{ __('Learning operations') }}</h3><span class="aec-pill">{{ __('Administration') }}</span></div>
                <div class="aec-module-list">
                    <a class="aec-module" href="{{ route('admin.courses.index') }}"><span>📚</span>{{ __('Courses & lessons') }}</a>
                    <a class="aec-module" href="{{ route('admin.users.index') }}"><span>👥</span>{{ __('Students, teachers & roles') }}</a>
                    <a class="aec-module" href="{{ route('admin.batches.index') }}"><span>🗓️</span>{{ __('Batches & branches') }}</a>
                    <a class="aec-module" href="{{ route('admin.payments.index') }}"><span>💳</span>{{ __('Payments') }}</a>
                    <a class="aec-module" href="{{ route('admin.website-builder.index') }}"><span>🖥️</span>{{ __('Website builder') }}</a>
                    <a class="aec-module" href="{{ route('admin.website-menu.index') }}"><span>☷</span>{{ __('Main menu builder') }}</a>
                    <a class="aec-module" href="{{ route('admin.media.index') }}"><span>🖼️</span>{{ __('Media library') }}</a>
                    <a class="aec-module" href="{{ route('admin.news.index') }}"><span>📰</span>{{ __('Blog & news') }}</a>
                    <a class="aec-module" href="{{ route('admin.activity-logs.index') }}"><span>📋</span>{{ __('Activity logs') }}</a>
                    <a class="aec-module" href="{{ route('admin.security.sessions.index') }}"><span>🔐</span>{{ __('Device sessions') }}</a>
                    <a class="aec-module" href="{{ route('admin.settings.index') }}"><span>⚙️</span>{{ __('System settings') }}</a>
                    <a class="aec-module" href="{{ route('admin.updates.index') }}"><span>🔄</span>{{ __('System updates') }}</a>
                </div>
            </section>
            @endif

            <section class="aec-panel" aria-labelledby="aec-recent-activity-title" style="order:7">
                <div class="aec-panel-head"><h3 id="aec-recent-activity-title">🕘 {{ __('Recent activity') }}</h3><a class="aec-pill" href="{{ route('admin.activity-logs.index') }}">{{ __('View activity log') }} →</a></div>
                @forelse($recentActivity as $activity)
                    <div class="aec-snapshot-row">
                        <span><strong>{{ $activity->user?->name ?? __('System') }}</strong> · {{ $activity->description }}</span>
                        <time datetime="{{ $activity->created_at?->toIso8601String() }}" style="flex:0 0 auto;color:var(--aec-muted);font-size:.75rem">{{ $activity->created_at?->diffForHumans() }}</time>
                    </div>
                @empty
                    <p class="muted" style="margin:0">{{ __('No activity has been recorded yet.') }}</p>
                @endforelse
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

        @if($user->isBranchManager() && ($user->hasAdminPermission('branch.batches') || $user->hasAdminPermission('branch.students')))
            <section class="panel" style="margin-bottom:20px;">
                <div class="page-header" style="margin-bottom:10px;">
                    <div>
                        <span class="badge info">{{ __('Admin Panel · Branch workspace') }}</span>
                        <h2 style="margin:10px 0 0;">{{ __('Manage your assigned branch') }}</h2>
                        <p class="muted">{{ __('You can manage only the branch modules explicitly assigned to your account.') }}</p>
                    </div>
                    @if($user->hasAdminPermission('branch.batches'))
                        <a href="{{ route('admin.branch-workspace.batches.index') }}" class="btn primary">{{ __('Open branch batches') }}</a>
                    @endif
                </div>
                @if($user->hasAdminPermission('branch.students'))
                    <a href="{{ route('admin.branch-workspace.students.index') }}" class="btn secondary">{{ __('View branch students') }}</a>
                @endif
            </section>
        @endif

        @if($user->isAccountant() && ($user->hasAdminPermission('finance.invoices') || $user->hasAdminPermission('finance.refunds') || $user->hasAdminPermission('finance.expenses') || $user->hasAdminPermission('finance.reports')))
            <section class="panel" style="margin-bottom:20px;">
                <div class="page-header" style="margin-bottom:10px;">
                    <div>
                        <span class="badge info">{{ __('Admin Panel · Finance workspace') }}</span>
                        <h2 style="margin:10px 0 0;">{{ __('Manage your branch finances') }}</h2>
                        <p class="muted">{{ __('Only explicitly assigned finance modules are available within your branch.') }}</p>
                    </div>
                    @if($user->hasAdminPermission('finance.invoices'))
                        <a href="{{ route('admin.finance.invoices.index') }}" class="btn primary">{{ __('Open branch invoices') }}</a>
                    @endif
                </div>
                @if($user->hasAdminPermission('finance.reports'))
                    <a href="{{ route('admin.finance.reports.index') }}" class="btn secondary">{{ __('View branch finance reports') }}</a>
                @endif
            </section>
        @endif

        @if($user->isTeacher() && $teacherInsights)
            <section class="panel" style="margin-bottom:20px;">
                <div class="page-header" style="margin-bottom:14px;">
                    <div>
                        <span class="badge info">{{ __('Teaching workspace') }}</span>
                        <h2 style="margin:10px 0 0;">{{ __('Your teaching overview') }}</h2>
                    </div>
                    <a href="{{ route('teacher.batches.index') }}" class="btn primary">{{ __('Manage my batches') }}</a>
                </div>
                <div class="dash-summary">
                    <div class="summary-box"><span class="muted">{{ __('Assigned courses') }}</span><strong>{{ number_format($teacherInsights['assigned_courses']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Active batches') }}</span><strong>{{ number_format($teacherInsights['active_batches']) }} / {{ number_format($teacherInsights['total_batches']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Active students') }}</span><strong>{{ number_format($teacherInsights['active_students']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Assignments created') }}</span><strong>{{ number_format($teacherInsights['assignments_created']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Pending reviews') }}</span><strong>{{ number_format($teacherInsights['pending_reviews']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Graded submissions') }}</span><strong>{{ number_format($teacherInsights['graded_submissions']) }}</strong></div>
                </div>
                <p class="muted" style="margin:12px 0 0;">{{ __('Counts are limited to courses, batches and assignment submissions assigned to your teacher account.') }}</p>
            </section>
        @endif

        @if($user->isStudent() && $studentInsights)
            <section class="panel" style="margin-bottom:20px;">
                <div class="page-header" style="margin-bottom:14px;">
                    <div>
                        <span class="badge info">{{ __('Student analytics') }}</span>
                        <h2 style="margin:10px 0 0;">{{ __('Your learning progress') }}</h2>
                    </div>
                    <strong style="font-size:1.35rem;color:var(--primary);">{{ $studentInsights['progress_percent'] }}%</strong>
                </div>
                <div style="height:10px;background:var(--panel-alt);border-radius:999px;overflow:hidden;margin-bottom:18px;" role="progressbar" aria-label="{{ __('Course completion') }}" aria-valuenow="{{ $studentInsights['progress_percent'] }}" aria-valuemin="0" aria-valuemax="100">
                    <div style="height:100%;width:{{ $studentInsights['progress_percent'] }}%;background:linear-gradient(90deg,var(--primary),var(--accent));border-radius:999px;"></div>
                </div>
                <div class="dash-summary">
                    <div class="summary-box"><span class="muted">{{ __('Enrolled courses') }}</span><strong>{{ number_format($studentInsights['enrolled_courses']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Lessons completed') }}</span><strong>{{ number_format($studentInsights['completed_lessons']) }} / {{ number_format($studentInsights['total_lessons']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Assignments submitted') }}</span><strong>{{ number_format($studentInsights['assignments_submitted']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Exams attempted') }}</span><strong>{{ number_format($studentInsights['exams_attempted']) }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Attendance records') }}</span><strong>{{ number_format($studentInsights['attendance_marked']) }}</strong></div>
                </div>
                <p class="muted" style="margin:14px 0 0;">{{ __('Your progress is calculated from completed lessons in your active and completed enrollments.') }}</p>
            </section>
        @endif
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
