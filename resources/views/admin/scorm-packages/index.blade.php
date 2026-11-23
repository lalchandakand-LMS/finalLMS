@extends('layouts.app')

@section('title', __('SCORM packages') . ' | ' . $course->title)

@section('content')
@php($scormRoutePrefix = auth()->user()->isAdmin() ? 'admin' : 'teacher')
<div class="container" style="max-width:1000px;">
    <div class="page-header"><div><span class="badge info">{{ __('SCORM 1.2') }}</span><h1>{{ __('SCORM package library') }}</h1><p class="muted">{{ $course->title }} · {{ __('Packages are validated before being stored in private storage.') }}</p></div></div>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    <section class="panel">
        <h2>{{ __('Upload SCORM 1.2 package') }}</h2>
        <form method="POST" action="{{ route($scormRoutePrefix.'.courses.scorm-packages.store', $course) }}" enctype="multipart/form-data" class="grid grid-2">
            @csrf
            <label>{{ __('Package title') }}<input name="title" maxlength="180" placeholder="{{ __('Optional title') }}"></label>
            <label>{{ __('ZIP archive (max 100 MB)') }}<input type="file" name="package" accept=".zip,application/zip" required></label>
            <div><button class="btn primary" type="submit">{{ __('Validate and upload') }}</button></div>
        </form>
    </section>
    <section class="panel" style="margin-top:18px;">
        <h2>{{ __('Validated packages') }}</h2>
        @forelse($packages as $package)
            <article style="padding:12px 0;border-bottom:1px solid var(--border);">
                <strong>{{ $package->title }}</strong><p class="muted">{{ __('Entry point') }}: {{ $package->entry_point }} · {{ count($package->files ?? []) }} {{ __('files') }} · SHA-256 {{ substr($package->archive_sha256,0,16) }}…</p>
                <form method="POST" action="{{ route($scormRoutePrefix.'.courses.scorm-packages.destroy', [$course,$package]) }}" onsubmit="return confirm('{{ __('Remove this package?') }}')">@csrf @method('DELETE')<button class="btn danger" type="submit">{{ __('Remove') }}</button></form>
            </article>
        @empty
            <p class="muted">{{ __('No SCORM packages have been uploaded for this course.') }}</p>
        @endforelse
    </section>
</div>
@endsection
