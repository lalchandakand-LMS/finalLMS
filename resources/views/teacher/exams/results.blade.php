@extends('layouts.app')

@section('title', 'Exam results | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $exam->title }} — Results</h1>
            <p class="muted">Pass marks: {{ $exam->pass_marks }} · {{ $attempts->total() }} submitted attempts</p>
        </div>
        <a href="{{ route('teacher.batches.exams.index', $exam->batch_id) }}" class="btn secondary">Back to exams</a>
    </div>

    <div class="panel">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead><tr>
                    <th style="padding:12px;border-bottom:1px solid var(--border);">Student</th>
                    <th style="padding:12px;border-bottom:1px solid var(--border);">Email</th>
                    <th style="padding:12px;border-bottom:1px solid var(--border);">Score</th>
                    <th style="padding:12px;border-bottom:1px solid var(--border);">Result</th>
                    <th style="padding:12px;border-bottom:1px solid var(--border);">Submitted</th>
                </tr></thead>
                <tbody>
                @forelse($attempts as $attempt)
                    <tr>
                        <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->name }}</td>
                        <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->email }}</td>
                        <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->score }}</td>
                        <td style="padding:12px;border-bottom:1px solid var(--border);">
                            @if($attempt->score >= $exam->pass_marks)<span class="badge success">Passed</span>@else<span class="badge warning">Not passed</span>@endif
                        </td>
                        <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->submitted_at?->format('M j, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="padding:18px;">No students have submitted this exam yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $attempts->links() }}</div>
    </div>
@endsection
