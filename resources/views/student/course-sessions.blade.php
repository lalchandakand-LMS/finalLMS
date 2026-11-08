@extends('layouts.app')
@section('title', __('My course sessions'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Student') }}</span><h1>{{ __('My course sessions') }}</h1><p class="muted">{{ __('Check in when your enrolled class starts, then check out when you finish.') }}</p></div></div>
@forelse($sessions as $session)@php($record=$attendance->get($session->id))
<div class="panel" style="margin-bottom:14px"><h2>{{ $session->title }}</h2><p>{{ $session->course?->title }} · {{ $session->batch?->title }}</p><p>{{ $session->starts_at->format('d M Y h:i A') }} — {{ $session->ends_at?->format('h:i A') }}</p>
@if($record)<p class="badge success">{{ __('Checked in') }}: {{ $record->checked_in_at?->format('h:i A') }}</p>@if($record->checked_out_at)<p>{{ __('Checked out') }}: {{ $record->checked_out_at->format('h:i A') }}</p>@else<form method="POST" action="{{ route('student.course-attendances.check-out',$record) }}">@csrf<button class="btn primary">{{ __('Check out') }}</button></form>@endif
@else<form method="POST" action="{{ route('student.course-sessions.check-in',$session) }}">@csrf<button class="btn primary" @disabled(now()->lt($session->starts_at)||now()->gt($session->ends_at))>{{ __('Check in') }}</button></form>@endif
</div>
@empty<div class="panel">{{ __('No upcoming course sessions are scheduled for your enrollments.') }}</div>@endforelse
{{ $sessions->links() }}
@endsection