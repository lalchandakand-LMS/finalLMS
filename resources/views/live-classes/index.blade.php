@extends('layouts.app')

@section('title', __('Live classes') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Learning room</span>
            <h1 style="margin:12px 0 0;">{{ __('Live classes') }}</h1>
            <p class="muted">{{ __('Your scheduled online lessons and meeting details.') }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn secondary">Dashboard</a>
    </div>

    <div class="course-list">
        @forelse($liveClasses as $liveClass)
            @php
                $studentCanJoin = ! auth()->user()->isStudent() || $liveClass->batch->enrollments->first()?->status === 'active';
                $joinWindowOpen = $studentCanJoin
                    && now()->greaterThanOrEqualTo($liveClass->starts_at->copy()->subMinutes(15))
                    && now()->lessThanOrEqualTo($liveClass->ends_at)
                    && in_array($liveClass->status, ['scheduled', 'live'], true);
                $canManage = auth()->user()->isAdmin()
                    || (auth()->user()->isTeacher() && $liveClass->batch->teacher_id === auth()->id());
            @endphp
            <article class="course-item">
                <div class="meta-row" style="margin-top:0;">
                    <span class="badge {{ $liveClass->status === 'live' ? 'success' : ($liveClass->status === 'cancelled' ? 'warning' : 'info') }}">{{ ucfirst($liveClass->status) }}</span>
                    <span class="muted">{{ $liveClass->starts_at->format('M j, Y · g:i A') }} – {{ $liveClass->ends_at->format('g:i A') }}</span>
                </div>
                <h2 style="margin:12px 0 6px;">{{ $liveClass->title }}</h2>
                <p class="muted">{{ $liveClass->batch->course->title }} · {{ $liveClass->batch->title }} · {{ $liveClass->batch->branch->name }}</p>
                @if($liveClass->description)
                    <p>{{ $liveClass->description }}</p>
                @endif
                @if(auth()->user()->isStudent())
                    @if($joinWindowOpen)
                        <a class="btn primary" href="{{ $liveClass->meeting_url }}" target="_blank" rel="noopener noreferrer">{{ __('Join live class') }}</a>
                    @elseif($liveClass->status === 'completed' && $liveClass->recording_url)
                        <a class="btn primary" href="{{ $liveClass->recording_url }}" target="_blank" rel="noopener noreferrer">{{ __('Watch class recording') }}</a>
                    @else
                        <span class="muted">The join link opens 15 minutes before class. A recording appears here after the teacher publishes it.</span>
                    @endif
                @else
                    <a class="btn secondary" href="{{ $liveClass->meeting_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open meeting link') }}</a>
                    @if($liveClass->recording_url)
                        <a class="btn secondary" href="{{ $liveClass->recording_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open recording') }}</a>
                    @endif
                @endif
                @if($canManage)
                    <details style="margin-top:14px;">
                        <summary>{{ __('Edit session') }}</summary>
                        <form method="POST" action="{{ route('live-classes.update', $liveClass) }}" class="grid grid-2" style="margin-top:12px;">
                            @csrf
                            @method('PUT')
                            <label>Title<input name="title" value="{{ $liveClass->title }}" maxlength="160" required></label>
                            <label>Meeting URL<input type="url" name="meeting_url" value="{{ $liveClass->meeting_url }}" maxlength="2048" required></label>
                            <label>Recording URL (published after class)<input type="url" name="recording_url" value="{{ $liveClass->recording_url }}" maxlength="2048"></label>
                            <label>Start<input type="datetime-local" name="starts_at" value="{{ $liveClass->starts_at->format('Y-m-d\TH:i') }}" required></label>
                            <label>End<input type="datetime-local" name="ends_at" value="{{ $liveClass->ends_at->format('Y-m-d\TH:i') }}" required></label>
                            <label>Status<select name="status">@foreach(['scheduled', 'live', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected($liveClass->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                            <label>Description<textarea name="description">{{ $liveClass->description }}</textarea></label>
                            <button class="btn primary" type="submit">{{ __('Save changes') }}</button>
                        </form>
                        <form method="POST" action="{{ route('live-classes.destroy', $liveClass) }}" style="margin-top:8px;" onsubmit="return confirm('Delete this live class?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn secondary" type="submit">{{ __('Delete session') }}</button>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">{{ __('No live classes are scheduled for your account yet.') }}</p></div>
        @endforelse
    </div>

    <div style="margin-top:20px;">{{ $liveClasses->links() }}</div>
@endsection
