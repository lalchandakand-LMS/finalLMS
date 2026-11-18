@extends('layouts.app')

@section('title', __('Learn & Earn Exams') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Learn & Earn Exams') }}</h1><p class="muted">{{ __('Build exams from saved MCQs. Each exam keeps its own immutable question and marking snapshots.') }}</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn secondary" href="{{ route('admin.learn-earn.questions.index') }}">{{ __('Question bank') }}</a>
        <a class="btn primary" href="{{ route('admin.learn-earn.exams.create') }}">{{ __('Create exam') }}</a>
    </div>
</div>

<div class="panel">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>{{ __('Exam') }}</th><th>{{ __('Questions') }}</th><th>{{ __('Access') }}</th><th>{{ __('Availability') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($exams as $exam)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:12px;"><strong>{{ $exam->title }}</strong><div class="muted">{{ $exam->slug }}</div></td>
                    <td>{{ $exam->questions_count }}</td>
                    <td>{{ $exam->access_type === 'paid' ? '৳'.number_format((float)$exam->price, 2) : __('Free') }}</td>
                    <td>{{ $exam->starts_at->format('d M Y H:i') }} – {{ $exam->ends_at->format('d M Y H:i') }}</td>
                    <td>{{ __(ucfirst($exam->status)) }}</td>
                    <td>
                        @if($exam->status !== 'archived')
                        <form method="POST" action="{{ route('admin.learn-earn.exams.archive', $exam) }}" onsubmit="return confirm('{{ __('Archive this exam?') }}')">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Archive') }}</button>
                        </form>
                        @else
                            <span class="muted">{{ __('Archived') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No exams created yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $exams->links() }}
</div>
@endsection
