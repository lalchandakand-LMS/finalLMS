<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\CourseContentProgress;
use App\Models\DownloadPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $hasPurchased = $user?->isStudent() && $post->product_id
            ? DownloadPermission::query()->where('user_id', $user->id)
                ->where('product_id', $post->product_id)->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('downloads_remaining')->orWhere('downloads_remaining', '>', 0))
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))->exists()
            : false;

        if ($isEnrolled && ! $canManage && ! $this->isLessonUnlocked($course, $item, $user)) {
            return redirect()->route('courses.show', $course)->with('warning', __('Complete the required lesson before opening this content.'));
        }

        $accessAllowed = match ($post->access_type) {
            'free' => true,
            'paid' => $canManage || $isEnrolled || $hasPurchased,
            default => $item->is_preview || $canManage || $isEnrolled,
        };

        if (! $accessAllowed) {
            if ($post->access_type === 'paid' && $post->product_id) {
                if (! $user) {
                    return redirect()->route('login', ['continue' => route('ebooks.show', $post->product->slug, false)])
                        ->with('info', __('Purchase this PDF to unlock the lesson.'));
                }
                return redirect()->route('ebooks.show', $post->product->slug)
                    ->with('warning', __('Purchase this content to unlock the lesson.'));
            }

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

        $post->load(['thumbnail', 'categories', 'academicLevel', 'product']);
        $curriculumItems = $course->curriculumItems()->with('post')->get()
            ->filter(fn ($curriculumItem) => $curriculumItem->post && $curriculumItem->post->status === 'published' && $curriculumItem->post->published_at?->lte(now()))
            ->values();
        $currentIndex = $curriculumItems->search(fn ($curriculumItem) => $curriculumItem->id === $item->id);
        $previousItem = $currentIndex !== false && $currentIndex > 0 ? $curriculumItems[$currentIndex - 1] : null;
        $nextItem = $currentIndex !== false ? ($curriculumItems[$currentIndex + 1] ?? null) : null;
        $canViewAllLessons = $canManage || $isEnrolled || $hasPurchased || $post->access_type === 'free';
        $canAccessNext = $nextItem && ($nextItem->is_preview || $canViewAllLessons);
        $canAccessPrevious = $previousItem && ($previousItem->is_preview || $canViewAllLessons);

        return view('courses.content', compact(
            'course', 'post', 'item', 'curriculumItems', 'previousItem',
            'nextItem', 'canAccessNext', 'canAccessPrevious', 'canViewAllLessons', 'contentProgress'
        ));
    }

    public function file(Request $request, Course $course, CourseContentPost $post)
    {
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        $curriculumItem = $course->curriculumItems()->where('course_content_post_id', $post->id)->firstOrFail();
        abort_unless($post->content_file_path && Storage::disk('local')->exists($post->content_file_path), 404);

        $user = $request->user();
        $canManage = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        $isEnrolled = $user?->isStudent() && $course->enrollments()
            ->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        $hasPurchased = $user?->isStudent() && $post->product_id
            ? DownloadPermission::query()->where('user_id', $user->id)
                ->where('product_id', $post->product_id)->whereNull('revoked_at')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($query) => $query->whereNull('downloads_remaining')->orWhere('downloads_remaining', '>', 0))
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))->exists()
            : false;
        $previewFileAllowed = $post->access_type !== 'paid' && $curriculumItem->is_preview;
        if ($isEnrolled && ! $canManage) abort_unless($this->isLessonUnlocked($course, $curriculumItem, $user), 403);
        if ($post->access_type === 'paid' && ! $canManage && ! $isEnrolled) {
            $permission = DownloadPermission::query()->where('user_id', $user?->id)
                ->where('product_id', $post->product_id)
                ->whereHas('orderItem.order', fn ($query) => $query->where('status', 'completed'))
                ->firstOrFail();

            return app(EbookStoreController::class)->download($permission, $request);
        }

        abort_unless($canManage || $isEnrolled || $hasPurchased || $post->access_type === 'free' || $previewFileAllowed, 403);

        $absolutePath = Storage::disk('local')->path($post->content_file_path);
        $mime = $post->content_file_mime ?: 'application/octet-stream';
        $inline = str_starts_with($mime, 'video/') || str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        $response = response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"', '', $post->content_file_name ?: 'lesson-file').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function saveProgress(Request $request, Course $course, CourseContentPost $post): JsonResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless($course->status === 'published', 404);
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        abort_unless($course->enrollments()->where('user_id', $student->id)->whereIn('status', ['active', 'completed'])->exists(), 403);
        $item = $course->curriculumItems()->where('course_content_post_id', $post->id)->firstOrFail();
        abort_unless($this->isLessonUnlocked($course, $item, $student), 403, 'Complete the required lesson first.');
        $data = $request->validate([
            'position_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);
        $position = min((int) $data['position_seconds'], (int) $data['duration_seconds']);
        $duration = (int) $data['duration_seconds'];
        $percent = min(100, round(($position / $duration) * 100, 2));
        $progress = DB::transaction(function () use ($item, $student, $position, $duration, $percent): CourseContentProgress {
            $record = CourseContentProgress::query()->where('course_curriculum_item_id', $item->id)
                ->where('user_id', $student->id)->lockForUpdate()->first();
            if (! $record) $record = new CourseContentProgress(['course_curriculum_item_id' => $item->id, 'user_id' => $student->id]);
            $record->last_position_seconds = $position;
            $record->duration_seconds = $duration;
            $record->progress_percent = max((float) ($record->progress_percent ?? 0), $percent);
            $record->last_viewed_at = now();
            if ($record->progress_percent >= 90) $record->completed_at ??= now();
            $record->save();
            return $record;
        }, 3);
        return response()->json([
            'saved' => true,
            'position_seconds' => $progress->last_position_seconds,
            'duration_seconds' => $progress->duration_seconds,
            'progress_percent' => (float) $progress->progress_percent,
            'completed' => $progress->completed_at !== null,
        ]);
    }

    private function isLessonUnlocked(Course $course, \\App\\Models\\CourseCurriculumItem $item, \\App\\Models\\User $student): bool
    {
        if ($item->prerequisite_post_id) {
            $prerequisite = $course->curriculumItems()->where('course_content_post_id', $item->prerequisite_post_id)->first();
            if (! $prerequisite || ! CourseContentProgress::query()
                ->where('course_curriculum_item_id', $prerequisite->id)
                ->where('user_id', $student->id)
                ->whereNotNull('completed_at')->exists()) {
                return false;
            }
        }

        if ($item->unlock_after_previous) {
            $previous = $course->curriculumItems()->where('position', '<', $item->position)
                ->orderByDesc('position')->orderByDesc('id')->first();
            if ($previous && ! CourseContentProgress::query()
                ->where('course_curriculum_item_id', $previous->id)
                ->where('user_id', $student->id)
                ->whereNotNull('completed_at')->exists()) {
                return false;
            }
        }

        return true;
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

        $item = $course->curriculumItems()->where('course_content_post_id', $post->id)->firstOrFail();
        abort_unless($this->isLessonUnlocked($course, $item, $student), 403, 'Complete the required lesson first.');

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
            $progress->progress_percent = 100;
            if ($progress->duration_seconds) $progress->last_position_seconds = $progress->duration_seconds;
            $progress->completed_at ??= now();
            $progress->save();
        });

        return redirect()->route('courses.content.show', [$course, $post])
            ->with('success', __('Lesson marked complete.'));
    }
}
