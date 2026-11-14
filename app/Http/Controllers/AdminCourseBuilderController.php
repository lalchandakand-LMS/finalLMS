<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\QuestionSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseBuilderController extends Controller
{
    public function edit(Course $course): View
    {
        $course->load(['teacher', 'curriculumItems.post.categories', 'questionSets.questions']);
        $attachedPostIds = $course->curriculumItems->pluck('course_content_post_id')->all();
        $availablePosts = CourseContentPost::query()
            ->where(fn ($query) => $query->published()->orWhereIn('id', $attachedPostIds))
            ->with('categories')
            ->orderBy('title')
            ->get();
        $availableQuestionSets = QuestionSet::query()
            ->where(fn ($query) => $query->where('status', 'published')
                ->orWhereHas('courses', fn ($courses) => $courses->where('courses.id', $course->id)))
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        return view('admin.course-builder.edit', compact('course', 'availablePosts', 'availableQuestionSets'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $attachedPostIds = $course->curriculumItems()->pluck('course_content_post_id')->all();
        $attachedQuestionSetIds = $course->questionSets()->pluck('question_sets.id')->all();
        $data = $request->validate([
            'post_ids' => ['nullable', 'array', 'max:300'],
            'post_ids.*' => ['integer', 'distinct', Rule::exists('course_content_posts', 'id')->where(fn ($query) => $query->where(function ($eligible) use ($attachedPostIds) {
                $eligible->where(function ($published) {
                    $published->where('status', 'published')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhereIn('id', $attachedPostIds);
            }))],
            'section_titles' => ['nullable', 'array'],
            'section_titles.*' => ['nullable', 'string', 'max:120'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'previews' => ['nullable', 'array'],
            'previews.*' => ['nullable', 'boolean'],
            'durations' => ['nullable', 'array'],
            'durations.*' => ['nullable', 'integer', 'min:1', 'max:600'],
            'question_set_ids' => ['nullable', 'array', 'max:100'],
            'question_set_ids.*' => ['integer', 'distinct', Rule::exists('question_sets', 'id')->where(fn ($query) => $query->where('status', 'published')->orWhereIn('id', $attachedQuestionSetIds))],
            'question_set_section_titles' => ['nullable', 'array'],
            'question_set_section_titles.*' => ['nullable', 'string', 'max:120'],
            'question_set_positions' => ['nullable', 'array'],
            'question_set_positions.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'question_set_max_attempts' => ['nullable', 'array'],
            'question_set_max_attempts.*' => ['nullable', 'integer', 'min:1', 'max:100'],
            'question_set_time_limits' => ['nullable', 'array'],
            'question_set_time_limits.*' => ['nullable', 'integer', 'min:1', 'max:600'],
            'question_set_questions_per_attempt' => ['nullable', 'array'],
            'question_set_questions_per_attempt.*' => ['nullable', 'integer', 'min:1', 'max:200'],
            'question_set_randomize' => ['nullable', 'array'],
            'question_set_randomize.*' => ['nullable', 'boolean'],
            'question_set_available_from' => ['nullable', 'array'],
            'question_set_available_from.*' => ['nullable', 'date'],
        ]);

        $postIds = collect($data['post_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $sectionTitles = $data['section_titles'] ?? [];
        $positions = $data['positions'] ?? [];
        $previews = $data['previews'] ?? [];
        $durations = $data['durations'] ?? [];
        $questionSetIds = collect($data['question_set_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $questionSetSections = $data['question_set_section_titles'] ?? [];
        $questionSetPositions = $data['question_set_positions'] ?? [];
        $questionSetAttempts = $data['question_set_max_attempts'] ?? [];
        $questionSetTimeLimits = $data['question_set_time_limits'] ?? [];
        $questionSetQuestionsPerAttempt = $data['question_set_questions_per_attempt'] ?? [];
        $questionSetRandomize = $data['question_set_randomize'] ?? [];
        $questionSetAvailableFrom = $data['question_set_available_from'] ?? [];

        DB::transaction(function () use ($course, $postIds, $sectionTitles, $positions, $previews, $durations, $questionSetIds, $questionSetSections, $questionSetPositions, $questionSetAttempts, $questionSetTimeLimits, $questionSetQuestionsPerAttempt, $questionSetRandomize, $questionSetAvailableFrom): void {
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

            $questionSetSync = [];
            foreach ($questionSetIds as $index => $setId) {
                $questionSetSync[$setId] = [
                    'section_title' => $questionSetSections[$setId] ?? null,
                    'position' => (int) ($questionSetPositions[$setId] ?? (($index + 1) * 10)),
                    'max_attempts' => isset($questionSetAttempts[$setId]) && $questionSetAttempts[$setId] !== ''
                        ? (int) $questionSetAttempts[$setId] : null,
                    'time_limit_minutes' => isset($questionSetTimeLimits[$setId]) && $questionSetTimeLimits[$setId] !== ''
                        ? (int) $questionSetTimeLimits[$setId] : null,
                    'questions_per_attempt' => isset($questionSetQuestionsPerAttempt[$setId]) && $questionSetQuestionsPerAttempt[$setId] !== ''
                        ? (int) $questionSetQuestionsPerAttempt[$setId] : null,
                    'randomize_questions' => (bool) ($questionSetRandomize[$setId] ?? false),
                    'available_from' => $questionSetAvailableFrom[$setId] ?? null,
                ];
            }
            $course->questionSets()->sync($questionSetSync);
        });

        return redirect()->route('admin.course-builder.edit', $course)->with('success', __('Course curriculum updated.'));
    }
}
