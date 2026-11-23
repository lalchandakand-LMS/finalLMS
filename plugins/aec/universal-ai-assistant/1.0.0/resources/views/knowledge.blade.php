@extends('layouts.app')
@section('title', __('AI Support Knowledge Base'))
@section('content')
<div class="page-header"><div><h1>{{ __('AI Support Knowledge Base') }}</h1><p class="muted">{{ __('Only administrator-published answers appear here. Each answer can include a source for human verification.') }}</p></div></div>
@if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="flash error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($canManage)<section class="panel"><h2>{{ __('Publish verified FAQ') }}</h2><form method="POST" action="{{ route('admin.ai-assistant.knowledge.store') }}">@csrf
<label>{{ __('Category') }}</label><input name="category" maxlength="60" required><label>{{ __('Question') }}</label><input name="question" maxlength="240" required><label>{{ __('Verified answer') }}</label><textarea name="answer" maxlength="12000" rows="5" required></textarea><label>{{ __('Source label') }}</label><input name="source_label" maxlength="180"><label>{{ __('Source URL') }}</label><input name="source_url" type="url" maxlength="500"><button class="btn primary">{{ __('Publish FAQ') }}</button></form></section>@endif
<section class="panel"><h2>{{ __('Published support answers') }}</h2>@forelse($items as $item)<article style="padding:12px 0;border-bottom:1px solid var(--border-color,#ddd)"><span class="badge">{{ $item->category }}</span><h3>{{ $item->question }}</h3><p>{{ $item->answer }}</p>@if($item->source_url)<a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $item->source_label ?: __('View source') }}</a>@elseif($item->source_label)<p class="muted">{{ $item->source_label }}</p>@endif</article>@empty<p class="muted">{{ __('No verified FAQ entries have been published yet.') }}</p>@endforelse</section>
@endsection
