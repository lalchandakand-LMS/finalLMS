@extends('layouts.app')
@section('title', __('Question sets'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Question sets') }}</h1><p class="muted">{{ __('Build reusable quizzes from Question Bank MCQs or custom MCQ, True/False and short-answer questions.') }}</p></div>
    <a class="btn primary" href="{{ route('admin.question-sets.create') }}">{{ __('Create question set') }}</a>
</div>
<div class="panel">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>{{ __('Question set') }}</th><th>{{ __('Questions') }}</th><th>{{ __('Courses') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created by') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($questionSets as $set)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:12px;"><strong>{{ $set->title }}</strong><div class="muted">{{ \Illuminate\Support\Str::limit($set->description, 110) }}</div></td>
                    <td>{{ $set->questions_count }}</td>
                    <td>{{ $set->courses_count }}</td>
                    <td><span class="badge {{ $set->status === 'published' ? 'success' : 'warning' }}">{{ __(ucfirst($set->status)) }}</span></td>
                    <td>{{ $set->creator?->name ?: '—' }}</td>
                    <td style="white-space:nowrap;">
                        <a class="btn secondary" href="{{ route('admin.question-sets.edit', $set) }}">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.question-sets.destroy', $set) }}" style="display:inline;" onsubmit="return confirm('{{ __('Delete or archive this question set?') }}')">
                            @csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete / archive') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No question sets have been created yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $questionSets->links() }}
</div>
@endsection
