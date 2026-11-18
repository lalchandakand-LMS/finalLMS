@extends('layouts.app')
@section('title', __('Board Solver Activity'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Board Solver Activity') }}</h1><p class="muted">{{ __('Administrative view of submitted questions and generated solutions.') }}</p></div></div>
<div class="panel"><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>{{ __('Student ID') }}</th><th>{{ __('Board / Year') }}</th><th>{{ __('Class / Subject') }}</th><th>{{ __('Question') }}</th><th>{{ __('Date') }}</th></tr></thead><tbody>
@forelse($history as $item)<tr style="border-top:1px solid var(--border)"><td>{{ $item->user_id }}</td><td>{{ $item->board ?: '—' }} / {{ $item->exam_year ?: '—' }}</td><td>{{ $item->class_level ?: '—' }} / {{ $item->subject }}</td><td>{{ mb_substr($item->question,0,180) }}</td><td>{{ $item->created_at }}</td></tr>@empty<tr><td colspan="5" class="muted" style="padding:18px;text-align:center">{{ __('No solver activity yet.') }}</td></tr>@endforelse
</tbody></table></div>{{ $history->links() }}</div>
@endsection
