@extends('layouts.app')
@section('title', __('Teacher AI Drafts'))
@section('content')
<div class="page-header"><div><h1>{{ __('Teacher AI Draft Library') }}</h1><p class="muted">{{ __('Save AI-generated teaching materials, then submit them for independent administrator review.') }}</p></div></div>
@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($role === 'teacher')
<section class="panel"><h2>{{ __('Save a teaching draft') }}</h2>
<form method="POST" action="{{ route('teacher.ai-assistant.drafts.store') }}">@csrf
<label>{{ __('Draft type') }}</label><select name="draft_type" required><option value="question_set">{{ __('Question set') }}</option><option value="lesson_plan">{{ __('Lesson plan') }}</option><option value="feedback_template">{{ __('Feedback template') }}</option><option value="rubric">{{ __('Marking rubric') }}</option></select>
<label>{{ __('Title') }}</label><input name="title" maxlength="180" required>
<label>{{ __('Draft content') }}</label><textarea name="content" maxlength="30000" rows="10" required></textarea>
<label><input type="checkbox" name="submit_for_review" value="1"> {{ __('Submit for administrator approval') }}</label>
<button class="btn primary" type="submit">{{ __('Save draft') }}</button></form></section>
@endif
<section class="panel"><h2>{{ $role === 'admin' ? __('Drafts awaiting review') : __('My saved drafts') }}</h2>
@forelse($drafts as $draft)<article style="padding:14px 0;border-bottom:1px solid var(--border-color,#ddd)"><strong>{{ $draft->title }}</strong><span class="badge">{{ __(ucwords(str_replace('_',' ',$draft->status))) }}</span><p class="muted">{{ __(ucwords(str_replace('_',' ',$draft->draft_type))) }}</p><pre style="white-space:pre-wrap">{{ $draft->content }}</pre>
@if($role === 'admin' && $draft->status === 'pending_review')
<form method="POST" action="{{ route('admin.ai-assistant.drafts.review',$draft->id) }}">@csrf<input type="hidden" name="version" value="{{ $draft->version }}"><textarea name="review_note" maxlength="500" placeholder="{{ __('Review note') }}"></textarea><button class="btn primary" name="decision" value="approve">{{ __('Approve') }}</button><button class="btn secondary" name="decision" value="request_changes">{{ __('Request changes') }}</button></form>
@endif</article>@empty<p class="muted">{{ __('No drafts found.') }}</p>@endforelse
</section>
@endsection
