@extends('layouts.app')

@section('title', 'Quiz & Results Control | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">Admin control</span><h1 style="margin:12px 0 0;">Quiz & Competition Management</h1><p class="muted">Create quizzes, manage participation and publish skill-based results transparently.</p></div>
</div>

<div class="panel" role="note" style="margin-bottom:24px;border-left:4px solid var(--warning);">
    <strong>Compliance safeguard</strong>
    <p class="muted" style="margin-bottom:0;">Only free-entry, skill-based competitions are enabled. Random winner selection and paid-entry collection are disabled until qualified Bangladesh legal advice and written confirmation of the specific contest model and payment-provider terms are obtained.</p>
</div>

<div class="panel" style="margin-bottom:24px;">
    <h2 style="margin-top:0;">Create a new quiz contest</h2>
    <p class="muted">Participation is free. Each account can submit once. Add at least one multiple-choice question.</p>
    <form method="POST" action="{{ route('admin.contests.store') }}">
        @csrf
        <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <div><label>Contest title</label><input name="title" maxlength="180" required value="{{ old('title') }}"></div>
            <div><label>Prize / reward details</label><input name="prize_description" maxlength="500" required value="{{ old('prize_description') }}" placeholder="Example: English learning gift hamper"></div>
            <div><label>Number of winners</label><input type="number" name="winner_count" min="1" max="100" value="{{ old('winner_count', 1) }}" required></div>
            <div><label>Starts at</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required></div>
            <div><label>Ends at</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required></div>
            <div style="grid-column:1/-1;"><label>Description / rules</label><textarea name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></div>
        </div>
        <h3>Questions</h3>
        <div id="contest-questions">
            <section class="question-editor" style="border:1px solid var(--border);border-radius:12px;padding:15px;margin:12px 0;">
                <strong>Question 1</strong>
                <label>Question text</label><input name="questions[0][question]" required maxlength="2000" placeholder="Write your question">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:10px;">
                    @foreach(['A','B','C','D'] as $letter)
                        <div><label>Option {{ $letter }}</label><input name="questions[0][options][]" required maxlength="300"></div>
                    @endforeach
                </div>
                <label>Correct answer</label><select name="questions[0][correct_option]" required><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select>
            </section>
        </div>
        <button type="button" class="btn secondary" id="add-contest-question">+ Add another question</button>
        <button type="submit" class="btn primary">Publish contest</button>
    </form>
</div>

<div class="panel">
    <h2 style="margin-top:0;">All contests</h2>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
        <thead><tr style="text-align:left;border-bottom:1px solid var(--border);"><th style="padding:10px;">Contest</th><th>Questions</th><th>Participants</th><th>Prize</th><th>Status</th><th>Results</th></tr></thead>
        <tbody>
        @forelse($contests as $contest)
            <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:12px 10px;"><strong>{{ $contest->title }}</strong><br><small class="muted">{{ $contest->starts_at->format('d M Y H:i') }} – {{ $contest->ends_at->format('d M Y H:i') }}</small><br><a href="{{ route('contests.show', $contest) }}">View public page</a>
                    @if(!$contest->drawn_at && $contest->entries_count === 0 && now()->lessThan($contest->starts_at))
                        <br><a class="btn secondary" href="{{ route('admin.contests.edit', $contest) }}">Edit before opening</a>
                    @endif
                </td>
                <td>{{ $contest->questions_count }}</td><td>{{ $contest->entries_count }}</td><td>{{ $contest->prize_description }}<br><small>{{ $contest->winner_count }} winner(s)</small></td>
                <td>
                    <span class="badge {{ $contest->drawn_at ? 'success' : ($contest->status === 'published' ? 'info' : 'warning') }}">{{ $contest->drawn_at ? 'Results published' : ucfirst($contest->status) }}</span>
                    @if(!$contest->drawn_at && now()->lessThan($contest->ends_at))
                        <form method="POST" action="{{ route('admin.contests.status', $contest) }}" style="margin-top:6px;">
                            @csrf
                            <input type="hidden" name="status" value="{{ $contest->status === 'paused' ? 'published' : 'paused' }}">
                            <button class="btn secondary" type="submit">{{ $contest->status === 'paused' ? 'Resume' : 'Pause' }}</button>
                        </form>
                    @elseif(!$contest->drawn_at && $contest->status !== 'closed')
                        <form method="POST" action="{{ route('admin.contests.status', $contest) }}" style="margin-top:6px;">
                            @csrf<input type="hidden" name="status" value="closed">
                            <button class="btn secondary" type="submit">Close contest</button>
                        </form>
                    @endif
                </td>
                <td>
                    @if($contest->drawn_at)
                        <strong>Results published</strong><br>@foreach($contest->winners as $winner)<small>#{{ $winner->rank }} {{ $winner->user->name }}</small><br>@endforeach
                    @elseif(now()->greaterThan($contest->ends_at) && $contest->entries_count > 0)
                        <form method="POST" action="{{ route('admin.contests.results.publish', $contest) }}" onsubmit="return confirm('Publish results now? This action cannot be repeated.');">@csrf<button class="btn primary" type="submit">Publish results</button></form>
                    @else
                        <small class="muted">Available after end time</small>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="padding:16px;">No contests created yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div style="margin-top:16px;">{{ $contests->links() }}</div>
</div>
<script>
(() => {
    const container = document.getElementById('contest-questions');
    const add = document.getElementById('add-contest-question');
    if (!container || !add) return;
    let index = 1;
    add.addEventListener('click', () => {
        if (index >= 50) return;
        const section = document.createElement('section');
        section.className = 'question-editor';
        section.style.cssText = 'border:1px solid var(--border);border-radius:12px;padding:15px;margin:12px 0;';
        const i = index++;
        section.innerHTML = '<strong>Question ' + (i + 1) + '</strong><label>Question text</label><input name="questions['+i+'][question]" required maxlength="2000" placeholder="Write your question"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:10px;">' +
            ['A','B','C','D'].map(letter => '<div><label>Option '+letter+'</label><input name="questions['+i+'][options][]" required maxlength="300"></div>').join('') +
            '</div><label>Correct answer</label><select name="questions['+i+'][correct_option]" required><option value="0">A</option><option value="1">B</option><option value="2">C</option><option value="3">D</option></select>';
        container.appendChild(section);
    });
})();
</script>
@endsection
