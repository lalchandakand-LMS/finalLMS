@extends('layouts.app')
@section('title', __('Course revision history'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Course management') }}</span><h1>{{ __('Course revision history') }}</h1><p class="muted">{{ $course->title }}</p></div>
    <a class="btn secondary" href="{{ route('admin.courses.edit', $course) }}">{{ __('Back to course') }}</a>
</div>
<div class="panel">
    <p class="muted">{{ __('Each revision stores course details, status, SEO metadata and category assignments. Course images, enrollments, lessons, payments and student records are not rolled back.') }}</p>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
        <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Saved by') }}</th><th>{{ __('Change') }}</th><th>{{ __('Course title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Saved') }}</th><th>{{ __('Restore') }}</th></tr></thead>
        <tbody>
        @forelse($versions as $version)
            <tr style="border-top:1px solid var(--border);">
                <td>#{{ $version->version_number }}</td><td>{{ $version->creator?->name ?: '—' }}</td>
                <td>{{ __(ucfirst($version->change_type)) }}</td><td>{{ $version->snapshot['title'] ?? '—' }}</td>
                <td>{{ __(ucfirst($version->snapshot['status'] ?? '—')) }}</td><td>{{ $version->created_at?->format('d M Y, h:i A') }}</td>
                <td><form method="POST" action="{{ route('admin.courses.versions.restore', [$course, $version]) }}" onsubmit="return confirm('{{ __('Restore this course revision? Current state will be saved first.') }}')">
                    @csrf<button class="btn secondary" type="submit">{{ __('Restore revision') }}</button>
                </form></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted" style="padding:18px;">{{ __('No previous course revisions have been saved yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $versions->links() }}
</div>
@endsection
