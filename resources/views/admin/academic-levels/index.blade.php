@extends('layouts.app')

@section('title', 'Academic Levels | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">Academic Structure</span><h1 style="margin:12px 0 0;">Academic Levels</h1><p class="muted">Create levels such as Class 6–8, SSC, HSC, Admission or University.</p></div>
    <a class="btn secondary" href="{{ route('admin.courses.index') }}">Courses</a>
</div>
<section class="panel" style="margin-bottom:20px;">
    <h2>Create academic level</h2>
    <form method="POST" action="{{ route('admin.academic-levels.store') }}" class="grid grid-2">
        @csrf
        <label>Level name<input name="name" maxlength="120" placeholder="e.g. SSC" required></label>
        <label>Slug (optional)<input name="slug" maxlength="140" placeholder="ssc"></label>
        <label>Description<input name="description" maxlength="1000"></label>
        <label>Display order<input type="number" name="sort_order" min="0" max="10000" value="0"></label>
        <label><input type="checkbox" name="is_active" value="1" checked style="width:auto;"> Active</label>
        <div><button class="btn primary" type="submit">Create level</button></div>
    </form>
</section>
<section class="panel">
    <h2>Manage levels</h2>
    @forelse($levels as $level)
        <details style="padding:12px 0;border-bottom:1px solid var(--border);">
            <summary><strong>{{ $level->name }}</strong> <span class="muted">· {{ $level->courses_count }} courses · {{ $level->pages_count }} pages · {{ $level->is_active ? 'Active' : 'Inactive' }}</span></summary>
            <form method="POST" action="{{ route('admin.academic-levels.update', $level) }}" class="grid grid-2" style="margin-top:12px;">
                @csrf @method('PUT')
                <label>Name<input name="name" value="{{ $level->name }}" maxlength="120" required></label>
                <label>Slug<input name="slug" value="{{ $level->slug }}" maxlength="140"></label>
                <label>Description<input name="description" value="{{ $level->description }}" maxlength="1000"></label>
                <label>Display order<input type="number" name="sort_order" min="0" max="10000" value="{{ $level->sort_order }}"></label>
                <label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked($level->is_active)> Active</label>
                <div><button class="btn primary" type="submit">Save changes</button></div>
            </form>
            @if($level->courses_count === 0 && $level->pages_count === 0)
                <form method="POST" action="{{ route('admin.academic-levels.destroy', $level) }}" style="margin-top:8px;">@csrf @method('DELETE')<button class="btn secondary" type="submit">Delete level</button></form>
            @endif
        </details>
    @empty
        <p class="muted">No academic levels yet.</p>
    @endforelse
</section>
@endsection
