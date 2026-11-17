@extends('layouts.app')

@section('title', __('Attendance sync review queue'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge warning">{{ __('Attendance integrity') }}</span>
        <h1>{{ __('Stale offline attendance review') }}</h1>
        <p class="muted">{{ __('Review captures that exceeded the institution location-age policy. Approval creates an attendance record and immutable audit event.') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;"><a href="{{ route('admin.attendance-audit.index') }}" class="btn secondary">{{ __('Audit history') }}</a><a href="{{ route('admin.settings.index') }}" class="btn secondary">{{ __('Attendance policy settings') }}</a></div>
</div>

<div class="panel" style="padding:20px;">
    @if($reviews->isEmpty())
        <p class="muted">{{ __('There are no attendance records waiting for review.') }}</p>
    @else
        @foreach($reviews as $review)
            <article style="padding:16px 0;border-bottom:1px solid var(--border);">
                <div class="grid grid-2">
                    <div>
                        <h2 style="margin:0 0 8px;">{{ $review->student?->name ?? __('Student') }} — {{ $review->batch?->title ?? __('Batch') }}</h2>
                        <p class="muted" style="margin:0 0 8px;">{{ __('Instructor') }}: {{ $review->teacher?->name ?? '—' }}</p>
                        <p>{{ __('Class date') }}: {{ $review->attendance_date->format('Y-m-d') }} · {{ __('Proposed status') }}: <strong>{{ __(ucfirst($review->attendance_status)) }}</strong></p>
                        <p>{{ __('Captured') }}: {{ $review->location_captured_at?->toDateTimeString() ?? '—' }} · {{ __('Accuracy') }}: {{ $review->location_accuracy_meters }} m</p>
                        <p class="muted">{{ __('Coordinates') }}: {{ $review->location_latitude }}, {{ $review->location_longitude }}</p>
                    </div>
                    <div>
                        <form method="POST" action="{{ route('admin.attendance-sync-reviews.resolve', $review) }}" style="display:grid;gap:10px;">
                            @csrf
                            <label>{{ __('Review reason (required)') }}
                                <textarea name="reason" rows="2" minlength="5" maxlength="500" required placeholder="{{ __('Explain the verification performed') }}"></textarea>
                            </label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="btn primary" type="submit" name="decision" value="approve">{{ __('Approve attendance') }}</button>
                                <button class="btn secondary" type="submit" name="decision" value="reject">{{ __('Reject capture') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
        <div style="margin-top:16px;">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection
