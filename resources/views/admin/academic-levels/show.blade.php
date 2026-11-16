@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="mb-4"><a href="{{ route('admin.academic-levels.index') }}">← Academic Levels</a><h1 class="h3 mt-2">{{ $academicLevel->name }}</h1><p class="text-muted">{{ $academicLevel->description }}</p></div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card card-body"><h2 class="h5">Courses</h2><ul class="mb-0">@forelse($academicLevel->courses as $course)<li><a href="{{ route('admin.courses.index', ['academic_level_id' => $academicLevel->id]) }}">{{ $course->title }}</a></li>@empty<li class="text-muted">No courses assigned.</li>@endforelse</ul></div></div>
        <div class="col-md-6"><div class="card card-body"><h2 class="h5">Website Pages</h2><ul class="mb-0">@forelse($academicLevel->pages as $page)<li><a href="{{ route('admin.website-pages.edit', $page) }}">{{ $page->title }}</a></li>@empty<li class="text-muted">No pages assigned.</li>@endforelse</ul></div></div>
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
