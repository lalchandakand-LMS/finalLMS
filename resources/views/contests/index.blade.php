@extends('layouts.app')

@section('title', 'Quiz & Competition | ' . config('app.name'))

@section('content')
<div class="page-header"><div><span class="badge info">Open to everyone with an account</span><h1 style="margin:12px 0 0;">Quiz & Competition</h1><p class="muted">Take part in free skill-based quizzes and compete for prizes.</p></div></div>
<div class="course-list">
@forelse($contests as $contest)
    <article class="course-item">
        <div class="meta-row"><div>
            <span class="badge {{ now()->between($contest->starts_at, $contest->ends_at) ? 'success' : 'info' }}">{{ now()->lt($contest->starts_at) ? 'Upcoming' : (now()->gt($contest->ends_at) ? 'Closed' : 'Open now') }}</span>
            <h2 style="margin:10px 0;">{{ $contest->title }}</h2>
            <p class="muted">{{ \Illuminate\Support\Str::limit($contest->description, 180) }}</p>
            <p><strong>Prize:</strong> {{ $contest->prize_description }}</p>
            <small class="muted">{{ $contest->questions_count }} questions · {{ $contest->entries_count }} participants · Ends {{ $contest->ends_at->format('d M Y, h:i A') }}</small>
        </div><div><a class="btn primary" href="{{ route('contests.show', $contest) }}">View & participate</a></div></div>
    </article>
@empty
    <div class="panel">There are no published quiz contests at the moment. Please check again later.</div>
@endforelse
</div>
<div style="margin-top:18px;">{{ $contests->links() }}</div>
@endsection
