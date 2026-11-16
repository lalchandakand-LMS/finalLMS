@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h1 class="h3 mb-1">Academic Levels</h1><p class="text-muted mb-0">Create independent academic spaces for courses, batches, exams and pages.</p></div>
    </div>
    <div class="card mb-4"><div class="card-header">Create Academic Level</div><div class="card-body">
        <form method="POST" action="{{ route('admin.academic-levels.store') }}" class="row g-3">@csrf
            <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="120" required></div>
            <div class="col-md-4"><label class="form-label">URL slug (optional)</label><input class="form-control" name="slug" maxlength="140"></div>
            <div class="col-md-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" min="0" value="0"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> Active</label></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" maxlength="1000" rows="2"></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Create level</button></div>
        </form>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Level</th><th>Courses</th><th>Pages</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($levels as $level)<tr><td><strong>{{ $level->name }}</strong><div class="small text-muted">{{ $level->slug }}</div></td><td>{{ $level->courses_count }}</td><td>{{ $level->pages_count }}</td><td>{{ $level->is_active ? 'Active' : 'Inactive' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.academic-levels.show', $level) }}">Manage</a></td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No academic levels yet.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
