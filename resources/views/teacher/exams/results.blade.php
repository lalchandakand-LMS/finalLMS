@extends('layouts.app')

@section('title', __('Exam results') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $exam->title }} — {{ __('Results') }}</h1>
            <p class="muted">{{ __('Pass marks:') }} {{ $exam->pass_marks }} · {{ $attempts->total() }} {{ __('submitted attempts') }}</p>
        </div>
        <a href="{{ $isAdmin ? route('admin.batches.exams.index', $exam->batch_id) : route('teacher.batches.exams.index', $exam->batch_id) }}" class="btn secondary">{{ __('Back to exams') }}</a>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn secondary" href="{{ $isAdmin ? route('admin.exams.result-sheet', $exam) : route('teacher.exams.result-sheet', $exam) }}">{{ __('Merit list / Print sheet') }}</a>
            @if($exam->result_status !== 'published')
                <form method="POST" action="{{ $isAdmin ? route('admin.exams.results.publish', $exam) : route('teacher.exams.results.publish', $exam) }}" onsubmit="return confirm('{{ __('Publish results to students now?') }}')">
                    @csrf<button class="btn primary" type="submit">{{ __('Publish results') }}</button>
                </form>
            @else
                <span class="badge success">{{ __('Results published') }}</span>
            @endif
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" style="margin-bottom:18px;">
        <div class="panel"><div class="muted">{{ __('Submitted attempts') }}</div><strong style="font-size:1.6rem;">{{ $totalSubmitted }}</strong></div>
        <div class="panel"><div class="muted">{{ __('Pass rate') }}</div><strong style="font-size:1.6rem;">{{ number_format($passRate, 1) }}%</strong></div>
        <div class="panel"><div class="muted">{{ __('Average score') }}</div><strong style="font-size:1.6rem;">{{ $summary->average_score !== null ? number_format((float) $summary->average_score, 2) : '—' }}</strong></div>
        <div class="panel"><div class="muted">{{ __('Highest / Lowest') }}</div><strong style="font-size:1.6rem;">{{ $summary->highest_score ?? '—' }} / {{ $summary->lowest_score ?? '—' }}</strong></div>
    </div>
    <div class="panel" style="margin-bottom:18px;">
        <h2>{{ __('Question-wise performance') }}</h2>
        <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;text-align:left;">
            <thead><tr><th style="padding:12px;">{{ __('Question') }}</th><th style="padding:12px;">{{ __('Answered') }}</th><th style="padding:12px;">{{ __('Correct') }}</th><th style="padding:12px;">{{ __('Accuracy') }}</th></tr></thead>
            <tbody>@forelse($questionAnalytics as $question)<tr><td style="padding:12px;border-top:1px solid var(--border);">{{ $question->question }}</td><td style="padding:12px;border-top:1px solid var(--border);">{{ $question->answered_count }}</td><td style="padding:12px;border-top:1px solid var(--border);">{{ $question->correct_count }}</td><td style="padding:12px;border-top:1px solid var(--border);">{{ number_format($question->accuracy, 1) }}%</td></tr>@empty<tr><td colspan="4" class="muted" style="padding:18px;">{{ __('No question analytics yet.') }}</td></tr>@endforelse</tbody>
        </table></div>
    </div>

    <div class="panel">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Student') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Email') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Score') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Result / Review') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Submitted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $attempt)
                        <tr>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->name }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->email }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->score ?? '—' }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">
                                @if($attempt->status === 'awaiting_manual')
                                    <span class="badge warning">{{ __('Awaiting written grading') }}</span>
                                    @foreach($attempt->answers->where('question.type', 'written')->whereNull('graded_at') as $answer)
                                        <div class="course-item" style="margin-top:10px;padding:12px;">
                                            <strong>{{ $answer->question->question }}</strong>
                                            <p style="white-space:pre-wrap;">{{ $answer->answer_text }}</p>
                                            @if($answer->question->grading_rubric)
                                                <p class="muted">{{ __('Rubric:') }} {{ implode(' · ', $answer->question->grading_rubric) }}</p>
                                            @endif
                                            <form method="POST" action="{{ $isAdmin ? route('admin.exam-answers.grade', $answer) : route('teacher.exam-answers.grade', $answer) }}">
                                                @csrf
                                                <label>{{ __('Marks awarded (max :max)', ['max' => $answer->question->marks]) }}<input type="number" name="marks_awarded" min="0" max="{{ $answer->question->marks }}" required></label>
                                                <label>{{ __('Feedback') }}<textarea name="teacher_feedback" maxlength="3000"></textarea></label>
                                                <button class="btn primary" type="submit">{{ __('Save grading') }}</button>
                                            </form>
                                        </div>
                                    @endforeach
                                @elseif($attempt->status === 'submitted')
                                    @if($attempt->score >= $exam->pass_marks)
                                        <span class="badge success">{{ __('Passed') }}</span>
                                    @else
                                        <span class="badge warning">{{ __('Not passed') }}</span>
                                    @endif
                                @endif
                            </td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ optional($attempt->submitted_at)->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No students have submitted this exam yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $attempts->links() }}</div>
    </div>
@endsection
