@extends('layouts.app')
@section('title', __('Student Management'))
@section('content')
<div class="page-header"><div><span class="badge info">Admin</span><h1>{{ __('Advanced Student Management') }}</h1><p class="muted">Profiles, lifecycle, guardian relationships and learning records.</p></div></div>
<div class="panel">
<form method="GET" class="grid grid-3" style="margin-bottom:18px">
<div><label>Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email or phone"></div>
<div><label>Status</label><select name="status"><option value="">All statuses</option>@foreach(['active','suspended','graduated','transferred'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
<div style="display:flex;gap:8px;align-items:end"><button class="btn primary">Filter</button><a class="btn secondary" href="{{ route('admin.students.index') }}">Reset</a></div>
</form>
<div style="overflow-x:auto"><table style="width:100%;min-width:760px"><thead><tr><th>Student</th><th>Student ID</th><th>Branch</th><th>Enrollments</th><th>Guardians</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($students as $student)<tr><td>{{ $student->name }}<br><small class="muted">{{ $student->email }}</small></td><td>{{ $student->studentProfile?->student_number ?? '—' }}</td><td>{{ $student->branch?->name ?? '—' }}</td><td>{{ $student->enrollments_count }}</td><td>{{ $student->guardian_links_as_student_count }}</td><td><span class="badge {{ $student->studentProfile?->lifecycle_status === 'suspended' ? 'warning' : 'success' }}">{{ ucfirst($student->studentProfile?->lifecycle_status ?? 'active') }}</span></td><td><a class="btn secondary" href="{{ route('admin.students.show', $student) }}">Manage</a></td></tr>@empty<tr><td colspan="7">No students found.</td></tr>@endforelse
</tbody></table></div>
<div style="margin-top:16px">{{ $students->links() }}</div>
</div>
@endsection
