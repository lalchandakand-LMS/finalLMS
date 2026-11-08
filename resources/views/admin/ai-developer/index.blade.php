@extends('layouts.app')

@section('title', 'AI Developer | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">Admin only</span>
        <h1 style="margin:12px 0 0;">🤖 AI Developer</h1>
        <p class="muted">Describe an LMS problem in Bangla or English and receive a structured technical diagnosis.</p>
    </div>
</div>

@if(!$configured)
    <div class="panel" style="border-left:4px solid var(--warning,#d97706);margin-bottom:18px;">
        <strong>AI connection is not configured.</strong>
        <p class="muted" style="margin-bottom:0;">Set AI_DEVELOPER_API_KEY, AI_DEVELOPER_BASE_URL and AI_DEVELOPER_MODEL in the private server environment. Never paste credentials into this chat or commit them to GitHub.</p>
    </div>
@endif

<div class="grid grid-2" style="align-items:start;">
    <section class="panel">
        <h2>New task</h2>
        <form method="POST" action="{{ route('admin.ai-developer.analyze') }}">
            @csrf
            <div style="margin-bottom:14px;">
                <label for="title">Problem title</label>
                <input id="title" name="title" maxlength="180" required value="{{ old('title') }}" placeholder="যেমন: Student login error">
                @error('title')<small class="muted">{{ $message }}</small>@enderror
            </div>
            <div style="margin-bottom:14px;">
                <label for="instruction">Describe the problem / নির্দেশনা</label>
                <textarea id="instruction" name="instruction" rows="9" maxlength="12000" required placeholder="সমস্যাটি কী, কখন হচ্ছে, কী error দেখাচ্ছে—সংবেদনশীল তথ্য বাদ দিয়ে লিখুন।">{{ old('instruction') }}</textarea>
                @error('instruction')<small class="muted">{{ $message }}</small>@enderror
            </div>
            <button class="btn primary" type="submit" @disabled(!$configured)>Analyze problem</button>
        </form>
    </section>

    <section class="panel">
        <h2>What this first release can do</h2>
        <ul style="line-height:1.9;padding-left:20px;">
            <li>Understand Bangla and English problem reports.</li>
            <li>Suggest likely causes, safe fixes and tests.</li>
            <li>Keep a private history of your AI requests.</li>
            <li>Show errors without exposing provider credentials.</li>
        </ul>
        <div class="badge warning">Read-only diagnostic mode</div>
        <p class="muted" style="margin-top:12px;">This release does not yet edit repository files or deploy updates. GitHub branch-based patch creation, diff review and automated test feedback are the next gated development stage. Never apply an AI suggestion directly to the live website.</p>
    </section>
</div>

<div class="panel" style="margin-top:22px;">
    <h2>Recent AI tasks</h2>
    @forelse($tasks as $task)
        <article style="padding:16px 0;border-top:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <strong>{{ $task->title }}</strong>
                <span class="badge {{ $task->status === 'completed' ? 'success' : ($task->status === 'failed' ? 'warning' : 'info') }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
            </div>
            <small class="muted">{{ $task->created_at->format('d M Y, h:i A') }}</small>
            @if($task->response)
                <div style="white-space:pre-wrap;margin-top:12px;">{{ $task->response }}</div>
            @elseif($task->error_message)
                <p class="muted" style="margin-bottom:0;">{{ $task->error_message }}</p>
            @endif
        </article>
    @empty
        <p class="muted">No AI tasks yet.</p>
    @endforelse
</div>
@endsection
