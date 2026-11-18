@extends('layouts.app')
@section('title', __('My Solved Board Questions'))
@section('content')
<div class="page-header"><div><h1>{{ __('My Solved Board Questions') }}</h1><p class="muted">{{ __('Your private learning history.') }}</p></div><a class="btn secondary" href="{{ route('student.board-solver.index') }}">{{ __('Solve another question') }}</a></div>
<div class="grid">
@forelse($history as $item)<article class="panel"><div class="muted" style="font-size:.85rem">{{ $item->board ?: __('Board not set') }} · {{ $item->exam_year ?: __('Year not set') }} · {{ $item->class_level ?: __('Class not set') }} · {{ $item->subject }} · {{ $item->created_at }}</div><h2 style="margin-top:10px">{{ $item->question }}</h2><details style="margin-top:12px"><summary style="cursor:pointer;font-weight:700">{{ __('Show solution') }}</summary><div style="margin-top:12px;white-space:pre-wrap;line-height:1.8">{{ $item->solution }}</div></details></article>@empty<div class="panel muted" style="text-align:center">{{ __('No solved questions yet.') }}</div>@endforelse
</div>
<div style="margin-top:18px">{{ $history->links() }}</div>
@endsection
