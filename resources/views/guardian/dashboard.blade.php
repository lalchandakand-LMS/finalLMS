@extends('layouts.app')

@section('title', __('Guardian Dashboard') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Guardian portal') }}</span><h1>{{ __('My children') }}</h1><p class="muted">{{ __('Monitor linked learners’ courses, attendance and fee records.') }}</p></div>
</div>
@if($links->isEmpty())
    <div class="panel"><h2>{{ __('No student linked yet') }}</h2><p class="muted">{{ __('Please contact the academy administrator to securely link your guardian account to a student.') }}</p></div>
@else
    <div class="grid grid-2">
        @foreach($links as $link)
            @php($child = $link->student)
            <article class="card">
                <h2>{{ $child->name }}</h2>
                <p class="muted">{{ $child->email }}</p>
                <div class="grid grid-3">
                    <div><strong>{{ $child->enrollments->count() }}</strong><small class="muted" style="display:block">{{ __('Courses') }}</small></div>
                    <div><strong>{{ $child->attendances->where('status', 'present')->count() }}</strong><small class="muted" style="display:block">{{ __('Present') }}</small></div>
                    <div><strong>{{ $child->invoices->whereIn('status', ['pending','overdue'])->count() }}</strong><small class="muted" style="display:block">{{ __('Due invoices') }}</small></div>
                </div>
                <a class="btn primary" style="margin-top:16px" href="{{ route('guardian.children.show', $link) }}">{{ __('View learner report') }}</a>
            </article>
        @endforeach
    </div>
@endif
@endsection
