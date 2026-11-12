<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseBuilderController extends Controller
{
    public function edit(Course $course): View
    {
        $course->load(['teacher', 'curriculumItems.post.categories']);
        $availablePosts = CourseContentPost::published()->with('categories')->orderBy('title')->get();

        return view('admin.course-builder.edit', compact('course', 'availablePosts'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'post_ids' => ['nullable', 'array', 'max:300'],
            'post_ids.*' => ['integer', 'distinct', Rule::exists('course_content_posts', 'id')->where(fn ($query) => $query->where('status', 'published'))],
            'section_titles' => ['nullable', 'array'],
            'section_titles.*' => ['nullable', 'string', 'max:120'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'previews' => ['nullable', 'array'],
            'previews.*' => ['nullable', 'boolean'],
            'durations' => ['nullable', 'array'],
            'durations.*' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $postIds = collect($data['post_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $sectionTitles = $data['section_titles'] ?? [];
        $positions = $data['positions'] ?? [];
        $previews = $data['previews'] ?? [];
        $durations = $data['durations'] ?? [];

        DB::transaction(function () use ($course, $postIds, $sectionTitles, $positions, $previews, $durations): void {
            $course->curriculumItems()->when($postIds->isNotEmpty(), fn ($query) => $query->whereNotIn('course_content_post_id', $postIds))->when($postIds->isEmpty(), fn ($query) => $query)->delete();

            foreach ($postIds as $index => $postId) {
                $course->curriculumItems()->updateOrCreate(
                    ['course_content_post_id' => $postId],
                    [
                        'section_title' => $sectionTitles[$postId] ?? null,
                        'position' => (int) ($positions[$postId] ?? (($index + 1) * 10)),
                        'is_preview' => (bool) ($previews[$postId] ?? false),
                        'duration_minutes' => isset($durations[$postId]) ? (int) $durations[$postId] : null,
                    ]
                );
            }
        });

        return redirect()->route('admin.course-builder.edit', $course)->with('success', __('Course curriculum updated.'));
    }
}
