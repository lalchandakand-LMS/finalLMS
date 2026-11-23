@extends('layouts.app')

@section('title', 'Branches and batches | ' . config('app.name'))

@section('content')
<div class="panel" style="margin-bottom:16px;"><form method="GET" action="{{ route('admin.batches.index') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
    <label>Academic level<select name="academic_level_id"><option value="">All levels</option>@foreach($academicLevels as $academicLevel)<option value="{{ $academicLevel->id }}" @selected((string) ($filters['academic_level_id'] ?? '') === (string) $academicLevel->id)>{{ $academicLevel->name }}</option>@endforeach</select></label>
    <button class="btn primary" type="submit">Filter</button><a class="btn secondary" href="{{ route('admin.batches.index') }}">Reset</a>
</form></div>
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin:12px 0 0;">Branches and batches</h1>
        </div>
        <a href="{{ route('admin.courses.index') }}" class="btn secondary">Courses</a>
    </div>

    <div class="grid grid-2" style="margin-bottom:24px;">
        <section class="panel">
            <h2 style="margin-top:0;">Add branch</h2>
            <form method="POST" action="{{ route('admin.branches.store') }}">
                @csrf
                <label>Branch name<input name="name" maxlength="120" required></label>
                <label>Address<input name="address" maxlength="255"></label>
                <label>Phone<input name="phone" maxlength="40"></label>
                <button class="btn primary" type="submit">Save branch</button>
            </form>
            <h3 style="margin-top:22px;">Current branches</h3>
            <ul>
                @forelse($branches as $branch)
                    <li style="margin-bottom:10px;">
                        {{ $branch->name }} <span class="muted">({{ $branch->batches_count }} batches)</span>
                        <details style="margin-top:6px;">
                            <summary>Edit branch</summary>
                            <form method="POST" action="{{ route('admin.branches.update', $branch) }}" style="margin-top:10px;">
                                @csrf
                                @method('PUT')
                                <label>Name<input name="name" value="{{ $branch->name }}" maxlength="120" required></label>
                                <label>Address<input name="address" value="{{ $branch->address }}" maxlength="255"></label>
                                <label>Phone<input name="phone" value="{{ $branch->phone }}" maxlength="40"></label>
                                <input type="hidden" name="is_active" value="0">
                                <label><input type="checkbox" name="is_active" value="1" style="width:auto;" @checked($branch->is_active)> Active</label>
                                <button class="btn primary" type="submit">Save branch</button>
                            </form>
                            @if($branch->batches_count === 0)
                                <form method="POST" action="{{ route('admin.branches.destroy', $branch) }}" style="margin-top:8px;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn secondary" type="submit">Delete branch</button>
                                </form>
                            @endif
                        </details>
                    </li>
                @empty
                    <li class="muted">No branches created.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <h2 style="margin-top:0;">Schedule batch</h2>
            <form method="POST" action="{{ route('admin.batches.store') }}">
                @csrf
                <label>Batch name<input name="title" maxlength="160" required></label>
                <label>Branch<select name="branch_id" required><option value="">Select branch</option>@foreach($branches->where('is_active', true) as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
                <label>Course<select name="course_id" required><option value="">Select course</option>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
                <label>Teacher<select name="teacher_id" required><option value="">Select teacher</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></label>
                <label>Capacity<input type="number" name="capacity" min="1" max="1000" required></label>
                <label>Start<input type="datetime-local" name="starts_at" required></label>
                <label>End<input type="datetime-local" name="ends_at" required></label>
                <label>Fee mode<select name="fee_calculation_mode"><option value="fixed">Fixed course fee</option><option value="per_class">Per attended class</option></select></label>
                <label>Per-class fee (BDT)<input type="number" name="per_class_fee" min="0.01" step="0.01"></label>
                <label>Fixed fee (BDT)<input type="number" name="fee" min="0" step="0.01" value="0" required></label>
                <button class="btn primary" type="submit" @disabled($branches->isEmpty() || $courses->isEmpty() || $teachers->isEmpty())>Create batch</button>
            </form>
        </section>
    </div>

    <h2>Scheduled batches</h2>
    <div class="course-list">
        @forelse($batches as $batch)
            <article class="course-item">
                <div class="meta-row">
                    <div>
                        <span class="badge {{ $batch->status === 'open' ? 'success' : 'warning' }}">{{ ucfirst($batch->status) }}</span>
                        <h3 style="margin:10px 0 6px;">{{ $batch->title }}</h3>
                        <div class="muted">{{ $batch->course->title }} · {{ $batch->branch->name }} · Teacher: {{ $batch->teacher->name }}</div>
                        <div class="muted">{{ $batch->starts_at->format('M j, Y H:i') }} to {{ $batch->ends_at->format('M j, Y H:i') }} · Capacity {{ $batch->capacity }} · BDT {{ number_format($batch->fee, 2) }}</div>
                        <a href="{{ route('admin.batches.attendance.index', $batch) }}" class="btn secondary" style="margin-top:10px;">Attendance</a>
                        @if($batch->fee_calculation_mode === 'per_class')<form method="POST" action="{{ route('admin.batches.attendance-fees.generate', $batch) }}" style="display:inline-flex;gap:8px;align-items:center;margin-top:10px;">@csrf<input type="month" name="billing_period" value="{{ now()->subMonth()->format('Y-m') }}" required><input type="date" name="due_date" value="{{ now()->addDays(7)->toDateString() }}"><button class="btn primary" type="submit">Generate class-fee invoices</button></form>@endif
                        <a href="{{ route('admin.batches.exams.index', $batch) }}" class="btn secondary" style="margin-top:10px;">Exams & Question Bank</a>
                    </div>
                </div>
                <details style="margin-top:14px;">
                    <summary>Edit batch</summary>
                    <form method="POST" action="{{ route('admin.batches.update', $batch) }}" class="grid grid-2" style="margin-top:14px;">
                        @csrf
                        @method('PUT')
                        <label>Batch name<input name="title" value="{{ $batch->title }}" maxlength="160" required></label>
                        <label>Branch<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($batch->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                        <label>Course<select name="course_id" required>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($batch->course_id === $course->id)>{{ $course->title }}</option>@endforeach</select></label>
                        <label>Teacher<select name="teacher_id" required>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($batch->teacher_id === $teacher->id)>{{ $teacher->name }}</option>@endforeach</select></label>
                        <label>Capacity<input type="number" name="capacity" min="1" max="1000" value="{{ $batch->capacity }}" required></label>
                        <label>Fee mode<select name="fee_calculation_mode"><option value="fixed" @selected(($batch->fee_calculation_mode ?? 'fixed') === 'fixed')>Fixed course fee</option><option value="per_class" @selected($batch->fee_calculation_mode === 'per_class')>Per attended class</option></select></label>
                        <label>Per-class fee (BDT)<input type="number" name="per_class_fee" min="0.01" step="0.01" value="{{ $batch->per_class_fee }}"></label>
                        <label>Fixed fee (BDT)<input type="number" name="fee" min="0" step="0.01" value="{{ $batch->fee }}" required></label>
                        <label>Start<input type="datetime-local" name="starts_at" value="{{ $batch->starts_at->format('Y-m-d\TH:i') }}" required></label>
                        <label>End<input type="datetime-local" name="ends_at" value="{{ $batch->ends_at->format('Y-m-d\TH:i') }}" required></label>
                        <label>Status<select name="status"><option value="open" @selected($batch->status === 'open')>Open</option><option value="closed" @selected($batch->status === 'closed')>Closed</option><option value="completed" @selected($batch->status === 'completed')>Completed</option></select></label>
                        <button class="btn primary" type="submit">Save batch</button>
                    </form>
                    <form method="POST" action="{{ route('admin.batches.destroy', $batch) }}" style="margin-top:10px;">
                        @csrf
                        @method('DELETE')
                        <button class="btn secondary" type="submit">Delete batch</button>
                    </form>
                </details>
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">No batches scheduled yet.</p></div>
        @endforelse
    </div>
@endsection