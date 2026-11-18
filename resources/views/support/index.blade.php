@extends('layouts.app')

@section('title', 'AI Support & Help Desk')

@section('content')
<div class="page-header"><div><h1>AI Support & Help Desk</h1><p class="muted">Submit an issue and receive an initial diagnostic checklist.</p></div></div>
<section class="panel" style="max-width:850px;margin-bottom:18px;">
    <h2>Report a problem</h2>
    <form method="POST" action="{{ route('support.store') }}">
        @csrf
        <label>Subject</label><input name="subject" maxlength="180" required>
        <label>Describe the issue</label><textarea name="description" rows="4" maxlength="5000" required placeholder="What happened? Which page? What did you expect?"></textarea>
        <p class="muted">Never include passwords, OTPs, payment PINs, API keys, or private identity documents.</p>
        <button class="btn primary" type="submit">Diagnose & submit</button>
    </form>
</section>
<div style="max-width:850px;">
@forelse($tickets as $ticket)
<article class="panel" style="margin-bottom:12px;">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong>
        <span class="badge info">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</span>
    </div>
    @if(auth()->user()->isAdmin())<p class="muted">By {{ $ticket->user->name }} · {{ $ticket->category }} · {{ $ticket->priority }}</p>@endif
    <p style="white-space:pre-wrap;">{{ $ticket->description }}</p>
    <div style="background:var(--surface-muted);padding:12px;border-radius:8px;">
        <strong>Initial diagnostic suggestion</strong>
        <p>{{ $ticket->diagnostic_summary }}</p>
    </div>
    @if(auth()->user()->isAdmin())
    <form method="POST" action="{{ route('support.update', $ticket) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
        @csrf @method('PATCH')
        <select name="status"><option value="open" @selected($ticket->status === 'open')>Open</option><option value="in_progress" @selected($ticket->status === 'in_progress')>In progress</option><option value="waiting" @selected($ticket->status === 'waiting')>Waiting</option><option value="resolved" @selected($ticket->status === 'resolved')>Resolved</option><option value="closed" @selected($ticket->status === 'closed')>Closed</option></select>
        <select name="priority"><option value="low">Low</option><option value="normal" @selected($ticket->priority === 'normal')>Normal</option><option value="high" @selected($ticket->priority === 'high')>High</option><option value="urgent" @selected($ticket->priority === 'urgent')>Urgent</option></select>
        <button class="btn primary" type="submit">Update ticket</button>
    </form>
    @endif
</article>
@empty
<div class="panel">No support requests yet.</div>
@endforelse
{{ $tickets->links() }}
</div>
@endsection
