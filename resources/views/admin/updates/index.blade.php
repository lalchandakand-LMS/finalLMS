@extends('layouts.app')
@section('title', __('System updates').' | '.config('app.name'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Super Admin') }}</span><h1>{{ __('System Update Center') }}</h1><p class="muted">{{ __('Review system versions, database migrations and backups before applying a controlled update.') }}</p></div><a href="{{ route('admin.backups.index') }}" class="btn secondary">{{ __('Backup & Restore') }}</a></div>
<div class="grid grid-3" style="margin-bottom:20px;">
<div class="panel"><div class="muted">{{ __('Application version') }}</div><h2 style="margin:8px 0;">{{ $currentVersion }}</h2><small class="muted">{{ __('Configured release version') }}</small></div>
<div class="panel"><div class="muted">{{ __('PHP runtime') }}</div><h2 style="margin:8px 0;">{{ $phpVersion }}</h2><small class="muted">{{ __('Required PHP 8.3+') }}</small></div>
<div class="panel"><div class="muted">{{ __('Laravel framework') }}</div><h2 style="margin:8px 0;">{{ $laravelVersion }}</h2><small class="muted">{{ __('Environment:') }} {{ $environment }}</small></div>
</div>
<div class="panel" style="margin-bottom:20px;">
<h2>{{ __('Database migration status') }}</h2>
@if($pendingMigrations->isEmpty())
<p class="muted">{{ __('All migration files are recorded as applied.') }}</p>
<span class="badge success">{{ __('No pending migrations') }}</span>
@else
<p class="muted">{{ __('Pending migration files:') }} {{ $pendingMigrations->count() }}</p>
<div style="max-height:260px;overflow:auto;border:1px solid var(--border);border-radius:10px;padding:12px;margin:12px 0;">@foreach($pendingMigrations as $migration)<div style="padding:6px 0;border-bottom:1px solid var(--border);font-family:monospace;font-size:.82rem;">{{ $migration }}</div>@endforeach</div>
<div class="flash error">{{ __('Running migrations changes the database schema. A fresh signed backup is created automatically before the command runs.') }}</div>
<form method="POST" action="{{ route('admin.updates.migrate') }}" class="grid grid-2" style="margin-top:16px;">
@csrf
<div><label for="confirmation">{{ __('Type MIGRATE to confirm') }}</label><input id="confirmation" name="confirmation" required autocomplete="off"></div>
<div><label for="password">{{ __('Current administrator password') }}</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
<div><button class="btn primary" type="submit" onclick="return confirm('{{ __('Create a backup and run all pending database migrations?') }}')">{{ __('Backup & run migrations') }}</button></div>
</form>
@endif
</div>
<div class="panel" style="margin-bottom:20px;">
<h2>{{ __('Backup readiness') }}</h2>
@if($latestBackup)
<p><strong>{{ $latestBackup['name'] }}</strong></p>
<p class="muted">{{ number_format($latestBackup['size']/1024/1024,2) }} MB · {{ date('d M Y H:i:s',$latestBackup['modified']) }}</p>
@else
<p class="muted">{{ __('No server backup exists yet. Create one before deploying new application code.') }}</p>
@endif
<a href="{{ route('admin.backups.index') }}" class="btn secondary">{{ __('Open Backup & Restore') }}</a>
</div>
<div class="panel">
<h2>{{ __('Application code update') }}</h2>
<p class="muted">{{ __('This panel safely reports the installed application and database state. Uploading or replacing PHP code directly from a browser is intentionally disabled: releases must be deployed from a reviewed GitHub release package through cPanel or the approved deployment workflow.') }}</p>
<ol><li>{{ __('Review and approve the GitHub pull request and passing CI / CodeQL checks.') }}</li><li>{{ __('Create and download a full backup.') }}</li><li>{{ __('Upload the verified release package through cPanel and preserve the previous release.') }}</li><li>{{ __('Open this page and run pending migrations after confirming your password.') }}</li><li>{{ __('Verify login, student learning, payments, media and live classes before reopening traffic.') }}</li></ol>
<p class="muted">{{ __('Automatic code rollback is not enabled. Restore the saved previous release package and database backup together if a deployment must be reversed.') }}</p>
</div>
@endsection
