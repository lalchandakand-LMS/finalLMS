@extends('layouts.app')

@section('title', __('Payment reconciliation') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Finance control') }}</span>
            <h1>{{ __('Payment reconciliation') }}</h1>
            <p class="muted">{{ __('Compare local bKash payment records with the gateway. Reconciliation is read-only and never changes payment status.') }}</p>
        </div>
    </div>

    <section class="panel" style="margin-bottom:20px;">
        <p>{{ __('Eligible bKash payments') }}: <strong>{{ $pending }}</strong></p>
        <form method="POST" action="{{ route('admin.payment-reconciliation.run') }}">
            @csrf
            <label for="payment_id">{{ __('Online payment ID') }}</label>
            <input id="payment_id" name="payment_id" type="number" min="1" placeholder="{{ __('Course / eBook payment ID') }}">
            <label for="invoice_payment_id">{{ __('Invoice receipt ID') }}</label>
            <input id="invoice_payment_id" name="invoice_payment_id" type="number" min="1" placeholder="{{ __('Invoice payment receipt ID') }}">
            <label for="after_payment_id">{{ __('Continue after payment ID') }}</label>
            <input id="after_payment_id" name="after_payment_id" type="number" min="0" value="{{ request('after_payment_id') }}" placeholder="{{ __('Blank starts at the first payment') }}">
            <button class="btn primary" type="submit">{{ __('Run reconciliation') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('Reconciliation history') }}</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('Checked') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Payment / gateway ID') }}</th><th>{{ __('Local') }}</th><th>{{ __('Gateway') }}</th><th>{{ __('Amounts') }}</th><th>{{ __('Result') }}</th><th>{{ __('Details') }}</th></tr></thead>
                <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>{{ $record->checked_at?->format('d M Y H:i') }}</td>
                        <td>{{ $record->payment_id ? 'Payment #'.$record->payment_id : 'Invoice receipt #'.$record->invoice_payment_id }}</td>
                        <td>{{ $record->gateway_payment_id }}</td>
                        <td>{{ $record->local_status }}</td>
                        <td>{{ $record->gateway_status ?: '—' }}</td>
                        <td>{{ $record->local_amount }} / {{ $record->gateway_amount ?? '—' }} {{ $record->currency }}</td>
                        <td><span class="badge {{ $record->result === 'matched' ? 'success' : 'warning' }}">{{ __(ucfirst($record->result)) }}</span></td>
                        <td>{{ $record->details }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">{{ __('No reconciliation runs yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    </section>
@endsection
