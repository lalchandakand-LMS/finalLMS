@extends('layouts.app')
@section('title', 'Backup & Restore')
@section('content')
<div class="page-header"><div><span class="badge info">System administration</span><h1>Backup & Restore</h1><p class="muted">Backups contain database records and uploaded media. Archives are stored privately on the server and protected with an application-key signature.</p></div><a href="{{ route('dashboard') }}" class="btn secondary">{{ __('Dashboard') }}</a></div>
<div class="panel" style="margin-bottom:20px;">
<h2>{{ __('Create a backup') }}</h2><p class="muted">Create a downloadable backup before changing settings, updating the LMS, or restoring an older copy.</p>
<form method="POST" action="{{ route('admin.backups.create') }}">@csrf<button class="btn primary">{{ __('Create database + media backup') }}</button></form>
</div>
<div class="panel" style="margin-bottom:20px;">
<h2>{{ __('Restore a backup') }}</h2>
<div class="flash error">{{ __('Warning: restore replaces the current database contents. Make a fresh backup first.') }} The backup must have been created by this application key and the database structure must match the installed LMS version.</div>
<form method="POST" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data">
@csrf
<label>{{ __('Backup archive (.json.gz)') }}<input type="file" name="backup_file" accept=".gz,application/gzip" required></label>
<label>{{ __('Type RESTORE to confirm') }}<input name="confirmation" autocomplete="off" required></label>
<label>{{ __('Current administrator password') }}<input type="password" name="password" autocomplete="current-password" required></label>
<button class="btn secondary" onclick="return confirm('This will replace the current database. Continue?')">{{ __('Restore backup') }}</button>
</form>
</div>
<div class="panel"><h2>{{ __('Server backups') }}</h2>
@forelse($files as $file)
<div class="course-item" style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;">
<div><strong>{{ $file['name'] }}</strong><p class="muted">{{ number_format($file['size'] / 1024 / 1024, 2) }} MB · {{ date('d M Y H:i', $file['modified']) }}</p></div>
<a class="btn secondary" href="{{ route('admin.backups.download', $file['name']) }}">{{ __('Download') }}</a>
</div>
@empty<p class="muted">{{ __('No backup archives yet.') }}</p>@endforelse
</div>
@endsection
