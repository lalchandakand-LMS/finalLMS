@extends('layouts.app')
@section('title', __('Exam merit list') . ' | ' . $exam->title)
@section('content')
<div class="page-header no-print">
    <div><span class="badge info">{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</span><h1>{{ __('Exam merit list and result sheet') }}</h1><p class="muted">{{ $exam->title }} · {{ $exam->starts_at->format('d M Y') }}</p></div>
    <div style="display:flex;gap:8px;"><button class="btn primary" type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button><a class="btn secondary" href="{{ url()->previous() }}">{{ __('Back') }}</a></div>
</div>
<div class="panel" id="print-result-sheet">
    <div style="text-align:center;margin-bottom:20px;">
        <h1>{{ config('app.name') }}</h1><h2>{{ $exam->title }}</h2>
        <p>{{ $exam->batch->course->title }} · {{ $exam->batch->title }}</p>
        <p>{{ __('Total candidates:') }} {{ $attempts->count() }} · {{ __('Pass marks:') }} {{ $exam->pass_marks }}</p>
    </div>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;text-align:left;">
        <thead><tr><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Merit') }}</th><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Student') }}</th><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Email') }}</th><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Score') }}</th><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Status') }}</th><th style="padding:9px;border-bottom:2px solid #222;">{{ __('Submitted') }}</th></tr></thead>
        <tbody>@forelse($attempts as $attempt)
            <tr><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->merit_position }}</td><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->student->name }}</td><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->student->email }}</td><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->score }}</td><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->result_label }}</td><td style="padding:9px;border-bottom:1px solid #bbb;">{{ $attempt->submitted_at?->format('d M Y H:i') }}</td></tr>
        @empty<tr><td colspan="6" style="padding:18px;">{{ __('No published submissions yet.') }}</td></tr>@endforelse</tbody>
    </table></div>
    <div style="display:flex;justify-content:space-between;margin-top:55px;"><span>{{ __('Teacher signature') }} __________________</span><span>{{ __('Authorized signature') }} __________________</span></div>
</div>
<style>@media print{body{background:#fff!important}.no-print,header,nav,aside,footer{display:none!important}#print-result-sheet{box-shadow:none!important;border:0!important;margin:0!important;padding:0!important;color:#000!important}#print-result-sheet table{font-size:11px} @page{size:A4 portrait;margin:14mm}}</style>
@endsection
