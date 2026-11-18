@extends('layouts.app')
@section('title', $question ? __('Edit preparation question') : __('Add preparation question'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ $question ? __('Edit question') : __('Add question to bank') }}</h1></div><a class="btn secondary" href="{{ route('admin.prep-question-bank.index') }}">{{ __('Back to bank') }}</a></div>
<div class="panel" style="max-width:950px"><form method="POST" action="{{ $question ? route('admin.prep-question-bank.questions.update',$question->id) : route('admin.prep-question-bank.questions.store') }}">@csrf @if($question) @method('PUT') @endif
<div class="grid grid-2">
<label>{{ __('Exam track') }}<select name="track" required>@foreach(['bcs'=>'BCS','bank_job'=>'Bank Job','admission'=>'Admission'] as $v=>$label)<option value="{{ $v }}" @selected(old('track',$question->track??'bcs')===$v)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Question type') }}<select name="type" id="prep-type"><option value="mcq" @selected(old('type',$question->type??'mcq')==='mcq')>MCQ</option><option value="written" @selected(old('type',$question->type??'mcq')==='written')>{{ __('Written') }}</option></select></label>
<label>{{ __('Subject') }}<input name="subject" maxlength="100" value="{{ old('subject',$question->subject??'') }}" required placeholder="{{ __('Bangla, English, Math, General Knowledge...') }}"></label>
<label>{{ __('Topic') }}<input name="topic" maxlength="120" value="{{ old('topic',$question->topic??'') }}"></label>
<label>{{ __('Source year') }}<input type="number" name="source_year" min="1950" max="{{ now()->year+1 }}" value="{{ old('source_year',$question->source_year??'') }}"></label>
<label>{{ __('Source exam / institution') }}<input name="source_name" maxlength="120" value="{{ old('source_name',$question->source_name??'') }}"></label>
<label>{{ __('Difficulty') }}<select name="difficulty">@foreach(['easy','medium','hard'] as $v)<option value="{{ $v }}" @selected(old('difficulty',$question->difficulty??'medium')===$v)>{{ __(ucfirst($v)) }}</option>@endforeach</select></label>
<label>{{ __('Marks') }}<input type="number" name="marks" min="1" max="100" value="{{ old('marks',$question->marks??1) }}" required></label>
</div>
<label>{{ __('Question text') }}<textarea name="question" rows="5" maxlength="20000" required>{{ old('question',$question->question??'') }}</textarea></label>
<div id="prep-mcq" class="panel"><h3>MCQ options</h3><div class="grid grid-2">@foreach(range(0,3) as $i)<label>{{ __('Option') }} {{ ['A','B','C','D'][$i] }}<input name="options[{{ $i }}]" maxlength="1000" value="{{ old('options.'.$i,$question->options[$i]??'') }}"></label>@endforeach</div><label>{{ __('Correct option') }}<select name="correct_option">@foreach(['A','B','C','D'] as $i=>$letter)<option value="{{ $i }}" @selected((string)old('correct_option',$question->correct_option??0)===(string)$i)>{{ $letter }}</option>@endforeach</select></label></div>
<label>{{ __('Expected / model answer (optional for written)') }}<textarea name="correct_answer" rows="4" maxlength="10000">{{ old('correct_answer',$question->correct_answer??'') }}</textarea></label>
<label>{{ __('Explanation / solution') }}<textarea name="explanation" rows="4" maxlength="10000">{{ old('explanation',$question->explanation??'') }}</textarea></label>
<button class="btn primary">{{ __('Save question') }}</button>
</form></div>
@push('scripts')<script>(()=>{const type=document.getElementById('prep-type'),mcq=document.getElementById('prep-mcq');const sync=()=>{mcq.hidden=type.value!=='mcq';mcq.querySelectorAll('input,select').forEach(x=>x.disabled=type.value!=='mcq')};type.addEventListener('change',sync);sync()})();</script>@endpush
@endsection
