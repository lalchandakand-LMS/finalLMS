@extends('layouts.app')
@section('title', __('Board Solver Review Queue'))
@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin') }}</span>
        <h1>{{ __('Board Solver Review Queue') }}</h1>
        <p class="muted">{{ __('Review AI-generated learning solutions before adding them to the reusable exam question sets.') }}</p>
    </div>
</div>
<div class="panel" style="margin-bottom:18px">
    <form method="GET" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <label>{{ __('Review status') }}
            <select name="status">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['pending','approved','rejected'] as $option)
                    <option value="{{ $option }}" @selected(($status ?? '') === $option)>{{ __(ucfirst($option)) }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn secondary" type="submit">{{ __('Filter') }}</button>
        <a class="btn secondary" href="{{ route('admin.board-solver.history') }}">{{ __('Clear') }}</a>
    </form>
</div>
<div style="display:grid;gap:16px">
@forelse($history as $item)
    <article class="panel">
        <div class="page-header" style="margin-bottom:10px">
            <div>
                <strong>{{ $item->subject }}</strong>
                <div class="muted" style="font-size:.88rem">
                    {{ $item->student_name ?: __('Deleted student') }} ·
                    {{ $item->board ?: '—' }} / {{ $item->exam_year ?: '—' }} ·
                    {{ $item->class_level ?: '—' }} · {{ $item->question_type }} · {{ $item->created_at }}
                </div>
            </div>
            <span class="badge {{ $item->review_status === 'approved' ? 'success' : ($item->review_status === 'rejected' ? 'danger' : 'warning') }}">
                {{ __(ucfirst($item->review_status ?: 'pending')) }}
            </span>
        </div>
        <h3>{{ __('Question') }}</h3>
        <div style="white-space:pre-wrap;line-height:1.7">{{ $item->question }}</div>
        <details style="margin-top:12px">
            <summary style="cursor:pointer;font-weight:700">{{ __('View AI solution for review') }}</summary>
            <div style="white-space:pre-wrap;line-height:1.8;margin-top:10px">{{ $item->solution }}</div>
        </details>
        @if($item->admin_notes)
            <p class="muted"><strong>{{ __('Review note') }}:</strong> {{ $item->admin_notes }}</p>
        @endif
        @if($item->question_set_id)
            <p><a class="btn secondary" href="{{ route('admin.question-sets.edit', $item->question_set_id) }}">{{ __('Open draft question set') }}</a></p>
        @endif
        @if(($item->review_status ?: 'pending') === 'pending')
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:14px">
                <form method="POST" action="{{ route('admin.board-solver.history.approve', $item->id) }}" class="panel" style="padding:14px">
                    @csrf
                    <h3 style="margin-top:0">{{ __('Approve into Question Bank') }}</h3>
                    <label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="5" required></label>
                    <label>{{ __('Admin note (optional)') }}<textarea name="admin_notes" rows="2" maxlength="2000"></textarea></label>
                    <button class="btn primary" type="submit">{{ __('Approve & create draft question set') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.board-solver.history.reject', $item->id) }}" class="panel" style="padding:14px">
                    @csrf
                    <h3 style="margin-top:0">{{ __('Reject solution') }}</h3>
                    <label>{{ __('Reason (required)') }}<textarea name="admin_notes" rows="3" maxlength="2000" required></textarea></label>
                    <button class="btn danger" type="submit">{{ __('Reject') }}</button>
                </form>
            </div>
        @endif
    </article>
@empty
    <div class="panel muted" style="text-align:center">{{ __('No solver submissions found for this filter.') }}</div>
@endforelse
</div>
<div style="margin-top:18px">{{ $history->links() }}</div>
@endsection
