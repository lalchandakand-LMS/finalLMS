@extends('layouts.app')
@section('title', __('Create exam paper'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Build exam question paper') }}</h1><p class="muted">{{ __('Choose a track, filter the bank, select questions, and optionally randomize the final paper.') }}</p></div><a class="btn secondary" href="{{ route('admin.prep-question-bank.sets.index') }}">{{ __('Back') }}</a></div>
<div class="panel" style="margin-bottom:16px"><form method="GET" class="grid grid-3">
<label>{{ __('Track') }}<select name="track">@foreach(['bcs'=>'BCS','bank_job'=>'Bank Job','admission'=>'Admission'] as $v=>$label)<option value="{{ $v }}" @selected(($filters['track']??'bcs')===$v)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Subject') }}<input name="subject" value="{{ $filters['subject']??'' }}"></label><label>{{ __('Type') }}<select name="type"><option value="">{{ __('All') }}</option><option value="mcq" @selected(($filters['type']??'')==='mcq')>MCQ</option><option value="written" @selected(($filters['type']??'')==='written')>{{ __('Written') }}</option></select></label>
<label>{{ __('Year') }}<input type="number" name="year" value="{{ $filters['year']??'' }}"></label><button class="btn secondary">{{ __('Filter questions') }}</button></form></div>
<div class="panel"><form method="POST" action="{{ route('admin.prep-question-bank.sets.store') }}">@csrf
<div class="grid grid-2"><label>{{ __('Exam track') }}<select name="track" required>@foreach(['bcs'=>'BCS','bank_job'=>'Bank Job','admission'=>'Admission'] as $v=>$label)<option value="{{ $v }}" @selected(($filters['track']??'bcs')===$v)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Paper title') }}<input name="title" maxlength="180" required></label><label>{{ __('Duration (minutes)') }}<input type="number" name="duration_minutes" min="1" max="600" value="60" required></label><label>{{ __('Status') }}<select name="status"><option value="draft">{{ __('Draft') }}</option><option value="ready">{{ __('Ready for review') }}</option></select></label></div>
<label>{{ __('Instructions') }}<textarea name="instructions" maxlength="5000"></textarea></label>
<label>{{ __('Randomly select how many from checked questions (leave blank to include all)') }}<input type="number" name="random_count" min="1" max="200"></label>
<div style="display:flex;gap:8px;align-items:center;margin:12px 0"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="select-all" style="width:auto">{{ __('Select all visible questions') }}</label><span class="muted">{{ $questions->count() }} {{ __('questions loaded (up to 500)') }}</span></div>
<div style="max-height:520px;overflow:auto;border:1px solid var(--border);border-radius:12px;padding:10px">
@forelse($questions as $q)<label style="display:flex;gap:10px;align-items:flex-start;padding:10px;border-bottom:1px solid var(--border)"><input type="checkbox" name="question_ids[]" value="{{ $q->id }}" class="prep-q" style="width:auto;margin-top:4px"><span><strong>{{ $q->subject }}</strong> · {{ strtoupper($q->type) }} · {{ $q->source_year ?: '—' }}<small class="muted" style="display:block">{{ \Illuminate\Support\Str::limit($q->question,180) }}</small></span></label>@empty<p class="muted">{{ __('No matching questions. Add questions to the bank first.') }}</p>@endforelse
</div><button class="btn primary" style="margin-top:16px">{{ __('Create question paper') }}</button>
</form></div>
@push('scripts')<script>document.getElementById('select-all')?.addEventListener('change',e=>document.querySelectorAll('.prep-q').forEach(x=>x.checked=e.target.checked));</script>@endpush
@endsection
