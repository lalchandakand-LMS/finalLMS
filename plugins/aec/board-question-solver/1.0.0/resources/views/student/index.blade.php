@extends('layouts.app')
@section('title', __('Board Question Solver'))
@section('content')
<div class="page-header"><div><h1>{{ __('Board Question Solver') }}</h1><p class="muted">{{ __('Prepare for SSC, HSC and board examinations with step-by-step explanations in Bangla or English.') }}</p></div><a class="btn secondary" href="{{ route('student.board-solver.history') }}">{{ __('My solved questions') }}</a></div>
<div class="panel" style="max-width:950px"><form id="board-solver-form" enctype="multipart/form-data" style="display:grid;gap:12px">@csrf
<div class="grid grid-2">
<label>{{ __('Education Board') }}<select name="board"><option value="">{{ __('Not specified') }}</option>@foreach(['Dhaka','Chattogram','Rajshahi','Cumilla','Jashore','Barishal','Sylhet','Dinajpur','Mymensingh','Madrasah','Technical'] as $board)<option>{{ $board }}</option>@endforeach</select></label>
<label>{{ __('Exam year') }}<input type="number" name="exam_year" min="1990" max="{{ now()->year+1 }}" placeholder="{{ now()->year }}"></label>
<label>{{ __('Class / Level') }}<select name="class_level"><option value="">{{ __('Select') }}</option>@foreach(['Class 6','Class 7','Class 8','Class 9','SSC','HSC','Alim','Dakhil','Vocational','Other'] as $level)<option>{{ $level }}</option>@endforeach</select></label>
<label>{{ __('Subject') }}<input required name="subject" maxlength="100" placeholder="English, Bangla, Mathematics…"></label>
<label>{{ __('Question type') }}<select name="question_type"><option value="creative">{{ __('Creative / CQ') }}</option><option value="mcq">MCQ</option><option value="short">{{ __('Short question') }}</option><option value="grammar">{{ __('Grammar / Writing') }}</option><option value="math">{{ __('Mathematics') }}</option><option value="science">{{ __('Science') }}</option><option value="other">{{ __('Other') }}</option></select></label>
<label>{{ __('Answer language') }}<select name="language"><option value="bn" @selected($settings['default_language']==='bn')>বাংলা</option><option value="en" @selected($settings['default_language']==='en')>English</option></select></label>
</div>
<label>{{ __('Write / paste the question') }}<textarea name="question" rows="5" maxlength="{{ $settings['max_question_length'] }}" placeholder="এখানে প্রশ্ন লিখুন…"></textarea></label>
@if($settings['allow_image_questions'])<label>{{ __('Or upload a clear question photo (JPG, PNG, WebP; max :size MB)',['size'=>number_format($settings['max_image_kb']/1024,1)]) }}<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>@endif
<label>{{ __('Solution mode') }}<select name="mode"><option value="full">{{ __('Full step-by-step solution') }}</option><option value="hints">{{ __('Hints first (practice mode)') }}</option><option value="explain">{{ __('Explain the concept') }}</option></select></label>
<button id="board-solver-submit" class="btn primary" style="justify-self:start">{{ __('Solve Question') }}</button></form><p id="board-solver-status" class="muted" aria-live="polite" style="margin-top:12px"></p></div>
<section id="board-solver-answer" class="panel" hidden style="margin-top:18px"><h2>{{ __('Solution') }}</h2><div id="board-solver-answer-text" style="margin-top:12px;white-space:pre-wrap;line-height:1.8"></div></section>
@endsection
@push('scripts')
<script>
(()=>{const form=document.getElementById('board-solver-form'),button=document.getElementById('board-solver-submit'),status=document.getElementById('board-solver-status'),answer=document.getElementById('board-solver-answer'),text=document.getElementById('board-solver-answer-text');if(!form)return;form.addEventListener('submit',async e=>{e.preventDefault();button.disabled=true;button.textContent='Solving…';status.textContent='Your question is being analysed. Please wait.';answer.hidden=true;try{const response=await fetch(@json(route('student.board-solver.solve')),{method:'POST',body:new FormData(form),headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to solve this question.');text.textContent=data.solution;answer.hidden=false;status.textContent='Solution ready. Review the steps and practise on your own.';}catch(error){status.textContent=error.message;}finally{button.disabled=false;button.textContent='{{ __('Solve Question') }}';}});})();
</script>
@endpush
