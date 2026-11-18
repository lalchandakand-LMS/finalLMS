@extends('layouts.app')

@section('title', __('Payment audit trail') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Finance control') }}</span>
            <h1>{{ __('Payment audit trail') }}</h1>
            <p class="muted">{{ __('Append-only financial events with actor, source, correlation and before/after state. Sensitive gateway credentials are never stored here.') }}</p>
        </div>
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <form method="GET" class="grid grid-3">
            <div><label for="payment_id">{{ __('Payment ID') }}</label><input id="payment_id" name="payment_id" type="number" value="{{ $filters['payment_id'] ?? '' }}"></div>
            <div><label for="invoice_payment_id">{{ __('Invoice receipt ID') }}</label><input id="invoice_payment_id" name="invoice_payment_id" type="number" value="{{ $filters['invoice_payment_id'] ?? '' }}"></div>
            <div><label for="invoice_refund_id">{{ __('Refund ID') }}</label><input id="invoice_refund_id" name="invoice_refund_id" type="number" value="{{ $filters['invoice_refund_id'] ?? '' }}"></div>
            <div><label for="event">{{ __('Event') }}</label><input id="event" name="event" maxlength="80" value="{{ $filters['event'] ?? '' }}"></div>
            <div><button class="btn primary" type="submit">{{ __('Filter audit trail') }}</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Event') }}</th><th>{{ __('Payment / receipt / refund') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Source') }}</th><th>{{ __('Before') }}</th><th>{{ __('After') }}</th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->created_at?->format('d M Y H:i:s') }}</td>
                        <td>{{ $event->event }}</td>
                        <td>{{ $event->payment_id ? 'Payment #'.$event->payment_id : '—' }} / {{ $event->invoice_payment_id ? 'Receipt #'.$event->invoice_payment_id : '—' }} / {{ $event->invoice_refund_id ? 'Refund #'.$event->invoice_refund_id : '—' }}</td>
                        <td>{{ $event->actor?->name ?? __('System / gateway') }}</td>
                        <td>{{ $event->source }}@if($event->provider) · {{ $event->provider }}@endif</td>
                        <td><code>{{ json_encode($event->before_state, JSON_UNESCAPED_UNICODE) }}</code></td>
                        <td><code>{{ json_encode($event->after_state, JSON_UNESCAPED_UNICODE) }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="7">{{ __('No payment audit events found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $events->links() }}
    </section>
@endsection
