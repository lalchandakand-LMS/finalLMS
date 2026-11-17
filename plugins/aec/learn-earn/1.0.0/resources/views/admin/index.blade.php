@extends('layouts.app')

@section('title', __('Learn & Earn Question Bank') . ' | ' . config('app.name'))

@section('content')
<div class="page-header">
    <div>
        <span class="badge info">{{ __('Admin') }}</span>
        <h1>{{ __('Learn & Earn Question Bank') }}</h1>
        <p class="muted">{{ __('Create reusable MCQs with categories, positive marks and negative marks.') }}</p>
    </div>
    <a class="btn primary" href="{{ route('admin.learn-earn.questions.create') }}">{{ __('Create MCQ') }}</a>
</div>

<div class="grid grid-3" style="margin-bottom:20px;">
    <div class="panel"><span class="muted">{{ __('Total questions') }}</span><h2>{{ $summary['total'] }}</h2></div>
    <div class="panel"><span class="muted">{{ __('Published') }}</span><h2>{{ $summary['published'] }}</h2></div>
    <div class="panel"><span class="muted">{{ __('Draft') }}</span><h2>{{ $summary['draft'] }}</h2></div>
</div>

<div class="panel" style="margin-bottom:20px;">
    <h2>{{ __('Question categories') }}</h2>
    <form method="POST" action="{{ route('admin.learn-earn.categories.store') }}" class="grid grid-2">
        @csrf
        <label>{{ __('Category name') }}<input name="name" maxlength="120" required></label>
        <label>{{ __('Description') }}<input name="description" maxlength="1000"></label>
        <div><button class="btn secondary" type="submit">{{ __('Add category') }}</button></div>
    </form>
    <div style="overflow-x:auto;margin-top:14px;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Questions') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:10px;">
                        <form method="POST" action="{{ route('admin.learn-earn.categories.update', $category) }}" class="grid grid-2">
                            @csrf @method('PUT')
                            <input name="name" value="{{ $category->name }}" maxlength="120" required>
                            <input name="description" value="{{ $category->description }}" maxlength="1000" placeholder="{{ __('Description') }}">
                            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) style="width:auto;">{{ __('Active') }}</label>
                            <button class="btn secondary" type="submit">{{ __('Save') }}</button>
                        </form>
                    </td>
                    <td>{{ $category->questions_count }}</td>
                    <td>{{ $category->is_active ? __('Active') : __('Archived') }}</td>
                    <td>
                        @if($category->is_active)
                        <form method="POST" action="{{ route('admin.learn-earn.categories.archive', $category) }}" onsubmit="return confirm('{{ __('Archive this category?') }}')">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Archive') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="padding:12px;">{{ __('No categories created yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<form method="GET" class="panel" style="margin-bottom:18px;">
    <div class="grid grid-3">
        <label>{{ __('Search') }}<input name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120"></label>
        <label>{{ __('Category') }}<select name="category_id"><option value="">{{ __('All categories') }}</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)($filters['category_id'] ?? '') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label>{{ __('Status') }}<select name="status"><option value="">{{ __('All statuses') }}</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Draft') }}</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option><option value="archived" @selected(($filters['status'] ?? '') === 'archived')>{{ __('Archived') }}</option></select></label>
    </div>
    <button class="btn secondary" type="submit">{{ __('Filter') }}</button>
</form>

<div class="panel">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr><th>{{ __('Question') }}</th><th>{{ __('Category') }}</th><th>{{ __('Marks') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($questions as $item)
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:12px;min-width:240px;"><strong>{{ \Illuminate\Support\Str::limit($item->question, 110) }}</strong><div class="muted">{{ $item->subject ?: __('General') }} · {{ ucfirst($item->difficulty) }}</div></td>
                    <td>{{ $item->category?->name ?? __('Uncategorized') }}</td>
                    <td>{{ $item->marks }} / -{{ $item->negative_marks }}</td>
                    <td>{{ __(ucfirst($item->status)) }}</td>
                    <td style="white-space:nowrap;">
                        <a class="btn secondary" href="{{ route('admin.learn-earn.questions.edit', $item) }}">{{ __('Edit') }}</a>
                        @if($item->status !== 'archived')
                        <form method="POST" action="{{ route('admin.learn-earn.questions.archive', $item) }}" style="display:inline;" onsubmit="return confirm('{{ __('Archive this question?') }}')">
                            @csrf @method('PATCH')
                            <button class="btn secondary" type="submit">{{ __('Archive') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="padding:18px;">{{ __('No questions found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $questions->links() }}
</div>
@endsection
