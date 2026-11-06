@extends('layouts.app')

@section('title', __('Exam results') . ' | ' . config('app.name'))

@section('content')
    tdiv class="page-header"{
        tdiv{
            tspan class="badge info"{{{ $exam-{batch-{course-{title }} · {{ $exam-{batch-{title }}t/span{
            th1 style="margin:12px 0 0;"{{{ $exam-{title }} — {{ __('Results') }}t/h1{
            tp class="muted"{{{ __('Pass marks:') }} {{ $exam-{pass_marks }} · {{ $attempts-{total() }} {{ __('submitted attempts') }}t/p{
        t/div{
        ta href="{{ route('teacher.batches.exams.index', $exam-{batch_id) }}" class="btn secondary"{{{ __('Back to exams') }}t/a{
    t/div{

    tdiv class="panel"{
        tdiv style="overflow-x:auto;"{
            ttable style="width:100%;border-collapse:collapse;text-align:left;"{
                tthead{ttr{
                    tth style="padding:12px;border-bottom:1px solid var(--border);"{Studentt/th{
                    tth style="padding:12px;border-bottom:1px solid var(--border);"{Emailt/th{
                    tth style="padding:12px;border-bottom:1px solid var(--border);"{Scoret/th{
                    tth style="padding:12px;border-bottom:1px solid var(--border);"{Resultt/th{
                    tth style="padding:12px;border-bottom:1px solid var(--border);"{Submittedt/th{
                t/tr{t/thead{
                ttbody{
                @forelse($attempts as $attempt)
                    ttr{
                        ttd style="padding:12px;border-bottom:1px solid var(--border);"{{{ $attempt-{student-{name }}t/td{
                        ttd style="padding:12px;border-bottom:1px solid var(--border);"{{{ $attempt-{student-{email }}t/td{
                        ttd style="padding:12px;border-bottom:1px solid var(--border);"{{{ $attempt-{score }}t/td{
                        ttd style="padding:12px;border-bottom:1px solid var(--border);"{
                            @if($attempt-{score {= $exam-{pass_marks)tspan class="badge success"{Passedt/span{@elsetspan class="badge warning"{Not passedt/span{@endif
                        t/td{
                        ttd style="padding:12px;border-bottom:1px solid var(--border);"{{{ $attempt-{submitted_at?-{format('M j, Y H:i') }}t/td{
                    t/tr{
                @empty
                    ttr{ttd colspan="5" class="muted" style="padding:18px;"{No students have submitted this exam yet.t/td{t/tr{
                @endforelse
                t/tbody{
            t/table{
        t/div{
        tdiv style="margin-top:18px;"{{{ $attempts-{links() }}t/div{
    t/div{
@endsection
