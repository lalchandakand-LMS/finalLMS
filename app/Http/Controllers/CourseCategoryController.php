<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.course-categories.index', [
            'categories' => CourseCategory::withCount(['courses', 'posts'])
                ->orderBy('sort_order')->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['name']);
        unset($data['image']);
        if ($request->hasFile('image')) $data['image_path'] = $request->file('image')->store('course-categories', 'public');
        CourseCategory::create($data);

        return back()->with('success', 'Course category created.');
    }

    public function update(Request $request, CourseCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['name'], $category->id);
        unset($data['image']);
        $oldImagePath = $category->image_path;
        $newImagePath = $request->file('image')?->store('course-categories', 'public');
        if ($newImagePath) $data['image_path'] = $newImagePath;
        $category->update($data);
        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) Storage::disk('public')->delete($oldImagePath);

        return back()->with('success', 'Course category updated.');
    }

    public function destroy(CourseCategory $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $category->courses()->detach();
            $category->posts()->get()->each(function ($post): void {
                $post->forceFill(['category' => null])->save();
            });
            $category->posts()->detach();
            $category->delete();
        });

        if ($category->image_path) Storage::disk('public')->delete($category->image_path);
        return back()->with('success', 'Category deleted; courses and content posts were preserved.');
    }

    private function validated(Request $request, ?CourseCategory $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('course_categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'course-category';
        $slug = $base;
        $suffix = 2;
        while (CourseCategory::query()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
