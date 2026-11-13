@extends('layouts.app')

@section('title', ($courseCombo->exists ? __('Edit course combo') : __('Create course combo')) . ' | ' . config('app.name'))

@section('content')
    <div class="page-header">
        <div><span class="badge info">Admin</span><h1 style="margin:12px 0 0;">{{ $courseCombo->exists ? __('Edit course combo') : __('Create course combo') }}</h1></div>
        <a class="btn secondary" href="{{ route('admin.course-combos.index') }}">{{ __('Back to combos') }}</a>
    </div>
    <div class="panel" style="max-width:900px;margin:0 auto;">
        <form method="POST" action="{{ $courseCombo->exists ? route('admin.course-combos.update', $courseCombo) : route('admin.course-combos.store') }}">
            @csrf
            @if($courseCombo->exists) @method('PUT') @endif
            <label>{{ __('Combo title') }}
                <input type="text" name="title" maxlength="255" value="{{ old('title', $courseCombo->title) }}" required>
            </label>
            <label>{{ __('Description') }}
                <textarea name="description" rows="5" maxlength="10000">{{ old('description', $courseCombo->description) }}</textarea>
            </label>
            <div class="grid grid-2">
                <label>{{ __('Combo price (BDT)') }}
                    <input type="number" name="price" min="0" max="100000000" step="0.01" value="{{ old('price', $courseCombo->price ?? 0) }}" required>
                </label>
                <label>{{ __('Publication status') }}
                    <select name="status" required>
                        @foreach(['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $courseCombo->status ?? 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <label>{{ __('Included courses (choose at least two)') }}
                @php($selectedCourseIds = array_map('strval', old('course_ids', $courseCombo->exists ? $courseCombo->courses->pluck('id')->all() : [])))
                <select name="course_ids[]" multiple size="10" required style="min-height:230px;">
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(in_array((string) $course->id, $selectedCourseIds, true))>
                            {{ $course->title }} · ৳{{ number_format((float) $course->price, 2) }}{{ $course->open_batches_count ? ' · '.__('Has open batch — unavailable for combos') : '' }}
                        </option>
                    @endforeach
                </select>
                <small class="muted">{{ __('Hold Ctrl (Windows) or Command (Mac) to select multiple courses. Only published courses without open batches can be included because combo checkout does not reserve separate batch seats.') }}</small>
            </label>
            @error('course_ids')<div class="flash error" role="alert">{{ $message }}</div>@enderror
            @error('course_ids.*')<div class="flash error" role="alert">{{ $message }}</div>@enderror
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button class="btn primary" type="submit">{{ __('Save combo') }}</button>
                <a class="btn secondary" href="{{ route('admin.course-combos.index') }}">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
