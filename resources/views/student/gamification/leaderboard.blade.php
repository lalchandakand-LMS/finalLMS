@extends('layouts.app')
@section('title', __('Learner leaderboard'))
@section('content')
<div class="page-header"><div><h1>{{ __('Learner leaderboard') }}</h1><p class="muted">{{ __('Only learners who opt in appear here.') }}</p></div><a class="btn secondary" href="{{ route('student.gamification.index') }}">{{ __('My achievements') }}</a></div>
<section class="panel"><ol style="padding-left:24px;">@forelse($rows as $row)<li style="padding:12px 0;border-bottom:1px solid var(--border,#e5e7eb);"><strong>{{ $row->display_name }}</strong><span style="float:right;">{{ number_format($row->xp) }} XP @if($row->streak !== null) · {{ $row->streak }} {{ __('day streak') }} @endif</span></li>@empty<p class="muted">{{ __('No learners have opted in yet.') }}</p>@endforelse</ol></section>
@endsection
