@extends('layouts.app')
@section('title', __('Course curriculum builder'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course curriculum builder') }}</h1><p class="muted">{{ $course->title }} · {{ __('Select lessons and question sets for this course.') }}</p></div><a class="btn secondary" href="{{ route('admin.courses.index') }}">{{ __('Back to courses') }}</a></div>
<div class="panel">
<form method="POST" action="{{ route('admin.course-builder.update', $course) }}">@csrf @method('PUT')
@php($current = $course->curriculumItems->keyBy('course_content_post_id'))
@if($availablePosts->isEmpty())<p class="muted">{{ __('Publish content posts in the Content Studio before adding them to a course.') }}</p>@else
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
<thead><tr><th>{{ __('Include') }}</th><th>{{ __('Content post') }}</th><th>{{ __('Section / module') }}</th><th>{{ __('Order') }}</th><th>{{ __('Duration (min)') }}</th><th>{{ __('Free preview') }}</th></tr></thead>
<tbody>
@foreach($availablePosts as $post)
@php($item = $current->get($post->id))
<tr style="border-top:1px solid var(--border);"><td style="padding:12px;"><input type="checkbox" name="post_ids[]" value="{{ $post->id }}" @checked($item) style="width:auto;"></td>
<td><strong>{{ $post->title }}</strong><div class="muted">{{ $post->categories->pluck('name')->join(', ') }}</div></td>
<td><input name="section_titles[{{ $post->id }}]" maxlength="120" value="{{ old('section_titles.'.$post->id, $item?->section_title) }}" placeholder="{{ __('e.g. Module 1') }}"></td>
<td><input type="number" name="positions[{{ $post->id }}]" min="0" max="100000" value="{{ old('positions.'.$post->id, $item?->position ?? (($loop->iteration) * 10)) }}" style="width:90px;"></td>
<td><input type="number" name="durations[{{ $post->id }}]" min="1" max="600" value="{{ old('durations.'.$post->id, $item?->duration_minutes) }}" style="width:90px;"></td>
<td><input type="checkbox" name="previews[{{ $post->id }}]" value="1" @checked(old('previews.'.$post->id, $item?->is_preview ?? false)) style="width:auto;"></td></tr>
@endforeach
</tbody></table></div>
@endif
@php($currentQuestionSets = $course->questionSets->keyBy('id'))
<div class="panel" style="margin-top:18px;">
    <h2>{{ __('Question sets') }}</h2>
    <p class="muted">{{ __('Attach published question sets to this course and group them under a module. Configure attempts, time limits and question selection.') }}</p>
    @if($availableQuestionSets->isEmpty())
        <p class="muted">{{ __('Create and publish a question set before attaching it to a course.') }}</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr><th>{{ __('Include') }}</th><th>{{ __('Question set') }}</th><th>{{ __('Module / section') }}</th><th>{{ __('Order') }}</th><th>{{ __('Max attempts') }}</th><th>{{ __('Time limit (minutes)') }}</th><th>{{ __('Questions per attempt') }}</th><th>{{ __('Available from') }}</th><th>{{ __('Shuffle') }}</th></tr></thead>
                <tbody>
                @foreach($availableQuestionSets as $set)
                    @php($attached = $currentQuestionSets->get($set->id))
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:10px;"><input type="checkbox" name="question_set_ids[]" value="{{ $set->id }}" @checked($attached) style="width:auto;"></td>
                        <td><strong>{{ $set->title }}</strong><div class="muted">{{ $set->questions_count }} {{ __('questions') }} · {{ __(ucfirst($set->status)) }}</div></td>
                        <td><input name="question_set_section_titles[{{ $set->id }}]" maxlength="120" value="{{ old('question_set_section_titles.'.$set->id, $attached?->pivot?->section_title) }}" placeholder="{{ __('e.g. Module 1 quiz') }}"></td>
                        <td><input type="number" name="question_set_positions[{{ $set->id }}]" min="0" max="100000" value="{{ old('question_set_positions.'.$set->id, $attached?->pivot?->position ?? ($loop->iteration * 10)) }}" style="width:85px;"></td>
                        <td><input type="number" name="question_set_max_attempts[{{ $set->id }}]" min="1" max="100" value="{{ old('question_set_max_attempts.'.$set->id, $attached?->pivot?->max_attempts) }}" style="width:85px;" placeholder="{{ __('Unlimited') }}"></td>
                        <td><input type="number" name="question_set_time_limits[{{ $set->id }}]" min="1" max="600" value="{{ old('question_set_time_limits.'.$set->id, $attached?->pivot?->time_limit_minutes) }}" style="width:85px;" placeholder="{{ __('None') }}"></td>
                        <td><input type="number" name="question_set_questions_per_attempt[{{ $set->id }}]" min="1" max="200" value="{{ old('question_set_questions_per_attempt.'.$set->id, $attached?->pivot?->questions_per_attempt) }}" style="width:85px;" placeholder="{{ __('All') }}"></td>
                        <td><input type="datetime-local" name="question_set_available_from[{{ $set->id }}]" value="{{ old('question_set_available_from.'.$set->id, $attached?->pivot?->available_from ? \Illuminate\Support\Carbon::parse($attached->pivot->available_from)->format('Y-m-d\TH:i') : '') }}"></td>
                        <td><input type="hidden" name="question_set_randomize[{{ $set->id }}]" value="0"><input type="checkbox" name="question_set_randomize[{{ $set->id }}]" value="1" @checked(old('question_set_randomize.'.$set->id, $attached?->pivot?->randomize_questions ?? true)) style="width:auto;"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<button class="btn primary" type="submit" style="margin-top:18px;">{{ __('Save course curriculum') }}</button>
</form></div>
@endsection
