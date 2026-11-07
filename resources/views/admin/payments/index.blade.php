@extends('layouts.app')

@section('title', __('Payment review') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin: 12px 0 0;">{{ __('Payment review') }}</h1>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="btn secondary">{{ __('Payment settings') }}</a>
    </div>

    <div class="course-list">
        @forelse($payments as $payment)
            <article class="course-item">
                <div class="meta-row">
                    <div>
                        <span class="badge {{ $payment->status === 'approved' ? 'success' : ($payment->status === 'pending' ? 'warning' : 'info') }}">{{ ucfirst($payment->status) }}</span>
                        <h2 style="margin: 10px 0 6px;">{{ $payment->course->title }}</h2>
                        <div class="muted">{{ __('Student:') }} {{ $payment->user->name }} ({{ $payment->user->email }})</div>
                        <div class="muted">{{ ucfirst($payment->method) }} · BDT {{ number_format($payment->amount, 2) }} · Reference: {{ $payment->transaction_reference }}</div>
                        @if($payment->sender_account)
                            <div class="muted">{{ __('Sender:') }} {{ $payment->sender_account }}</div>
                        @endif
                        @if($payment->admin_note)
                            <div class="muted">{{ __('Review note:') }} {{ $payment->admin_note }}</div>
                        @endif
                    </div>
                    <div>
                        @if($payment->proof_path)
                            <a href="{{ route('admin.payments.proof', $payment) }}" class="btn secondary">{{ __('View proof') }}</a>
                        @endif
                    </div>
                </div>

                @if($payment->status === 'pending')
                    <div class="grid grid-2" style="margin-top: 16px;">
                        <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                            @csrf
                            <button type="submit" class="btn primary">{{ __('Approve and enroll') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                            @csrf
                            <label for="admin-note-{{ $payment->id }}">{{ __('Reject with note (optional)') }}</label>
                            <input id="admin-note-{{ $payment->id }}" type="text" name="admin_note" maxlength="1000" placeholder="{{ __('Explain what needs correction') }}">
                            <button type="submit" class="btn secondary" style="margin-top: 10px;">{{ __('Reject payment') }}</button>
                        </form>
                    </div>
                @elseif($payment->reviewed_at)
                    <div class="muted" style="margin-top: 12px;">Reviewed {{ $payment->reviewed_at->format('M j, Y H:i') }}@if($payment->reviewer) by {{ $payment->reviewer->name }}@endif</div>
                @endif
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">{{ __('No payment submissions yet.') }}</p></div>
        @endforelse
    </div>

    <div style="margin-top: 20px;">{{ $payments->links() }}</div>
@endsection