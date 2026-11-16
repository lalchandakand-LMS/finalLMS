@extends('layouts.app')
@section('title', __('Course content studio'))
@section('content')
<div class="page-header"><div><span class="badge info">{{ __('Admin') }}</span><h1>{{ __('Course content studio') }}</h1><p class="muted">{{ __('Create lessons, upload media, set access and sell PDF content from one studio.') }}</p></div><a class="btn primary" href="{{ route('admin.course-content-posts.create') }}">{{ __('Create content post') }}</a></div>
<div class="panel" style="margin-bottom:18px;"><form method="GET" class="grid grid-3">
<label>{{ __('Search') }}<input name="q" value="{{ request('q') }}" placeholder="{{ __('Title or description') }}"></label>
<label>{{ __('Category') }}<select name="category"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)request('category') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>{{ __('Academic Level') }}<select name="academic_level"><option value="">{{ __('All levels') }}</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected((string)request('academic_level') === (string)$level->id)>{{ $level->name }}</option>@endforeach</select></label>
<label>{{ __('Access') }}<select name="access_type"><option value="">{{ __('All access types') }}</option>@foreach(['course_enrollment','free','paid'] as $type)<option value="{{ $type }}" @selected(request('access_type') === $type)>{{ __(ucwords(str_replace('_',' ',$type))) }}</option>@endforeach</select></label>
<label>{{ __('Status') }}<select name="status"><option value="">{{ __('All statuses') }}</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></label>
<div><button class="btn secondary" type="submit">{{ __('Filter') }}</button> <a class="btn secondary" href="{{ route('admin.course-content-posts.index') }}">{{ __('Reset') }}</a></div>
</form></div>
<div class="panel">
<form id="bulk-form" method="POST" action="{{ route('admin.course-content-posts.bulk-update') }}" style="margin-bottom:16px;">
@csrf
<div class="grid grid-3" style="align-items:end;">
<label>{{ __('Bulk status') }}<select name="status" required><option value="">{{ __('Choose status') }}</option>@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}">{{ __(ucfirst($status)) }}</option>@endforeach</select></label>
<div><button class="btn secondary" type="submit">{{ __('Apply to selected') }}</button></div>
</div>
</form>
@error('post_ids')<p class="error">{{ $message }}</p>@enderror
@error('status')<p class="error">{{ $message }}</p>@enderror
@foreach($errors->get('post_ids.*') as $messages)@foreach($messages as $message)<p class="error">{{ $message }}</p>@endforeach @endforeach
<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">
<thead><tr><th><input type="checkbox" id="select-all-posts" aria-label="{{ __('Select all posts') }}" style="width:auto;"></th><th>{{ __('Content') }}</th><th>{{ __('Level / Category') }}</th><th>{{ __('Access / Price') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($posts as $post)
<tr style="border-top:1px solid var(--border);">
<td style="padding:12px;"><input class="post-select" type="checkbox" name="post_ids[]" value="{{ $post->id }}" form="bulk-form" aria-label="{{ __('Select :title', ['title' => $post->title]) }}" style="width:auto;"></td>
<td style="padding:12px;">@if($post->thumbnail_path)<img src="{{ asset('storage/'.$post->thumbnail_path) }}" alt="{{ $post->title }}" style="width:76px;height:48px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px;">@elseif($post->thumbnail)<img src="{{ $post->thumbnail->url }}" alt="{{ $post->thumbnail->alt_text ?: $post->title }}" style="width:76px;height:48px;object-fit:cover;border-radius:8px;vertical-align:middle;margin-right:8px;">@endif<strong>{{ $post->title }}</strong><div class="muted">{{ $post->slug }}</div>@if($post->content_file_name)<div class="muted">{{ $post->content_file_name }}</div>@endif</td>
<td>{{ $post->academicLevel?->name ?: '—' }}<div class="muted">{{ $post->categories->pluck('name')->join(', ') ?: '—' }}</div></td>
<td>{{ __(ucwords(str_replace('_',' ',$post->access_type))) }}@if($post->access_type === 'paid')<div class="muted">৳{{ number_format((float)$post->regular_price,2) }} @if($post->sale_price) · {{ __('Sale') }} ৳{{ number_format((float)$post->sale_price,2) }} @endif</div>@endif</td>
<td><span class="badge {{ $post->status === 'published' ? 'success' : 'warning' }}">{{ __(ucfirst($post->status)) }}</span></td>
<td><a class="btn secondary" href="{{ route('admin.course-content-posts.edit', $post) }}">{{ __('Edit') }}</a>
<form method="POST" action="{{ route('admin.course-content-posts.destroy', $post) }}" style="display:inline;" onsubmit="return confirm('{{ __('Delete this post?') }}')">@csrf @method('DELETE')<button class="btn secondary" type="submit">{{ __('Delete') }}</button></form>
@if($post->product_id)<a class="btn secondary" href="{{ route('admin.ebooks.orders.index') }}">{{ __('Sales / Orders') }}</a>@endif
</td></tr>
@empty<tr><td colspan="6" class="muted" style="padding:18px;">{{ __('No content posts found.') }}</td></tr>@endforelse
</tbody></table></div>{{ $posts->links() }}</div>
<script>
document.getElementById('select-all-posts')?.addEventListener('change', function () {
    document.querySelectorAll('.post-select').forEach((checkbox) => { checkbox.checked = this.checked; });
});
</script>
@endsection
