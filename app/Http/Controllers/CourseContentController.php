<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\CourseContentProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CourseContentController extends Controller
{
    public function show(Request $request, Course $course, CourseContentPost $post): View|RedirectResponse
    {
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);

        $item = $course->curriculumItems()->where('course_content_post_id', $post->id)->firstOrFail();
        $user = Auth::user();
        $canManage = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        $isEnrolled = $user?->isStudent() && $course->enrollments()
            ->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();

        if (! $item->is_preview && ! $canManage && ! $isEnrolled) {
            if (! $user) {
                return redirect()->route('login', [
                    'continue' => route('courses.show', $course, false),
                ])->with('info', __('Log in and enroll to unlock this lesson.'));
            }

            return redirect()->route('courses.show', $course)
                ->with('warning', __('Enroll in this course to unlock the lesson.'));
        }

        $contentProgress = null;
        if ($isEnrolled) {
            $contentProgress = CourseContentProgress::query()->updateOrCreate(
                ['course_curriculum_item_id' => $item->id, 'user_id' => $user->id],
                ['last_viewed_at' => now()]
            );
        }

        $post->load(['thumbnail', 'categories']);
        $curriculumItems = $course->curriculumItems()->with('post')->get()
            ->filter(fn ($curriculumItem) => $curriculumItem->post && $curriculumItem->post->status === 'published' && $curriculumItem->post->published_at?->lte(now()))
            ->values();
        $currentIndex = $curriculumItems->search(fn ($curriculumItem) => $curriculumItem->id === $item->id);
        $previousItem = $currentIndex !== false && $currentIndex > 0 ? $curriculumItems[$currentIndex - 1] : null;
        $nextItem = $currentIndex !== false ? ($curriculumItems[$currentIndex + 1] ?? null) : null;
        $canViewAllLessons = $canManage || $isEnrolled;
        $canAccessNext = $nextItem && ($nextItem->is_preview || $canViewAllLessons);
        $canAccessPrevious = $previousItem && ($previousItem->is_preview || $canViewAllLessons);

        return view('courses.content', compact(
            'course', 'post', 'item', 'curriculumItems', 'previousItem',
            'nextItem', 'canAccessNext', 'canAccessPrevious', 'canViewAllLessons', 'contentProgress'
        ));
    }

    public function complete(Request $request, Course $course, CourseContentPost $post): RedirectResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        abort_unless($course->enrollments()
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists(), 403);

        $item = $course->curriculumItems()
            ->where('course_content_post_id', $post->id)
            ->firstOrFail();

        DB::transaction(function () use ($item, $student): void {
            $progress = CourseContentProgress::query()
                ->where('course_curriculum_item_id', $item->id)
                ->where('user_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $progress) {
                $progress = new CourseContentProgress([
                    'course_curriculum_item_id' => $item->id,
                    'user_id' => $student->id,
                    'last_viewed_at' => now(),
                ]);
            }

            $progress->last_viewed_at = now();
            $progress->completed_at ??= now();
            $progress->save();
        });

        return redirect()->route('courses.content.show', [$course, $post])
            ->with('success', __('Lesson marked complete.'));
    }
}
