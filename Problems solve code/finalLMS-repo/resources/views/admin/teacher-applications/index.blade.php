@extends('layouts.app')
@section('title', 'Teacher applications')
@section('content')
<div class="page-header"><div><span class="badge info">Admin only</span><h1>Teacher applications</h1><p class="muted">Review verified mobile numbers, identity documents and institution proof before granting teacher access.</p></div></div>
<div class="panel" style="overflow-x:auto;">
<table style="width:100%;min-width:900px;border-collapse:collapse;">
<thead><tr><th>Name / contact</th><th>Institution</th><th>Verification</th><th>Documents</th><th>Review</th></tr></thead>
<tbody>
@forelse($applications as $application)
<tr style="border-top:1px solid var(--border);vertical-align:top;">
<td style="padding:14px;">{{ $application->name }}<br>{{ $application->email }}<br>{{ $application->phone_number }}</td>
<td>{{ $application->institution_name }}<br>{{ $application->designation }}</td>
<td><span class="badge {{ $application->phone_verified_at ? 'success' : 'warning' }}">{{ $application->phone_verified_at ? 'Mobile verified' : 'Not verified' }}</span><br>{{ str_replace('_',' ',ucfirst($application->status)) }}</td>
<td><a class="btn secondary" href="{{ route('admin.teacher-applications.document',[$application,'nid']) }}">View NID</a><br><br><a class="btn secondary" href="{{ route('admin.teacher-applications.document',[$application,'institution']) }}">Institution proof</a></td>
<td>
@if($application->status === 'pending_admin_review')
<form method="POST" action="{{ route('admin.teacher-applications.approve',$application) }}">@csrf<button class="btn primary" type="submit">Approve & create teacher</button></form>
<form method="POST" action="{{ route('admin.teacher-applications.reject',$application) }}" style="margin-top:8px;">@csrf<textarea name="review_note" placeholder="Reason for rejection" required style="min-height:70px;"></textarea><button class="btn secondary" type="submit">Reject</button></form>
@else {{ $application->review_note ?: '—' }} @endif
</td></tr>
@empty
<tr><td colspan="5" style="padding:18px;">No teacher applications found.</td></tr>
@endforelse
</tbody></table>
<div style="margin-top:16px;">{{ $applications->links() }}</div>
</div>
@endsection