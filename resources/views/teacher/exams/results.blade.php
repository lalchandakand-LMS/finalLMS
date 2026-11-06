@extends('layouts.app')

@section('title', __('Exam results') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</span>
            <h1 style="margin:12px 0 0;">{{ $exam->title }} — {{ __('Results') }}</h1>
            <p class="muted">{{ __('Pass marks:') }} {{ $exam->pass_marks }} · {{ $attempts->total() }} {{ __('submitted attempts') }}</p>
        </div>
        <a href="{{ route('teacher.batches.exams.index', $exam->batch_id) }}" class="btn secondary">{{ __('Back to exams') }}</a>
    </div>

    <div class="panel">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;text-align:left;">
                <thead>
                    <tr>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Student') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Email') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Score') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Result') }}</th>
                        <th style="padding:12px;border-bottom:1px solid var(--border);">{{ __('Submitted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $attempt)
                        <tr>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->name }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->student->email }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->score }}</td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">
                                @if($attempt->score >= $exam->pass_marks)
                                    <span class="badge success">{{ __('Passed') }}</span>
                                @else
                                    <span class="badge warning">{{ __('Not passed') }}</span>
                                @endif
                            </td>
                            <td style="padding:12px;border-bottom:1px solid var(--border);">{{ $attempt->submitted_at?->format('M j, Y H:i') }}</td>
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
