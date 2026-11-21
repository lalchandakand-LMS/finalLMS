@extends('layouts.app')
@section('title', __('Learning achievements'))
@section('content')
<div class="page-header"><div><h1>{{ __('Learning achievements') }}</h1><p class="muted">{{ __('Track your XP, streaks and earned badges.') }}</p></div><a class="btn secondary" href="{{ route('student.gamification.leaderboard') }}">{{ __('Leaderboard') }}</a></div>
<div class="grid grid-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
    <section class="panel"><div class="muted">{{ __('Total XP') }}</div><strong style="font-size:2rem;">{{ number_format($xp) }}</strong></section>
    <section class="panel"><div class="muted">{{ __('Current streak') }}</div><strong style="font-size:2rem;">{{ $profile->current_streak }} {{ __('days') }}</strong></section>
    <section class="panel"><div class="muted">{{ __('Longest streak') }}</div><strong style="font-size:2rem;">{{ $profile->longest_streak }} {{ __('days') }}</strong></section>
</div>
<section class="panel" style="margin-bottom:20px;"><h2>{{ __('Your badges') }}</h2>
@if($badges->isEmpty())<p class="muted">{{ __('Complete lessons and quizzes to earn badges.') }}</p>
@else<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">@foreach($badges as $badge)<article class="panel"><strong>{{ $badge->name }}</strong><p class="muted">{{ $badge->description }}</p></article>@endforeach</div>@endif
</section>
<section class="panel"><h2>{{ __('Leaderboard privacy') }}</h2><p class="muted">{{ __('Your profile is private on the leaderboard unless you opt in. You can use a display name instead of your account name.') }}</p>
<form method="POST" action="{{ route('student.gamification.preferences') }}">@csrf @method('PUT')
<label style="display:block;margin:12px 0;"><input type="checkbox" name="leaderboard_opt_in" value="1" @checked($profile->leaderboard_opt_in)> {{ __('Show me on the leaderboard') }}</label>
<label for="display_name">{{ __('Leaderboard display name') }}</label><input id="display_name" class="form-control" name="display_name" maxlength="40" value="{{ old('display_name', $profile->display_name) }}" placeholder="{{ __('Choose a nickname') }}">
<label style="display:block;margin:12px 0;"><input type="checkbox" name="show_streak" value="1" @checked($profile->show_streak)> {{ __('Show my streak') }}</label>
<button class="btn primary" type="submit">{{ __('Save privacy settings') }}</button></form></section>
@endsection
