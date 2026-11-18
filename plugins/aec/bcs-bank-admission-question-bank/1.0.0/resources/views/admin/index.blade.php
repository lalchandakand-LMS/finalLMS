@extends('layouts.app')
@section('title', __('BCS, Bank Job & Admission Question Bank'))
@section('content')
<div class="page-header">
 <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('BCS / Bank Job / Admission Question Bank') }}</h1><p class="muted">{{ __('Create a reusable question library and assemble exam papers from selected or randomly picked questions.') }}</p></div>
 <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn secondary" href="{{ route('admin.prep-question-bank.sets.index') }}">{{ __('Exam papers') }}</a><a class="btn primary" href="{{ route('admin.prep-question-bank.questions.create') }}">{{ __('Add question') }}</a></div>
</div>
<div class="grid grid-3" style="margin-bottom:18px">
 @foreach(['bcs'=>'BCS','bank_job'=>'Bank Job','admission'=>'Admission'] as $key=>$label)
 <div class="panel"><strong>{{ __($label) }}</strong><div style="font-size:28px;font-weight:700">{{ $counts[$key] ?? 0 }}</div><span class="muted">{{ __('questions') }}</span></div>
 @endforeach
</div>
<div class="panel" style="margin-bottom:18px">
 <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end">
  <label>{{ __('Exam track') }}<select name="track"><option value="">{{ __('All') }}</option>@foreach(['bcs'=>'BCS','bank_job'=>'Bank Job','admission'=>'Admission'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['track']??'')===$key)>{{ __($label) }}</option>@endforeach</select></label>
  <label>{{ __('Subject') }}<select name="subject"><option value="">{{ __('All subjects') }}</option>@foreach($subjects as $subject)<option @selected(($filters['subject']??'')===$subject)>{{ $subject }}</option>@endforeach</select></label>
  <label>{{ __('Question type') }}<select name="type"><option value="">{{ __('All') }}</option><option value="mcq" @selected(($filters['type']??'')==='mcq')>MCQ</option><option value="written" @selected(($filters['type']??'')==='written')>{{ __('Written') }}</option></select></label>
  <label>{{ __('Year') }}<input type="number" name="year" value="{{ $filters['year']??'' }}" min="1950" max="{{ now()->year+1 }}"></label>
  <label>{{ __('Search') }}<input name="search" value="{{ $filters['search']??'' }}" placeholder="{{ __('Question or topic') }}"></label>
  <button class="btn secondary">{{ __('Filter') }}</button>
 </form>
</div>
<div class="panel"><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>{{ __('Track') }}</th><th>{{ __('Subject / Topic') }}</th><th>{{ __('Question') }}</th><th>{{ __('Type') }}</th><th>{{ __('Year') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($questions as $q)<tr style="border-top:1px solid var(--border)"><td>{{ strtoupper(str_replace('_',' ',$q->track)) }}</td><td>{{ $q->subject }}<div class="muted">{{ $q->topic }}</div></td><td>{{ \Illuminate\Support\Str::limit($q->question,120) }}</td><td>{{ strtoupper($q->type) }}</td><td>{{ $q->source_year ?: '—' }}</td><td style="white-space:nowrap"><a class="btn secondary" href="{{ route('admin.prep-question-bank.questions.edit',$q->id) }}">{{ __('Edit') }}</a><form method="POST" action="{{ route('admin.prep-question-bank.questions.destroy',$q->id) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this question?') }}')">@csrf @method('DELETE')<button class="btn secondary">{{ __('Delete') }}</button></form></td></tr>
@empty<tr><td colspan="6" class="muted" style="padding:18px">{{ __('No questions found. Add the first question to start your bank.') }}</td></tr>@endforelse
</tbody></table></div>{{ $questions->links() }}</div>
@endsection
