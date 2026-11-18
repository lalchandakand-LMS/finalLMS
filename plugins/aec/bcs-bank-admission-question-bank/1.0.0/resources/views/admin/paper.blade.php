@extends('layouts.app')
@section('title', $paper->title)
@section('content')
<div class="page-header no-print"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ $paper->title }}</h1><p class="muted">{{ strtoupper(str_replace('_',' ',$paper->track)) }} · {{ $paper->duration_minutes }} {{ __('minutes') }} · {{ $items->count() }} {{ __('questions') }} · {{ $totalMarks }} {{ __('marks') }}</p></div><div style="display:flex;gap:8px"><label class="no-print" style="display:flex;align-items:center;gap:6px"><input id="show-answer-key" type="checkbox" style="width:auto">{{ __('Show answer key') }}</label><button class="btn primary" onclick="window.print()">{{ __('Print / Save PDF') }}</button><a class="btn secondary" href="{{ route('admin.prep-question-bank.sets.index') }}">{{ __('Back') }}</a></div></div>
<div class="panel paper-wrap"><div class="paper-heading"><h1>{{ $paper->title }}</h1><p>{{ strtoupper(str_replace('_',' ',$paper->track)) }} | {{ $paper->duration_minutes }} minutes | Total marks: {{ $totalMarks }}</p><p>{{ $paper->instructions }}</p></div>
@foreach($items as $i=>$q)<article class="paper-question"><p><strong>{{ $i+1 }}. [{{ $q->subject }}] {{ $q->question }}</strong> <span>({{ $q->marks }} {{ __('marks') }})</span></p>
@if($q->type==='mcq')<ol type="A">@foreach($q->options as $option)<li>{{ $option }}</li>@endforeach</ol>@endif
<div class="answer-key"><strong>{{ __('Answer') }}:</strong> @if($q->type==='mcq'){{ ['A','B','C','D'][(int)$q->correct_option] ?? '—' }}. {{ $q->options[(int)$q->correct_option] ?? '' }}@else{{ $q->correct_answer ?: '—' }}@endif
@if($q->explanation)<p>{{ $q->explanation }}</p>@endif</div></article>@endforeach
</div>
<style>
.paper-wrap{max-width:900px;margin:auto;background:white;color:#111;padding:36px}.paper-heading{text-align:center;border-bottom:2px solid #222;padding-bottom:16px;margin-bottom:22px}.paper-question{padding:12px 0;border-bottom:1px solid #ddd;break-inside:avoid}.paper-question ol{display:grid;grid-template-columns:1fr 1fr;gap:6px}.answer-key{background:#f3f4f6;padding:8px;margin-top:8px;border-radius:6px}.paper-question>p>span{white-space:nowrap}
.answer-key{display:none}.show-answer-key .answer-key{display:block}
@media print{body{background:#fff!important}.admin-shell,.admin-sidebar,.admin-topbar,.no-print{display:none!important}.paper-wrap{max-width:none;margin:0;padding:0;box-shadow:none;border:0}.answer-key{background:#eee!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
@push('scripts')<script>document.getElementById('show-answer-key')?.addEventListener('change',e=>document.body.classList.toggle('show-answer-key',e.target.checked));</script>@endpush
@endsection
