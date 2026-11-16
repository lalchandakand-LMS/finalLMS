@extends('layouts.app')

@section('title', __('SMS delivery report') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('SMS campaign report') }}</span>
            <h1 style="margin:12px 0 0;">{{ $campaign->title }}</h1>
            <p class="muted">{{ $campaign->message }}</p>
        </div>
        <a href="{{ route('admin.sms-campaigns.index') }}" class="btn secondary">{{ __('Back to campaigns') }}</a>
    </div>

    <div class="grid grid-4" style="margin-bottom:20px;">
        <div class="panel"><small class="muted">{{ __('Pending') }}</small><h2>{{ number_format($counts['pending']) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Sent') }}</small><h2>{{ number_format($counts['sent']) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Failed') }}</small><h2>{{ number_format($counts['failed']) }}</h2></div>
        <div class="panel"><small class="muted">{{ __('Skipped') }}</small><h2>{{ number_format($counts['skipped']) }}</h2></div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('admin.sms-campaigns.show', $campaign) }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:18px;">
            <div>
                <label for="recipient-status">{{ __('Delivery status') }}</label>
                <select id="recipient-status" name="status">
                    <option value="">{{ __('All recipients') }}</option>
                    @foreach(['pending', 'sent', 'failed', 'skipped'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn primary" type="submit">{{ __('Filter') }}</button>
            <a class="btn secondary" href="{{ route('admin.sms-campaigns.show', $campaign) }}">{{ __('Reset') }}</a>
        </form>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:760px;">
                <thead><tr style="text-align:left;border-bottom:1px solid var(--border);">
                    <th style="padding:12px 10px;">{{ __('Recipient') }}</th>
                    <th style="padding:12px 10px;">{{ __('Mobile number') }}</th>
                    <th style="padding:12px 10px;">{{ __('Status') }}</th>
                    <th style="padding:12px 10px;">{{ __('Sent at') }}</th>
                    <th style="padding:12px 10px;">{{ __('Delivery note') }}</th>
                </tr></thead>
                <tbody>
                @forelse($recipients as $recipient)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:12px 10px;">{{ $recipient->user?->name ?? __('Deleted account') }}<br><small class="muted">{{ $recipient->user?->email ?? '—' }}</small></td>
                        <td style="padding:12px 10px;">{{ $recipient->user?->phone_number ?? '—' }}</td>
                        <td style="padding:12px 10px;"><span class="badge {{ $recipient->status === 'sent' ? 'success' : ($recipient->status === 'failed' ? 'danger' : 'warning') }}">{{ __(ucfirst($recipient->status)) }}</span></td>
                        <td style="padding:12px 10px;">{{ $recipient->sent_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        <td style="padding:12px 10px;">{{ $recipient->error_message ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No recipients match this filter.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $recipients->links() }}</div>
    </div>
@endsection
