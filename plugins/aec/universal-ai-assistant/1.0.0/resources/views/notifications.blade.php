@extends('layouts.app')
@section('title', __('AI Agent Notifications'))
@section('content')
<div class="page-header"><div><h1>{{ __('AI Agent Notifications') }}</h1><p class="muted">{{ __('Private in-app notices about AI draft and approval workflows.') }}</p></div></div>
<section class="panel">@forelse($items as $item)<article style="padding:12px 0;border-bottom:1px solid var(--border-color,#ddd)"><strong>{{ $item->title }}</strong>@if(!$item->read_at)<span class="badge info">{{ __('New') }}</span>@endif<p>{{ $item->body }}</p><small class="muted">{{ $item->created_at }}</small>@if(!$item->read_at)<form method="POST" action="{{ route('ai-agent.notifications.read',$item->id) }}">@csrf<button class="btn secondary" type="submit">{{ __('Mark as read') }}</button></form>@endif</article>@empty<p class="muted">{{ __('No notifications yet.') }}</p>@endforelse</section>
@endsection
