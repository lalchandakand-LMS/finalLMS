@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4"><a href="{{ route('admin.academic-levels.index') }}">← Academic Levels</a><h1 class="h3 mt-2">{{ $academicLevel->name }}</h1><p class="text-muted">{{ $academicLevel->description }}</p></div>
    <div class="card card-body mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h5 mb-0">Exams for {{ $academicLevel->name }}</h2>
            <a href="{{ route('admin.batches.index', ['academic_level_id' => $academicLevel->id]) }}">Manage batches</a>
        </div>
        <form method="GET" action="{{ route('admin.academic-levels.show', $academicLevel) }}" class="d-flex gap-2 flex-wrap my-3">
            <label class="flex-grow-1">Search exam title
                <input class="form-control" type="search" name="exam_search" value="{{ $filters['exam_search'] ?? '' }}" maxlength="120" placeholder="Enter exam title">
            </label>
            <button class="btn btn-primary align-self-end" type="submit">Search exams</button>
            <a class="btn btn-outline-secondary align-self-end" href="{{ route('admin.academic-levels.show', $academicLevel) }}">Reset</a>
        </form>
        <div class="list-group list-group-flush">
            @forelse($exams as $exam)
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div><strong>{{ $exam->title }}</strong><div class="small text-muted">{{ $exam->batch->title }} · {{ $exam->batch->course->title }} · {{ $exam->starts_at->format('M j, Y H:i') }}</div></div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.batches.exams.index', $exam->batch) }}">Manage exam</a>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">No exams match this academic level and search.</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $exams->links() }}</div>
    </div>
    <div class="card card-body mb-4"><div class="d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Batches</h2><a href="{{ route('admin.batches.index', ['academic_level_id' => $academicLevel->id]) }}">Manage batches</a></div>
        @php($batches = $academicLevel->courses->flatMap(fn ($course) => $course->batches))
        @forelse($batches as $batch)
            <div class="border-top py-2 mt-2"><strong>{{ $batch->title }}</strong> <span class="text-muted">· {{ $batch->course?->title }} · {{ $batch->branch?->name }}</span>
                <div class="small text-muted">{{ $batch->exams->count() }} exam(s)</div>
                <a href="{{ route('admin.batches.exams.index', $batch) }}" class="btn btn-sm btn-outline-primary mt-1">Manage exams</a>
            </div>
        @empty
            <p class="text-muted mb-0">No batches assigned to this level yet.</p>
        @endforelse
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card card-body"><div class="d-flex justify-content-between align-items-center"><h2 class="h5">Courses</h2><a class="btn btn-sm btn-primary" href="{{ route('admin.courses.create', ['academic_level_id' => $academicLevel->id]) }}">Add course</a></div><ul class="mb-0">@forelse($academicLevel->courses as $course)<li><a href="{{ route('admin.courses.index', ['academic_level_id' => $academicLevel->id]) }}">{{ $course->title }}</a></li>@empty<li class="text-muted">No courses assigned.</li>@endforelse</ul></div></div>
        <div class="col-md-6"><div class="card card-body"><div class="d-flex justify-content-between align-items-center"><h2 class="h5">Website Pages</h2><a class="btn btn-sm btn-primary" href="{{ route('admin.website-pages.create', ['academic_level_id' => $academicLevel->id]) }}">Add page</a></div><ul class="mb-0">@forelse($academicLevel->pages as $page)<li><a href="{{ route('admin.website-pages.edit', $page) }}">{{ $page->title }}</a></li>@empty<li class="text-muted">No pages assigned.</li>@endforelse</ul></div></div>
    </div>
    <div class="card card-body"><h2 class="h5">Update level</h2><form method="POST" action="{{ route('admin.academic-levels.update', $academicLevel) }}" class="row g-3">@csrf @method('PUT')
        <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ $academicLevel->name }}" required></div>
        <div class="col-md-4"><label class="form-label">Slug</label><input class="form-control" name="slug" value="{{ $academicLevel->slug }}"></div>
        <div class="col-md-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="{{ $academicLevel->sort_order }}"></div>
        <div class="col-md-2 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($academicLevel->is_active)> Active</label></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description">{{ $academicLevel->description }}</textarea></div>
        <div class="col-12"><button class="btn btn-primary">Save changes</button></div>
    </form></div>
</div>
@endsection
