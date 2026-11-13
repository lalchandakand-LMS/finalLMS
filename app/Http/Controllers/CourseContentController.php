<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentPost;
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
            'nextItem', 'canAccessNext', 'canAccessPrevious', 'canViewAllLessons'
        ));
    }
}
