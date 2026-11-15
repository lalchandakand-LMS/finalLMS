@extends('layouts.app')
@section('title', 'Website Pages')
@section('content')
<div class="page-header">
    <div><span class="badge info">Admin CMS</span><h1>Website Pages</h1><p class="muted">Create and manage independent public pages without changing application code.</p></div>
    <a class="btn primary" href="{{ route('admin.website-pages.create') }}">+ Create Page</a>
</div>
<form method="GET" action="{{ route('admin.website-pages.index') }}" class="panel" style="margin-bottom:14px;">
    <label>Filter pages by academic level<select name="academic_level_id"><option value="">All pages and levels</option>@foreach($academicLevels as $academicLevel)<option value="{{ $academicLevel->id }}" @selected((string) ($filters['academic_level_id'] ?? '') === (string) $academicLevel->id)>{{ $academicLevel->name }}</option>@endforeach</select></label>
    <div class="cta-row" style="margin-top:10px;"><button class="btn primary" type="submit">Apply filter</button><a class="btn secondary" href="{{ route('admin.website-pages.index') }}">Clear</a></div>
</form>
<div class="panel">
    @forelse($pages as $page)
        <div class="card" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
            <div>
                <strong>{{ $page->title }} @if($page->academicLevel)<span class="badge info">{{ $page->academicLevel->name }}</span>@endif</strong>
                <div class="muted">/pages/{{ $page->slug }} · {{ ucfirst($page->status) }} · {{ $page->show_in_menu ? 'In menu' : 'Hidden from menu' }}</div>
                <small class="muted">Updated {{ $page->updated_at?->diffForHumans() }}</small>
            </div>
            <div class="cta-row">
                @if($page->status === 'published')<a class="btn secondary" target="_blank" rel="noopener" href="{{ route('website-pages.show', $page->slug) }}">View</a>@endif
                <form method="POST" action="{{ route('admin.website-pages.duplicate', $page) }}">@csrf<button class="btn secondary" type="submit">Duplicate</button></form>
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
