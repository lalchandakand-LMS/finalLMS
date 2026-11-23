@extends('layouts.app')

@section('title', __('AI Agent Workspace') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('AI Agent Workspace') }}</span>
        <h1 style="margin:12px 0 0;">{{ __('Agent proposals & approvals') }}</h1>
        <p class="muted">{{ __('AI-generated actions are proposals only. An authorized human reviews the exact details before an approved action is executed.') }}</p>
    </div>
    @if($role === 'student')
        <a class="btn secondary" href="{{ route('student.ai-assistant.index') }}">{{ __('Back to AI Student Agent') }}</a>
    @elseif($role === 'teacher')
        <a class="btn secondary" href="{{ route('teacher.ai-assistant.index') }}">{{ __('Back to AI Teacher Agent') }}</a>
    @else
        <a class="btn secondary" href="{{ route('admin.ai-assistant.index') }}">{{ __('Back to AI Admin Agent') }}</a>
    @endif
</div>
@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($role === 'student')
    <section class="panel">
        <h2>{{ __('Submit a personal study plan for review') }}</h2>
        <p class="muted">{{ __('Describe your learning goals and weekly focus. A teacher assigned to one of your active courses or an administrator must approve it before it becomes an active plan.') }}</p>
        <form method="POST" action="{{ route('student.ai-assistant.proposals.store') }}">
            <input type="hidden" name="idempotency_key" value="{{ (string) \\Illuminate\\Support\\Str::uuid() }}">
            @csrf
            <div class="form-grid">
                <div class="full-width"><label for="plan-title">{{ __('Plan title') }}</label><input id="plan-title" name="title" maxlength="120" required value="{{ old('title') }}"></div>
                <div class="full-width"><label for="plan-goals">{{ __('Learning goals') }}</label><textarea id="plan-goals" name="goals" maxlength="1500" rows="3" required>{{ old('goals') }}</textarea></div>
                <div><label for="plan-day">{{ __('Study day') }}</label><select id="plan-day" name="schedule[0][day]" required><option value="Saturday">Saturday</option><option value="Sunday">Sunday</option><option value="Monday">Monday</option><option value="Tuesday">Tuesday</option><option value="Wednesday">Wednesday</option><option value="Thursday">Thursday</option><option value="Friday">Friday</option></select></div>
                <div><label for="plan-focus">{{ __('Focus') }}</label><input id="plan-focus" name="schedule[0][focus]" maxlength="160" required placeholder="{{ __('e.g. Vocabulary and speaking') }}"></div>
                <div><label for="plan-minutes">{{ __('Minutes') }}</label><input id="plan-minutes" name="schedule[0][minutes]" type="number" min="5" max="240" value="30" required></div>
            </div>
            <button class="btn primary" type="submit">{{ __('Send for human approval') }}</button>
        </form>
    </section>
@if($role === 'student' && $learningPlans->isNotEmpty())
    <section class="panel">
        <h2>{{ __('Approved learning plans') }}</h2>
        @foreach($learningPlans as $plan)
            <article style="padding:12px 0;border-bottom:1px solid var(--border-color, #ddd);">
                <strong>{{ $plan->title }}</strong>
                <p>{{ $plan->goals }}</p>
                @foreach(json_decode($plan->schedule, true) ?: [] as $slot)
                    <p class="muted">{{ $slot['day'] ?? '' }} — {{ $slot['focus'] ?? '' }} ({{ $slot['minutes'] ?? 0 }} {{ __('minutes') }})</p>
                @endforeach
            </article>
        @endforeach
    </section>
@endif
@else
    <section class="panel">
        <h2>{{ __('Pending approval inbox') }}</h2>
        <p class="muted">{{ __('Teachers see proposals only from students enrolled in their own courses. Administrators can review institution-wide proposals. Requesters cannot approve their own proposals.') }}</p>
    </section>
@endif

<div class="grid grid-2">
@forelse($proposals as $proposal)
    @php($payload = json_decode($proposal->payload, true) ?: [])
    <article class="panel">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
            <strong>{{ $payload['title'] ?? __('Study plan proposal') }}</strong>
            <span class="badge {{ $proposal->status === 'pending' ? 'warning' : 'success' }}">{{ __(ucfirst($proposal->status)) }}</span>
        </div>
        @if($role !== 'student')<p class="muted">{{ __('Requested by') }}: {{ $proposal->requester_name }}</p>@endif
        <p>{{ $payload['goals'] ?? '' }}</p>
        @foreach($payload['schedule'] ?? [] as $slot)
            <p class="muted">{{ $slot['day'] ?? '' }} — {{ $slot['focus'] ?? '' }} ({{ $slot['minutes'] ?? 0 }} {{ __('minutes') }})</p>
        @endforeach
        <p class="muted">{{ __('Expires') }}: {{ $proposal->expires_at }}</p>
        @if($canReview && $proposal->status === 'pending' && (int) $proposal->requester_id !== (int) auth()->id())
            <form method="POST" action="{{ route($role.'.ai-assistant.proposals.approve', $proposal->id) }}" style="display:inline;">
                @csrf
                <input type="hidden" name="version" value="{{ $proposal->version }}">
                <button class="btn primary" type="submit">{{ __('Approve & execute') }}</button>
            </form>
            <form method="POST" action="{{ route($role.'.ai-assistant.proposals.reject', $proposal->id) }}" style="margin-top:8px;">
                @csrf
                <input type="hidden" name="version" value="{{ $proposal->version }}">
                <input name="review_note" maxlength="500" placeholder="{{ __('Optional review note') }}">
                <button class="btn secondary" type="submit">{{ __('Reject') }}</button>
            </form>
        @endif
    </article>
@empty
    <section class="panel"><p class="muted">{{ $role === 'student' ? __('You have not submitted a study plan yet.') : __('There are no pending proposals in your review scope.') }}</p></section>
@endforelse
</div>
@endsection
