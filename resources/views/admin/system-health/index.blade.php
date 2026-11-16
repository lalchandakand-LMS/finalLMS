@extends('layouts.app')

@section('title', __('System health') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('System tools') }}</span>
            <h1 style="margin:12px 0 0;">{{ __('Website health') }}</h1>
            <p class="muted">{{ __('Run safe checks for the application database, cache, storage and configured queue.') }}</p>
        </div>
        <a class="btn secondary" href="{{ route('dashboard') }}">{{ __('Back to dashboard') }}</a>
    </div>

    <section class="panel" style="margin-bottom:18px;">
        <h2>{{ __('Scheduler heartbeat') }}</h2>
        @if($schedulerHeartbeat && \Illuminate\Support\Carbon::parse($schedulerHeartbeat)->greaterThan(now()->subMinutes(10)))
            <span class="badge success">{{ __('Recent heartbeat') }}</span>
            <p class="muted">{{ __('Last scheduler heartbeat:') }} {{ \Illuminate\Support\Carbon::parse($schedulerHeartbeat)->diffForHumans() }}</p>
        @elseif($schedulerHeartbeat)
            <span class="badge danger">{{ __('Heartbeat is stale') }}</span>
            <p class="muted">{{ __('Last scheduler heartbeat:') }} {{ \Illuminate\Support\Carbon::parse($schedulerHeartbeat)->diffForHumans() }}. {{ __('Check the cPanel cron configuration.') }}</p>
        @else
            <span class="badge warning">{{ __('No heartbeat recorded yet') }}</span>
            <p class="muted">{{ __('Configure the Laravel scheduler cron job to begin monitoring.') }}</p>
        @endif
    </section>

    <section class="panel" style="margin-bottom:18px;">
        <h2>{{ __('Run health check') }}</h2>
        <p class="muted">{{ __('Checks use temporary data only. They do not modify student, course, enrollment or payment records.') }}</p>
        <form method="POST" action="{{ route('admin.system-health.run') }}">
            @csrf
            <button class="btn primary" type="submit">{{ __('Run checks now') }}</button>
        </form>
    </section>

    <section class="panel">
        <h2>{{ __('Recent health checks') }}</h2>
        @forelse($checks as $check)
            @php($results = json_decode($check->results, true) ?: [])
            <article style="padding:16px 0;border-top:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <strong>{{ ucfirst($check->status) }}</strong>
                    <span class="muted">{{ $check->checked_at }} · {{ number_format($check->duration_ms) }} ms</span>
                </div>
                <div class="grid grid-2" style="margin-top:12px;">
                    @foreach($results as $name => $result)
                        <div>
                            <strong>{{ str($name)->replace('_', ' ')->title() }}</strong>:
                            @if(($result['status'] ?? 'failed') === 'ok')
                                <span class="badge success">{{ __('OK') }}</span>
                                @if(!empty($result['details']))<p class="muted">{{ $result['details'] }}</p>@endif
                            @else
                                <span class="badge danger">{{ __('Failed') }}</span>
                                <p class="muted">{{ $result['message'] ?? __('Check failed.') }}</p>
                            @endif
                            <small class="muted">{{ number_format($result['duration_ms'] ?? 0) }} ms</small>
                        </div>
                    @endforeach
                </div>
            </article>
        @empty
            <p class="muted">{{ __('No health checks have been run yet.') }}</p>
        @endforelse
    </section>
@endsection
