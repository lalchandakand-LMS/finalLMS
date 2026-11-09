@extends('layouts.app')
@section('title','Question Sets')
@section('content')
<div class="container" style="max-width:1200px;margin:30px auto;padding:0 18px">
<h1>Question & Answer Sets</h1><p>Build reusable question collections from the existing question bank and preserve original questions.</p>
<form method="POST" action="{{ route('admin.question-sets.store') }}" class="panel" style="padding:18px;border:1px solid var(--border);border-radius:12px;margin-bottom:22px">
@csrf<h3>Create question set</h3>
<label>Set title<input name="title" maxlength="180" required style="display:block;width:100%;padding:9px"></label>
<label>Description<textarea name="description" maxlength="5000" style="display:block;width:100%;padding:9px"></textarea></label>
<label>Status<select name="status" style="display:block;padding:9px"><option value="draft">Draft</option><option value="published">Published</option></select></label>
<h4>Select questions</h4><div style="max-height:300px;overflow:auto;border:1px solid var(--border);padding:12px">
@forelse($questions as $question)
<label style="display:block;padding:7px 0"><input type="checkbox" name="question_ids[]" value="{{ $question->id }}"> {{ \Illuminate\Support\Str::limit(strip_tags($question->question),180) }} <small>· {{ $question->subject }} · {{ $question->teacher->name ?? 'Teacher' }}</small></label>
@empty<p>No question bank entries found.</p>@endforelse
</div><button class="btn btn-primary" style="margin-top:12px">Create set</button>
</form>
@foreach($sets as $set)
<section class="panel" style="padding:18px;margin:14px 0;border:1px solid var(--border);border-radius:12px">
<form method="POST" action="{{ route('admin.question-sets.update',$set) }}">@csrf @method('PUT')
<label>Title<input name="title" value="{{ $set->title }}" required style="display:block;width:100%;padding:9px"></label>
<label>Description<textarea name="description" style="display:block;width:100%;padding:9px">{{ $set->description }}</textarea></label>
<label>Status<select name="status"><option value="draft" @selected($set->status==='draft')>Draft</option><option value="published" @selected($set->status==='published')>Published</option></select></label>
<p><strong>Questions in this set:</strong> {{ $set->questions->count() }}</p>
<div style="max-height:260px;overflow:auto;border:1px solid var(--border);padding:12px">
@foreach($questions as $question)
<label style="display:block;padding:6px 0"><input type="checkbox" name="question_ids[]" value="{{ $question->id }}" @checked($set->questions->contains('id',$question->id))> {{ \Illuminate\Support\Str::limit(strip_tags($question->question),160) }} <small>· {{ $question->subject }}</small></label>
@endforeach
</div><button class="btn btn-primary" style="margin-top:10px">Save set</button>
</form>
<form method="POST" action="{{ route('admin.question-sets.destroy',$set) }}" style="margin-top:8px">@csrf @method('DELETE')<button class="btn">Delete set</button></form>
</section>
@endforeach
{{ $sets->links() }}
</div>
@endsection