@extends('layouts.app')

@section('title', __('Activity Log'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ __('Activity Log') }}</h1>
            <p class="muted">{{ __('Successful changes made by signed-in administrators, teachers, and students.') }}</p>
        </div>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="grid grid-2" style="margin-bottom:20px;">
            <div>
                <label for="user_id">{{ __('User ID') }}</label>
                <input id="user_id" name="user_id" type="number" min="1" value="{{ request('user_id') }}">
            </div>
            <div>
                <label for="method">{{ __('Action') }}</label>
                <select id="method" name="method">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach(['POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                        <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>
            <div><button class="btn primary" type="submit">{{ __('Filter log') }}</button></div>
        </form>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; min-width:760px;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px;">{{ __('Time') }}</th>
                        <th style="text-align:left;padding:10px;">{{ __('User') }}</th>
                        <th style="text-align:left;padding:10px;">{{ __('Action') }}</th>
                        <th style="text-align:left;padding:10px;">{{ __('Route') }}</th>
                        <th style="text-align:left;padding:10px;">{{ __('IP') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr style="border-top:1px solid var(--border);">
                            <td style="padding:10px;white-space:nowrap;">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td style="padding:10px;">{{ $log->user?->name ?? __('Deleted user') }}<br><span class="muted">#{{ $log->user_id ?? '—' }}</span></td>
                            <td style="padding:10px;"><span class="badge info">{{ $log->method }}</span></td>
                            <td style="padding:10px;">{{ $log->route ?? '—' }}</td>
                            <td style="padding:10px;">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No activity recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $logs->links() }}</div>
    </div>
@endsection
