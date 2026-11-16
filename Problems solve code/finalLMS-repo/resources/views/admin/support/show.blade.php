@extends('layouts.app')

@section('title', __('Support ticket') . ' #' . $ticket->id . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <a class="muted" href="{{ route('admin.support-tickets.index') }}">← {{ __('All support tickets') }}</a>
            <h1 style="margin-top:8px;">#{{ $ticket->id }} · {{ $ticket->subject }}</h1>
            <p class="muted">{{ $ticket->user?->name }} · {{ $ticket->user?->email }} · {{ $ticket->branch?->name ?? __('All branches') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.support-tickets.status', $ticket) }}" class="meta-row">
            @csrf
            @method('PATCH')
            <select name="status" aria-label="{{ __('Ticket status') }}">
                @foreach(['open', 'in_progress', 'closed'] as $status)
                    <option value="{{ $status }}" @selected($ticket->status === $status)>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                @endforeach
            </select>
            <button class="btn secondary" type="submit">{{ __('Update status') }}</button>
        </form>
    </div>

    <div class="panel" style="display:grid;gap:14px;">
        @foreach($ticket->messages as $message)
            <article style="padding:14px;border:1px solid var(--border);border-radius:12px;background:{{ $message->sender_role === 'admin' ? 'var(--surface-muted, #f3f7fb)' : 'var(--surface, #fff)' }};">
                <div class="meta-row" style="justify-content:space-between;">
                    <strong>{{ $message->sender?->name ?? __('Deleted account') }} · {{ $message->sender_role === 'admin' ? __('Academy support') : __('Student') }}</strong>
                    <small class="muted">{{ $message->created_at?->format('d M Y, h:i A') }}</small>
                </div>
                <p style="white-space:pre-wrap;margin-bottom:0;">{{ $message->body }}</p>
            </article>
        @endforeach
    </div>

    @if($ticket->status !== 'closed')
        <div class="panel" style="margin-top:16px;">
            <h2>{{ __('Reply to student') }}</h2>
            <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket) }}">
                @csrf
                <label>{{ __('Message') }}
                    <textarea name="message" rows="5" minlength="2" maxlength="5000" required>{{ old('message') }}</textarea>
                </label>
                <button class="btn primary" type="submit">{{ __('Send reply') }}</button>
            </form>
        </div>
    @else
        <div class="panel" style="margin-top:16px;"><p class="muted">{{ __('This ticket is closed. Reopen it above to send another reply.') }}</p></div>
    @endif
@endsection
