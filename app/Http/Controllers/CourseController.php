<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::with('teacher')->where('status', 'published')->latest()->get();

        return view('courses.index', compact('courses'));
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
        if ($course->image_path) {
            Storage::disk('public')->delete($course->image_path);
        }
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted successfully.');
    }

    public function show(Course $course): View
    {
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);

        $course->load('teacher', 'enrollments.user', 'lessons');

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

        return view('courses.show', compact('course', 'enrollment', 'payment', 'paymentMethods', 'batches', 'canViewLessons', 'lessonProgress'));
    }

    public function streamLessonVideo(Course $course, Lesson $lesson): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless($lesson->course_id === $course->id && $lesson->video_path, 404);
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        $isEnrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $canManageCourse || $isEnrolled, 403);
        abort_unless(Storage::disk('local')->exists($lesson->video_path), 404);

        return response()->file(Storage::disk('local')->path($lesson->video_path), [
            'Content-Type' => Storage::disk('local')->mimeType($lesson->video_path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function showLesson(Course $course, Lesson $lesson): View
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
        $isCompleted = $user && $user->isStudent()
            ? LessonProgress::where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists()
            : false;

        return view('student.lessons.show', compact('course', 'lesson', 'videoEmbedUrl', 'isCompleted'));
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
            'is_preview' => ['sometimes', 'boolean'],
        ]);

        $validated['is_preview'] = $request->boolean('is_preview');
        unset($validated['video_file'], $validated['thumbnail']);
        if ($request->hasFile('video_file')) {
            $validated['video_path'] = $request->file('video_file')->store('course-videos', 'local');
            $validated['video_url'] = null;
        }
        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }
        $course->lessons()->create($validated);

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
            'remove_video' => ['sometimes', 'boolean'],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
        $validated['is_preview'] = $request->boolean('is_preview');
        unset($validated['video_file'], $validated['thumbnail'], $validated['remove_video'], $validated['remove_thumbnail']);
        if ($request->hasFile('video_file')) {
            if ($lesson->video_path) Storage::disk('local')->delete($lesson->video_path);
            $validated['video_path'] = $request->file('video_file')->store('course-videos', 'public');
            $validated['video_url'] = null;
        } elseif ($request->boolean('remove_video')) {
            if ($lesson->video_path) Storage::disk('public')->delete($lesson->video_path);
            $validated['video_path'] = null;
            $validated['video_url'] = null;
        }
        if ($request->hasFile('thumbnail')) {
            if ($lesson->thumbnail_path) Storage::disk('public')->delete($lesson->thumbnail_path);
            $validated['thumbnail_path'] = $request->file('thumbnail')->store('course-thumbnails', 'public');
        } elseif ($request->boolean('remove_thumbnail')) {
            if ($lesson->thumbnail_path) Storage::disk('public')->delete($lesson->thumbnail_path);
            $validated['thumbnail_path'] = null;
        }
        $lesson->update($validated);
        return back()->with('success', 'Lesson updated successfully.');
    }

    public function destroyLesson(Course $course, Lesson $lesson): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($lesson->course_id === $course->id, 404);
        if ($lesson->video_path) Storage::disk('public')->delete($lesson->video_path);
        if ($lesson->thumbnail_path) Storage::disk('public')->delete($lesson->thumbnail_path);
        $lesson->delete();
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
