@extends('layouts.app')
@section('title', __('Exam question papers'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Exam question papers') }}</h1><p class="muted">{{ __('Saved snapshots built from your preparation question bank.') }}</p></div><a class="btn primary" href="{{ route('admin.prep-question-bank.sets.create') }}">{{ __('Create exam paper') }}</a></div>
<div class="panel"><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>{{ __('Title') }}</th><th>{{ __('Track') }}</th><th>{{ __('Questions') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($sets as $set)<tr style="border-top:1px solid var(--border)"><td>{{ $set->title }}</td><td>{{ strtoupper(str_replace('_',' ',$set->track)) }}</td><td>{{ $set->questions_count }}</td><td>{{ $set->duration_minutes }} {{ __('min') }}</td><td>{{ ucfirst($set->status) }}</td><td><a class="btn secondary" href="{{ route('admin.prep-question-bank.sets.show',$set->id) }}">{{ __('Open / print') }}</a><form method="POST" action="{{ route('admin.prep-question-bank.sets.destroy',$set->id) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this paper?') }}')">@csrf @method('DELETE')<button class="btn secondary">{{ __('Delete') }}</button></form></td></tr>@empty<tr><td colspan="6" class="muted">{{ __('No exam papers created yet.') }}</td></tr>@endforelse
</tbody></table></div>{{ $sets->links() }}</div>
@endsection
