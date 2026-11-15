@extends('layouts.app')

@section('title', $academicLevel->name . ' | Academic Level')

@section('content')
<div class="page-header">
    <div><span class="badge info">Academic Level</span><h1 style="margin:12px 0 0;">{{ $academicLevel->name }}</h1><p class="muted">{{ $academicLevel->description }}</p></div>
    <div class="cta-row"><a class="btn secondary" href="{{ route('admin.academic-levels.index') }}">All levels</a><a class="btn primary" href="{{ route('admin.courses.create') }}">Create course</a><a class="btn secondary" href="{{ route('admin.website-pages.create') }}">Create page</a></div>
</div>
<section class="panel" style="margin-bottom:18px;">
    <h2>Courses · {{ $academicLevel->courses->count() }}</h2>
    @forelse($academicLevel->courses as $course)
        <article class="course-item" style="margin-bottom:12px;">
            <div class="meta-row"><div><strong>{{ $course->title }}</strong><p class="muted">Teacher: {{ $course->teacher?->name ?? 'Unassigned' }} · {{ ucfirst($course->status) }}</p></div><a class="btn secondary" href="{{ route('admin.courses.edit', $course) }}">Edit course</a></div>
            <h3>Related batches · {{ $course->batches->count() }}</h3>
            @forelse($course->batches as $batch)
                <div style="padding:8px 0;border-top:1px solid var(--border);">
                    <strong>{{ $batch->title }}</strong> · {{ $batch->branch?->name }} · {{ ucfirst($batch->status) }}
                    <a href="{{ route('admin.batches.exams.index', $batch) }}" class="btn secondary" style="margin-left:8px;">Manage exams ({{ $batch->exams->count() }})</a>
                </div>
            @empty
                <p class="muted">No batches for this course yet. Create one from Batches.</p>
            @endforelse
        </article>
    @empty
        <p class="muted">No courses assigned to this level yet.</p>
    @endforelse
    <a class="btn secondary" href="{{ route('admin.batches.index') }}">Manage batches</a>
</section>
<section class="panel">
    <h2>Website pages · {{ $academicLevel->pages->count() }}</h2>
    @forelse($academicLevel->pages as $page)
        <div style="padding:9px 0;border-bottom:1px solid var(--border);"><strong>{{ $page->title }}</strong> · {{ ucfirst($page->status) }} <a class="btn secondary" href="{{ route('admin.website-pages.edit', $page) }}">Edit page</a></div>
    @empty
        <p class="muted">No pages assigned to this level yet.</p>
    @endforelse
</section>
@endsection
