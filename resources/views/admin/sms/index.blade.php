@extends('layouts.app')

@section('title', 'SMS campaigns | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">Admin</span>
            <h1 style="margin:12px 0 0;">Bulk SMS campaigns</h1>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="btn secondary">Provider settings</a>
    </div>

    <div class="panel" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">Create campaign</h2>
        <p class="muted">Eligible audience: {{ $eligibleCount }} verified phone number(s) with SMS marketing consent. Recipients are checked again before each message is sent.</p>
        <form method="POST" action="{{ route('admin.sms-campaigns.store') }}">
            @csrf
            <label for="campaign-title">Campaign name</label>
            <input id="campaign-title" type="text" name="title" maxlength="120" value="{{ old('title') }}" required>

            <label for="campaign-audience" style="margin-top:14px;">Audience</label>
            <select id="campaign-audience" name="audience" required>
                <option value="all" @selected(old('audience', 'all') === 'all')>All opted-in users</option>
                <option value="student" @selected(old('audience') === 'student')>Students</option>
                <option value="teacher" @selected(old('audience') === 'teacher')>Teachers</option>
            </select>

            <label for="campaign-message" style="margin-top:14px;">Message</label>
            <textarea id="campaign-message" name="message" maxlength="1500" required>{{ old('message') }}</textarea>

            <button type="submit" class="btn primary" style="margin-top:14px;" @disabled($eligibleCount === 0)>Queue campaign</button>
        </form>
    </div>

    <h2>Recent campaigns</h2>
    <div class="course-list">
        @forelse($campaigns as $campaign)
            <article class="course-item">
                <div class="meta-row">
                    <div>
                        <span class="badge {{ $campaign->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($campaign->status) }}</span>
                        <h3 style="margin:10px 0 6px;">{{ $campaign->title }}</h3>
                        <p class="muted" style="margin:0 0 8px;">{{ $campaign->message }}</p>
                        <div class="muted">Audience: {{ ucfirst($campaign->audience) }} · Created by {{ $campaign->creator?->name ?? 'Former user' }}</div>
                        <div class="muted">{{ $campaign->sent_count }} sent · {{ $campaign->failed_count }} failed · {{ $campaign->skipped_count }} skipped · {{ $campaign->recipient_count }} total</div>
                    </div>
                    <time class="muted" datetime="{{ $campaign->created_at->toIso8601String() }}">{{ $campaign->created_at->format('M j, Y H:i') }}</time>
                </div>
            </article>
        @empty
            <div class="panel"><p class="muted" style="margin:0;">No campaigns have been created.</p></div>
        @endforelse
    </div>
    <div style="margin-top:20px;">{{ $campaigns->links() }}</div>
@endsection