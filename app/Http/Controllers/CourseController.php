<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\PageView;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'teacher' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'integer', 'min:1'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'gte:min_price'],
            'sort' => ['nullable', 'in:latest,oldest,price_low,price_high,title'],
        ]);

        $query = Course::query()
            ->with(['teacher', 'curriculumItems.post.categories'])
            ->withCount(['enrollments as students_count' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'completed'])])
            ->where('status', 'published');

        if (! empty($validated['q'])) {
            $term = trim($validated['q']);
            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', '%'.$term.'%'));
            });
        }

        if (! empty($validated['teacher'])) {
            $query->where('teacher_id', $validated['teacher']);
        }
        if (! empty($validated['category'])) {
            $categoryId = (int) $validated['category'];
            $query->whereHas('curriculumItems.post.categories', fn ($categories) => $categories->whereKey($categoryId));
        }

        if (isset($validated['min_price'])) {
            $query->where('price', '>=', $validated['min_price']);
        }
        if (isset($validated['max_price'])) {
            $query->where('price', '<=', $validated['max_price']);
        }

        match ($validated['sort'] ?? 'latest') {
            'oldest' => $query->oldest(),
            'price_low' => $query->orderBy('price')->orderByDesc('id'),
            'price_high' => $query->orderByDesc('price')->orderByDesc('id'),
            'title' => $query->orderBy('title')->orderBy('id'),
            default => $query->latest(),
        };

        $courses = $query->paginate(12)->withQueryString();
        $teachers = User::query()
            ->whereIn('id', Course::query()->where('status', 'published')->select('teacher_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = CourseCategory::query()
            ->where('is_visible', true)
            ->whereHas('posts', fn ($posts) => $posts->published()->whereHas('courses', fn ($relatedCourses) => $relatedCourses->where('status', 'published')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('courses.index', compact('courses', 'teachers', 'categories'));
    }

    public function myCourses(): View
    {
        $user = Auth::user();
        abort_unless($user?->isStudent(), 403);

        $enrollments = \App\Models\Enrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with(['course.teacher', 'course.lessons'])
            ->latest()
            ->get();

        $lessonIds = $enrollments->flatMap(fn ($enrollment) => $enrollment->course->lessons->pluck('id'))->unique();
        $completedByLesson = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('completed_at', 'lesson_id');

        $enrollments->each(function ($enrollment) use ($completedByLesson) {
            $lessons = $enrollment->course->lessons;
            $enrollment->completed_lessons_count = $lessons->filter(
                fn ($lesson) => $completedByLesson->has($lesson->id)
            )->count();
            $enrollment->total_lessons_count = $lessons->count();
            $enrollment->progress_percent = $lessons->isEmpty()
                ? 0
                : (int) round(($enrollment->completed_lessons_count / $lessons->count()) * 100);
        });

        return view('student.courses.index', compact('enrollments'));
    }

    public function adminIndex(): View
    {
        $courses = Course::with('teacher')->latest()->get();
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.courses.index', compact('courses', 'teachers'));
    }

    public function create(): View
    {
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.courses.form', ['course' => null, 'teachers' => $teachers]);
    }

    public function edit(Course $course): View
    {
        $teachers = User::where('role', 'teacher')->get();

        return view('admin.courses.form', ['course' => $course, 'teachers' => $teachers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'teacher_id' => ['required', 'exists:users,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:published,draft,archived'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('courses', 'public');
        }

        Course::create($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Course created successfully.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'teacher_id' => ['required', 'exists:users,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:published,draft,archived'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('courses', 'public');
            if ($course->image_path) Storage::disk('public')->delete($course->image_path);
            $validated['image_path'] = $newImagePath;
        }
        $course->update($validated);

        return redirect()->route('admin.courses.index')->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->load('lessons');
        $videoPaths = $course->lessons->pluck('video_path')->filter()->all();
        $thumbnailPaths = $course->lessons->pluck('thumbnail_path')->filter()->all();
        $attachmentPaths = $course->lessons->pluck('attachment_path')->filter()->all();
        $imagePath = $course->image_path;
        $course->delete();
        if ($imagePath) Storage::disk('public')->delete($imagePath);
        foreach ($videoPaths as $path) Storage::disk('lesson_videos')->delete($path);
        foreach ($thumbnailPaths as $path) Storage::disk('public')->delete($path);
        foreach ($attachmentPaths as $path) Storage::disk('local')->delete($path);
        return redirect()->route('admin.courses.index')->with('success', 'Course deleted successfully.');
    }

    public function show(Request $request, Course $course): View
    {
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);

        PageView::record($request, 'course', 'course', $course->id);
        $course->views_count = PageView::where('view_type', 'course')->where('viewable_id', $course->id)->count();
        $comments = $course->comments()->with('author')->latest()->paginate(10, ['*'], 'comments_page');
        $course->load('teacher', 'enrollments.user', 'lessons', 'curriculumItems.post.categories', 'curriculumItems.post.thumbnail');

        $isWishlisted = $user?->isStudent() && DB::table('course_wishlists')
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        $enrollment = null;
        if (Auth::check()) {
            $enrollment = $course->enrollments()->where('user_id', Auth::id())->first();
        }

        $payment = Auth::check() && Auth::user()->isStudent()
            ? $course->payments()->where('user_id', Auth::id())->latest()->first()
            : null;
        $paymentMethods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false));
        $isEnrolled = $enrollment && in_array($enrollment->status, ['active', 'completed'], true);
        $canViewLessons = $canManageCourse || $isEnrolled;
        $lessonProgress = $isEnrolled && $user?->isStudent()
            ? LessonProgress::where('user_id', $user->id)
                ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
                ->pluck('completed_at', 'lesson_id')
            : collect();
        $batches = $course->batches()
            ->where('status', 'open')
            ->with('branch')
            ->withCount('enrollments')
            ->orderBy('starts_at')
            ->get();

        return view('courses.show', compact('course', 'enrollment', 'payment', 'paymentMethods', 'batches', 'canViewLessons', 'lessonProgress', 'isWishlisted', 'comments'));
    }

    public function streamLessonVideo(Course $course, Lesson $lesson): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($lesson->course_id === $course->id && $lesson->video_path, 404);
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);
        $isEnrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $canManageCourse || $isEnrolled, 403);
        abort_unless(Storage::disk('lesson_videos')->exists($lesson->video_path), 404);
        $response = response()->file(Storage::disk('lesson_videos')->path($lesson->video_path), [
            'Content-Type' => Storage::disk('lesson_videos')->mimeType($lesson->video_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ]);

        // BinaryFileResponse may mark file responses as public during preparation.
        // Re-apply private/no-store after constructing it so protected lesson media
        // cannot be stored by shared caches or intermediaries.
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public function showLesson(Course $course, Lesson $lesson): Response
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);
        $isEnrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $canManageCourse || $isEnrolled, 403);

        $videoEmbedUrl = null;
        if ($lesson->video_url) {
            $host = strtolower((string) parse_url($lesson->video_url, PHP_URL_HOST));
            $path = trim((string) parse_url($lesson->video_url, PHP_URL_PATH), '/');
            if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
                $query = [];
                parse_str((string) parse_url($lesson->video_url, PHP_URL_QUERY), $query);
                $videoId = $host === 'youtu.be' ? explode('/', $path)[0] : ($query['v'] ?? basename($path));
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $videoId)) {
                    $videoEmbedUrl = 'https://www.youtube-nocookie.com/embed/' . $videoId;
                }
            } elseif (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
                $videoId = basename($path);
                if (preg_match('/^[0-9]+$/', $videoId)) {
                    $videoEmbedUrl = 'https://player.vimeo.com/video/' . $videoId;
                }
            }
        }
        $privateVideoUrl = $lesson->video_path ? route('courses.lessons.media', [$course, $lesson, 'video']) : null;
        $privateAttachmentUrl = $lesson->attachment_path ? route('courses.lessons.media', [$course, $lesson, 'attachment']) : null;
        $isCompleted = $user && $user->isStudent()
            ? LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists()
            : false;

        return response()->view('student.lessons.show', compact('course', 'lesson', 'videoEmbedUrl', 'privateVideoUrl', 'privateAttachmentUrl', 'isCompleted'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'same-origin');
    }

    public function completeLesson(Course $course, Lesson $lesson): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isStudent() && $course->status === 'published' && $lesson->course_id === $course->id, 403);
        abort_unless($course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists(), 404);

        LessonProgress::firstOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id],
            ['completed_at' => now()]
        );

        return back()->with('success', 'Lesson progress saved.');
    }

    public function storeLesson(Course $course, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'order' => ['required', 'integer', 'min:1'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,m4v,mov,webm', 'max:524288'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
        $validated['is_preview'] = $request->boolean('is_preview');
        unset($validated['video_file'], $validated['thumbnail'], $validated['attachment_file']);
        $videoPath = $thumbnailPath = null;
        if ($request->hasFile('video_file')) {
            $videoPath = $request->file('video_file')->store('', 'lesson_videos');
            $validated['video_path'] = $videoPath;
            $validated['video_url'] = null;
        }
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('course-thumbnails', 'public');
            $validated['thumbnail_path'] = $thumbnailPath;
        }
        $attachmentPath = null;
        try {
            $lesson = $course->lessons()->create($validated);
            if ($request->hasFile('attachment_file')) {
                $file = $request->file('attachment_file');
                $attachmentPath = $file->store("course-media/{$course->id}/{$lesson->id}", 'local');
                $lesson->update([
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => basename($file->getClientOriginalName()),
                    'attachment_mime' => $file->getMimeType(),
                ]);
            }
        } catch (\Throwable $exception) {
            if (isset($lesson)) $lesson->delete();
            if ($videoPath) Storage::disk('lesson_videos')->delete($videoPath);
            if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
            if ($attachmentPath) Storage::disk('local')->delete($attachmentPath);
            throw $exception;
        }

        return $user->isTeacher()
            ? redirect()->route('teacher.courses.lessons.index', $course)->with('success', 'Lesson added successfully.')
            : redirect()->route('admin.courses.index')->with('success', 'Lesson added successfully.');
    }


    public function teacherLessons(Course $course): View
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        $course->load('lessons');
        $students = $course->enrollments()->whereIn('status', ['active', 'completed'])->with('user')->get();
        $lessonIds = $course->lessons->pluck('id');
        $progress = LessonProgress::query()->whereIn('lesson_id', $lessonIds)
            ->whereIn('user_id', $students->pluck('user_id'))->get()->groupBy('user_id');
        $students->each(function ($enrollment) use ($progress, $course) {
            $done = ($progress->get($enrollment->user_id) ?? collect())->count();
            $total = $course->lessons->count();
            $enrollment->progress_percent = $total ? (int) round($done / $total * 100) : 0;
            $enrollment->completed_lessons_count = $done;
        });
        return view('teacher.courses.lessons', compact('course', 'students'));
    }

    public function updateLesson(Course $course, Lesson $lesson, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($lesson->course_id === $course->id, 404);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'order' => ['required', 'integer', 'min:1'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'video_file' => ['nullable', 'file', 'mimes:mp4,m4v,mov,webm', 'max:524288'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'remove_attachment' => ['sometimes', 'boolean'],
            'remove_video' => ['sometimes', 'boolean'],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
        $validated['is_preview'] = $request->boolean('is_preview');
        unset($validated['video_file'], $validated['thumbnail'], $validated['attachment_file'], $validated['remove_attachment'], $validated['remove_video'], $validated['remove_thumbnail']);
        $newVideoPath = $newThumbnailPath = $oldVideoPath = $oldThumbnailPath = null;
        if ($request->hasFile('video_file')) {
            $newVideoPath = $request->file('video_file')->store('', 'lesson_videos');
            $validated['video_path'] = $newVideoPath;
            $validated['video_url'] = null;
            $oldVideoPath = $lesson->video_path;
        } elseif ($request->boolean('remove_video')) {
            $validated['video_path'] = null;
            $validated['video_url'] = null;
            $oldVideoPath = $lesson->video_path;
        } elseif ($request->filled('video_url')) {
            $validated['video_path'] = null;
            $oldVideoPath = $lesson->video_path;
        }
        if ($request->hasFile('thumbnail')) {
            $newThumbnailPath = $request->file('thumbnail')->store('course-thumbnails', 'public');
            $validated['thumbnail_path'] = $newThumbnailPath;
            $oldThumbnailPath = $lesson->thumbnail_path;
        } elseif ($request->boolean('remove_thumbnail')) {
            $validated['thumbnail_path'] = null;
            $oldThumbnailPath = $lesson->thumbnail_path;
        }
        $newAttachmentPath = $oldAttachmentPath = null;
        if ($request->hasFile('attachment_file')) {
            $file = $request->file('attachment_file');
            $newAttachmentPath = $file->store("course-media/{$course->id}/{$lesson->id}", 'local');
            $oldAttachmentPath = $lesson->attachment_path;
            $validated['attachment_path'] = $newAttachmentPath;
            $validated['attachment_name'] = basename($file->getClientOriginalName());
            $validated['attachment_mime'] = $file->getMimeType();
        } elseif ($request->boolean('remove_attachment')) {
            $oldAttachmentPath = $lesson->attachment_path;
            $validated['attachment_path'] = null;
            $validated['attachment_name'] = null;
            $validated['attachment_mime'] = null;
        }
        try {
            $lesson->update($validated);
        } catch (\Throwable $exception) {
            if ($newVideoPath) Storage::disk('lesson_videos')->delete($newVideoPath);
            if ($newThumbnailPath) Storage::disk('public')->delete($newThumbnailPath);
            if ($newAttachmentPath) Storage::disk('local')->delete($newAttachmentPath);
            throw $exception;
        }
        if ($oldVideoPath) Storage::disk('lesson_videos')->delete($oldVideoPath);
        if ($oldThumbnailPath) Storage::disk('public')->delete($oldThumbnailPath);
        if ($oldAttachmentPath) Storage::disk('local')->delete($oldAttachmentPath);
        return back()->with('success', 'Lesson updated successfully.');
    }

    public function destroyLesson(Course $course, Lesson $lesson): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($lesson->course_id === $course->id, 404);
        $videoPath = $lesson->video_path;
        $thumbnailPath = $lesson->thumbnail_path;
        $attachmentPath = $lesson->attachment_path;
        $lesson->delete();
        if ($videoPath) Storage::disk('lesson_videos')->delete($videoPath);
        if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
        if ($attachmentPath) Storage::disk('local')->delete($attachmentPath);
        return back()->with('success', 'Lesson deleted successfully.');
    }

    public function complete(Course $course, Request $request): RedirectResponse
    {
        $user = Auth::user();
        $studentId = (int) ($request->input('student_id') ?? $user->id);

        if (! $user || ($user->isStudent() && $studentId !== $user->id)) {
            abort(403);
        }

        if ($user->isTeacher() && $course->teacher_id !== $user->id) {
            abort(403);
        }

        abort_unless($user->isStudent() || $user->isAdmin() || $user->isTeacher(), 403);

        $enrollment = $course->enrollments()->where('user_id', $studentId)->where('status', 'active')->firstOrFail();
        $lessonCount = $course->lessons()->count();
        $completedLessonCount = LessonProgress::where('user_id', $studentId)
            ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
            ->count();

        if ($lessonCount === 0 || $completedLessonCount < $lessonCount) {
            return back()->with('error', 'Complete every lesson before completing this course.');
        }
        $enrollment->status = 'completed';
        $enrollment->completed_at = now();
        $enrollment->save();

        Certificate::firstOrCreate(
            [
                'user_id' => $studentId,
                'course_id' => $course->id,
            ],
            [
                'certificate_number' => 'LMS-' . strtoupper(Str::random(8)),
                'status' => 'issued',
                'issued_at' => now(),
            ]
        );

        return back()->with('success', 'Course marked complete and certificate issued.');
    }
}
