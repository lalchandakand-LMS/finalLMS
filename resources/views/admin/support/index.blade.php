@extends('layouts.app')

@section('title', __('Support tickets') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('Support tickets') }}</h1>
            <p class="muted">{{ __('Review student questions and reply from the academy.') }}</p>
        </div>
    </div>

    <div class="panel">
        <form method="GET" class="form-grid" style="grid-template-columns:1fr auto;align-items:end;">
            <label>{{ __('Status') }}
                <select name="status">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach(['open', 'in_progress', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn secondary" type="submit">{{ __('Filter') }}</button>
        </form>
        <div class="table-wrap" style="margin-top:16px;">
            <table>
                <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('Student') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last message') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.support-tickets.show', $ticket) }}"><strong>#{{ $ticket->id }} · {{ $ticket->subject }}</strong></a></td>
                            <td>{{ $ticket->user?->name }}<br><small class="muted">{{ $ticket->user?->email }}</small></td>
                            <td>{{ $ticket->branch?->name ?? __('All branches') }}</td>
                            <td><span class="badge {{ $ticket->status === 'closed' ? 'muted' : ($ticket->status === 'open' ? 'warning' : 'info') }}">{{ __(ucwords(str_replace('_', ' ', $ticket->status))) }}</span></td>
                            <td>{{ $ticket->last_message_at?->format('d M Y, h:i A') }}</td>
                            <td><a class="btn secondary" href="{{ route('admin.support-tickets.show', $ticket) }}">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">{{ __('No support tickets yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $tickets->links() }}
    </div>
@endsection
