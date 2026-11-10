@extends('layouts.app')
@section('title', __('Release preflight'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('System administration') }}</span>
        <h1>{{ __('Release preflight') }}</h1>
        <p class="muted">{{ $preparation->version }} · {{ __('Preflight results') }}</p>
    </div>
    <a href="{{ route('admin.updates.index') }}" class="btn secondary">{{ __('Back to updates') }}</a>
</div>

<div class="panel" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <h2 style="margin:0;">{{ __('Preflight summary') }}</h2>
        <span class="badge {{ $preflight['status'] === 'blocked' ? 'error' : 'warning' }}">
            {{ $preflight['status'] === 'blocked' ? __('Blocked') : __('Review required') }}
        </span>
    </div>
    <p>{{ $preflight['summary'] }}</p>
    <div class="flash error" style="margin-top:16px;">
        <strong>{{ __('Live activation is not available') }}</strong>
        <p>{{ __('This report is read-only. It does not create backups, run migrations, enable maintenance mode, activate code or modify the live application.') }}</p>
    </div>
</div>

<div class="panel">
    <h2>{{ __('Preflight results') }}</h2>
    @foreach($preflight['checks'] as $check)
        <div class="course-item" style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
            <span class="badge {{ $check['status'] === 'pass' ? 'success' : ($check['status'] === 'fail' ? 'error' : 'warning') }}">
                {{ ucfirst($check['status']) }}
            </span>
            <div style="flex:1;min-width:240px;">
                <strong>{{ __($check['label']) }}</strong>
                <p class="muted">{{ $check['message'] }}</p>
            </div>
        </div>
    @endforeach
</div>

@if($preflight['pending_migrations'] !== [])
    <div class="panel" style="margin-top:20px;">
        <h2>{{ __('Pending migrations') }}</h2>
        <p class="muted">{{ __('These migrations require separate review. This screen will not run them.') }}</p>
        <ul>
            @foreach($preflight['pending_migrations'] as $migration)
                <li><code>{{ $migration }}</code></li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
