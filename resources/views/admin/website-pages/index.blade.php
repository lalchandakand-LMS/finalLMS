@extends('layouts.app')
@section('title', 'Website Pages')
@section('content')
<div class="page-header">
    <div><span class="badge info">Admin CMS</span><h1>Website Pages</h1><p class="muted">Create and manage independent public pages without changing application code.</p></div>
    <a class="btn primary" href="{{ route('admin.website-pages.create') }}">+ Create Page</a>
</div>
<div class="panel">
    @forelse($pages as $page)
        <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
            <div>
                <strong>{{ $page->title }}</strong>
                <div class="muted">/pages/{{ $page->slug }} · {{ ucfirst($page->status) }} · {{ $page->show_in_menu ? 'In menu' : 'Hidden from menu' }}</div>
                <small class="muted">Updated {{ $page->updated_at?->diffForHumans() }}</small>
            </div>
            <div class="cta-row">
                @if($page->status === 'published')<a class="btn secondary" target="_blank" rel="noopener" href="{{ route('website-pages.show', $page->slug) }}">View</a>@endif
                <a class="btn secondary" href="{{ route('admin.website-pages.edit', $page) }}">Edit</a>
                <form method="POST" action="{{ route('admin.website-pages.destroy', $page) }}" onsubmit="return confirm('Delete this page permanently?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Delete</button></form>
            </div>
        </div>
    @empty
        <p class="muted">No custom pages yet. Create your first page.</p>
    @endforelse
    {{ $pages->links() }}
</div>
@endsection
