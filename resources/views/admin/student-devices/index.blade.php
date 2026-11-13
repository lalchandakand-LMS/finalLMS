@extends('layouts.app')

@section('title', 'Student device approvals | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">Admin only</span>
        <h1 style="margin:12px 0 0;">Student device approvals</h1>
        <p class="muted">Review additional-device requests after mobile verification. Approving a new device automatically revokes the student's previous approved device.</p>
    </div>
</div>

<div class="panel">
    <h2>Pending approval ({{ $requests->total() }})</h2>
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:760px;border-collapse:collapse;">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border);"><th style="padding:10px;">Student</th><th>Device</th><th>IP address</th><th>Phone verified</th><th>Requested</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($requests as $device)
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 10px;">{{ $device->user->name }}<br><small class="muted">{{ $device->user->email }} · {{ $device->user->phone_number }}</small></td>
                    <td>{{ $device->device_name }}</td><td>{{ $device->ip_address ?? '—' }}</td><td>{{ $device->phone_verified_at?->format('d M Y, h:i A') }}</td><td>{{ $device->created_at->format('d M Y, h:i A') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.student-devices.approve', $device) }}" style="display:inline;">@csrf<button class="btn primary" type="submit">Approve</button></form>
                        <form method="POST" action="{{ route('admin.student-devices.reject', $device) }}" style="display:inline;">@csrf<button class="btn secondary" type="submit">Reject</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">No additional devices are waiting for approval.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px;">{{ $requests->links() }}</div>
</div>

<div class="panel" style="margin-top:20px;">
    <h2>Approved and past devices</h2>
    <div style="overflow-x:auto;">
        <table style="width:100%;min-width:760px;border-collapse:collapse;">
            <thead><tr style="text-align:left;border-bottom:1px solid var(--border);"><th style="padding:10px;">Student</th><th>Device</th><th>Status</th><th>Last used</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($devices as $device)
                <tr style="border-bottom:1px solid var(--border);">
                    <td style="padding:12px 10px;">{{ $device->user->name }}<br><small class="muted">{{ $device->user->email }}</small></td><td>{{ $device->device_name }}</td><td><span class="badge {{ $device->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($device->status) }}</span></td><td>{{ $device->last_used_at?->format('d M Y, h:i A') ?? '—' }}</td>
                    <td>@if($device->status === 'approved')<form method="POST" action="{{ route('admin.student-devices.revoke', $device) }}">@csrf<button class="btn secondary" type="submit">Revoke access</button></form>@else—@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="padding:18px;">No device records yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
