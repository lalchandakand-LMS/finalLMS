@extends('layouts.app')
@section('title', 'Page Revision History')
@section('content')
<div class="page-header">
    <div><span class="badge info">Admin CMS</span><h1>Revision history</h1><p class="muted">Saved versions for {{ $websitePage->title }}. Restoring a version first saves the current page, so the action can be reversed.</p></div>
    <div class="cta-row"><a class="btn secondary" href="{{ route('admin.website-pages.edit', $websitePage) }}">Back to editor</a><a class="btn secondary" target="_blank" rel="noopener" href="{{ route('admin.website-pages.preview', $websitePage) }}">Preview current page</a></div>
</div>
@if($errors->any())
    <div class="flash error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
@endif
<section class="panel">
    @if($revisions->isEmpty())
        <p class="muted">No revisions have been saved yet. The first revision is created when this page is edited.</p>
    @else
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Saved at</th><th>Editor</th><th>Version</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($revisions as $revision)
                <tr>
                    <td>{{ $revision->created_at?->format('d M Y, h:i A') }}</td>
                    <td>{{ $revision->creator?->name ?? 'Former user / system' }}</td>
                    <td>{{ $revision->label }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.website-pages.revisions.restore', [$websitePage, $revision]) }}" onsubmit="return confirm('Restore this saved version? The current version will also be saved in history.');">
                            @csrf
                            <button class="btn secondary" type="submit">Restore this version</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $revisions->links() }}
    @endif
</section>
@endsection
