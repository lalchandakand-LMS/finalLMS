@extends('layouts.app')
@section('title', __('Lesson revision history'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Content studio') }}</span><h1>{{ __('Revision history') }}</h1><p class="muted">{{ $courseContentPost->title }}</p></div>
    <a class="btn secondary" href="{{ route('admin.course-content-posts.edit', $courseContentPost) }}">{{ __('Back to lesson') }}</a>
</div>
<div class="panel">
    <p class="muted">{{ __('Each revision stores the lesson text, video URL, metadata, publication state and categories before an edit. Media files and paid-download entitlements are intentionally not rolled back.') }}</p>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
        <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Saved by') }}</th><th>{{ __('Change') }}</th><th>{{ __('Title at that time') }}</th><th>{{ __('Saved') }}</th><th>{{ __('Restore') }}</th></tr></thead>
        <tbody>
        @forelse($versions as $version)
            <tr style="border-top:1px solid var(--border);">
                <td>#{{ $version->version_number }}</td>
                <td>{{ $version->creator?->name ?: '—' }}</td>
                <td>{{ __(ucfirst($version->change_type)) }}</td>
                <td>{{ $version->snapshot['title'] ?? '—' }}</td>
                <td>{{ $version->created_at?->format('d M Y, h:i A') }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.course-content-posts.versions.restore', [$courseContentPost, $version]) }}" onsubmit="return confirm('{{ __('Restore this revision? The current lesson will first be saved as a new revision.') }}')">
                        @csrf
                        <button class="btn secondary" type="submit">{{ __('Restore revision') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No previous revisions have been saved yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $versions->links() }}
</div>
@endsection
