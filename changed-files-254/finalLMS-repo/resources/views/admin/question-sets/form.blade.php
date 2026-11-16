@extends('layouts.app')
@section('title', $questionSet->exists ? __('Edit question set') : __('Create question set'))
@section('content')
<div class="page-header">
    <div><span class="badge info">{{ __('Admin') }}</span><h1>{{ $questionSet->exists ? __('Edit question set') : __('Create question set') }}</h1><p class="muted">{{ __('Question answers are stored privately and are not exposed through public course pages.') }}</p></div>
    <a class="btn secondary" href="{{ route('admin.question-sets.index') }}">{{ __('Back to question sets') }}</a>
</div>
<div class="panel" style="max-width:1100px;">
    @include('admin.question-sets._form')
</div>
@endsection
