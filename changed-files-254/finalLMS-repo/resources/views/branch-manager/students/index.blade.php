@extends('layouts.app')

@section('title', __('Branch students') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Branch workspace') }}</span>
            <h1 style="margin:12px 0 0;">{{ auth()->user()->branch?->name }} — {{ __('Students') }}</h1>
            <p class="muted">{{ __('Only students assigned to your branch or enrolled in your branch batches are shown.') }}</p>
        </div>
        <a href="{{ route('admin.branch-workspace.batches.index') }}" class="btn secondary">{{ __('Manage batches') }}</a>
    </div>

    <section class="panel">
        <form method="GET" action="{{ route('admin.branch-workspace.students.index') }}" class="grid grid-2" style="margin-bottom:18px;">
            <label>{{ __('Search name, email or phone') }}
                <input name="search" value="{{ request('search') }}" maxlength="120" placeholder="{{ __('Type a name, email or phone number') }}">
            </label>
            <div style="display:flex;gap:10px;align-items:end;">
                <button class="btn primary" type="submit">{{ __('Search') }}</button>
                <a class="btn secondary" href="{{ route('admin.branch-workspace.students.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>
        <p class="muted">{{ __('Showing') }} {{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} {{ __('of') }} {{ $students->total() }} {{ __('students') }}</p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:720px;">
                <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                    <th style="padding:12px;">{{ __('Student') }}</th>
                    <th style="padding:12px;">{{ __('Email') }}</th>
                    <th style="padding:12px;">{{ __('Phone') }}</th>
                    <th style="padding:12px;">{{ __('Branch batches') }}</th>
                </tr></thead>
                <tbody>
                @forelse($students as $student)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px;">{{ $student->name }}</td>
                        <td style="padding:12px;">{{ $student->email }}</td>
                        <td style="padding:12px;">{{ $student->phone_number ?: '—' }}</td>
                        <td style="padding:12px;">
                            @forelse($student->enrollments as $enrollment)
                                <div>{{ $enrollment->batch?->title ?? $enrollment->course?->title }} <span class="badge info">{{ ucfirst($enrollment->status) }}</span></div>
                            @empty
                                <span class="muted">{{ __('Branch assigned') }}</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted" style="padding:18px;">{{ __('No students found for this branch.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $students->links() }}</div>
    </section>
@endsection
