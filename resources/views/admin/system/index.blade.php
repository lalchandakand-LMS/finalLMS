@extends('layouts.app')
@section('title', __('System health & updates'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('System administration') }}</span><h1>{{ __('System health & updates') }}</h1><p class="muted">{{ __('Review server readiness and create a verified recovery point before applying a new release.') }}</p></div>
    <a href="{{ route('admin.backups.index') }}" class="btn secondary">{{ __('Backup & restore') }}</a>
</div>
<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;">
    <div class="panel"><span class="muted">{{ __('Health checks passed') }}</span><h2>{{ $passed }} / {{ $total }}</h2></div>
    <div class="panel"><span class="muted">{{ __('Application') }}</span><h2>{{ $appVersion }}</h2></div>
    <div class="panel"><span class="muted">{{ __('Laravel / PHP') }}</span><h2>{{ $laravelVersion }} / {{ $phpVersion }}</h2></div>
    <div class="panel"><span class="muted">{{ __('Private backups') }}</span><h2>{{ $backupCount }}</h2></div>
</div>
<div class="panel" style="margin-bottom:20px;">
    <h2>{{ __('Environment checks') }}</h2>
    @foreach($checks as $check)
        <div class="course-item" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div><strong>{{ $check['label'] }}</strong><p class="muted">{{ $check['detail'] }}</p></div>
            <span class="badge {{ $check['passed'] ? 'success' : 'danger' }}">{{ $check['passed'] ? __('PASS') : __('CHECK') }}</span>
        </div>
    @endforeach
</div>
<div class="panel">
    <h2>{{ __('Safe release procedure') }}</h2>
    <div class="flash info">{{ __('This panel does not install arbitrary ZIP files or execute remote code. That is intentional: an unsigned or incompatible update could damage the LMS or expose student/payment data.') }}</div>
    <ol style="line-height:1.9;padding-left:22px;">
        <li>{{ __('Create and download a fresh database + media backup from Backup & Restore.') }}</li>
        <li>{{ __('Obtain the release ZIP only from a successful CI run for the exact approved commit.') }}</li>
        <li>{{ __('Install and test the release on a separate staging site and database first.') }}</li>
        <li>{{ __('For production, preserve the existing .env and private uploads; never run the fresh installer over an existing LMS.') }}</li>
        <li>{{ __('Apply only the documented database migrations, then run the production smoke tests.') }}</li>
        <li>{{ __('Keep the previous application package and matching database backup until the new release is verified.') }}</li>
    </ol>
    <p class="muted">{{ __('Automatic one-click core updates remain disabled until a signed release manifest, trusted signing key, atomic file swap, migration compatibility checks and tested rollback runner are provisioned. This prevents an unsafe update from changing the live system.') }}</p>
    <a class="btn primary" href="{{ route('admin.backups.index') }}">{{ __('Create a recovery backup') }}</a>
</div>
@endsection
