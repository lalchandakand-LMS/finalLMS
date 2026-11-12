<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseCategoryController extends Controller
{
    public function index(): View
    {
        $categories = CourseCategory::with('image')->withCount('posts')
            ->orderBy('sort_order')->orderBy('name')->paginate(25);

        return view('admin.course-categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name']);
        CourseCategory::create($data);

        return redirect()->route('admin.course-categories.index')->with('success', __('Course category created.'));
    }

    public function update(Request $request, CourseCategory $courseCategory): RedirectResponse
    {
        $data = $this->validated($request, $courseCategory);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $courseCategory->id);
        $courseCategory->update($data);

        return redirect()->route('admin.course-categories.index')->with('success', __('Course category updated.'));
    }

    public function destroy(CourseCategory $courseCategory): RedirectResponse
    {
        abort_if($courseCategory->posts()->exists(), 422, __('Reassign or remove this category from content posts before deleting it.'));
        $courseCategory->delete();

        return back()->with('success', __('Course category deleted.'));
    }

    private function validated(Request $request, ?CourseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('course_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'image_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $data['is_visible'] = $request->boolean('is_visible');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (CourseCategory::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
