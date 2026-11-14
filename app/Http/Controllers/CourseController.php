<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\PageView;
use App\Models\QuestionSetAttempt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'teacher' => ['nullable', 'integer', 'min:1'],
            'category' => ['nullable', 'integer', 'min:1'],
            'level' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:40'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'gte:min_price'],
            'sort' => ['nullable', 'in:latest,oldest,price_low,price_high,title'],
        ]);

        $query = Course::query()
            ->with([
                'teacher',
                'categories',
                'curriculumItems' => fn ($items) => $items
                    ->whereHas('post', fn ($posts) => $posts->published())
                    ->with('post.categories'),
            ])
            ->withCount(['lessons', 'curriculumItems', 'enrollments as students_count' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'completed'])])
            ->where('status', 'published');

        if (! empty($validated['q'])) {
            $term = trim($validated['q']);
            $query->where(function ($builder) use ($term): void {
                $builder->where('title', 'like', '%'.$term.'%')
                    ->orWhere('short_description', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', '%'.$term.'%'));
                if (mb_strlen($term) <= 80) {
                    $builder->orWhereJsonContains('tags', $term);
                }
            });
        }

        if (! empty($validated['teacher'])) {
            $query->where('teacher_id', $validated['teacher']);
        }
        if (! empty($validated['category'])) {
            $categoryId = (int) $validated['category'];
            $query->where(function ($builder) use ($categoryId): void {
                $builder->whereHas('categories', fn ($categories) => $categories
                    ->whereKey($categoryId)->where('is_visible', true))
                    ->orWhereHas('curriculumItems.post', fn ($posts) => $posts
                        ->published()
                        ->whereHas('categories', fn ($categories) => $categories->whereKey($categoryId)->where('is_visible', true)));
            });
        }
        if (! empty($validated['level'])) {
            $query->where('level', $validated['level']);
        }
        if (! empty($validated['language'])) {
            $query->where('language', $validated['language']);
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
            ->where(function ($builder): void {
                $builder->whereHas('courses', fn ($courses) => $courses->where('status', 'published'))
                    ->orWhereHas('posts', fn ($posts) => $posts->published()
                        ->whereHas('courses', fn ($relatedCourses) => $relatedCourses->where('status', 'published')));
            })
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
            ->with([
                'course.teacher',
                'course.lessons' => fn ($lessons) => $lessons->orderBy('order')->orderBy('id'),
            ])
            ->latest()
            ->get();

        $lessonIds = $enrollments->flatMap(fn ($enrollment) => $enrollment->course->lessons->pluck('id'))->unique();
        $completedByLesson = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->pluck('completed_at', 'lesson_id');
        $lastViewedByCourse = LessonProgress::query()
            ->with('lesson:id,course_id,title,order')
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->whereNotNull('last_viewed_at')
            ->orderByDesc('last_viewed_at')
            ->get()
            ->filter(fn ($progress) => $progress->lesson)
            ->groupBy(fn ($progress) => $progress->lesson->course_id);

        $enrollments->each(function ($enrollment) use ($completedByLesson, $lastViewedByCourse) {
            $lessons = $enrollment->course->lessons;
            $enrollment->completed_lessons_count = $lessons->filter(
                fn ($lesson) => $completedByLesson->has($lesson->id)
            )->count();
            $enrollment->total_lessons_count = $lessons->count();
            $enrollment->progress_percent = $lessons->isEmpty()
                ? 0
                : (int) round(($enrollment->completed_lessons_count / $lessons->count()) * 100);

            $lastViewedLesson = ($lastViewedByCourse->get($enrollment->course_id) ?? collect())->first()?->lesson;
            $nextIncompleteLesson = $lessons->first(
                fn ($lesson) => ! $completedByLesson->has($lesson->id)
            );
            $enrollment->resume_lesson = $lastViewedLesson && ! $completedByLesson->has($lastViewedLesson->id)
                ? $lastViewedLesson
                : ($nextIncompleteLesson ?? $lastViewedLesson ?? $lessons->first());
        });

        return view('student.courses.index', compact('enrollments'));
    }

    public function adminIndex(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['published', 'draft', 'archived'])],
            'teacher_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Course::query()
            ->with(['teacher', 'categories'])
            ->withCount(['enrollments', 'lessons', 'curriculumItems'])
            ->latest();

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('slug', 'like', '%'.$term.'%'));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        $courses = $query->paginate(20)->withQueryString();
        $teachers = User::query()->where('role', 'teacher')->orderBy('name')->get(['id', 'name']);

        return view('admin.courses.index', compact('courses', 'teachers', 'filters'));
    }

    public function create(): View
    {
        $teachers = User::query()->where('role', 'teacher')->orderBy('name')->get();
        $categories = CourseCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.courses.form', ['course' => null, 'teachers' => $teachers, 'categories' => $categories]);
    }

    public function edit(Course $course): View
    {
        $teachers = User::query()->where('role', 'teacher')->orderBy('name')->get();
        $categories = CourseCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.courses.form', [
            'course' => $course->load('categories'),
            'teachers' => $teachers,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->courseRules());
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['image']);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        $validated['short_description'] = ($validated['short_description'] ?? '')
            ?: Str::limit(trim(strip_tags($validated['description'])), 240);
        $validated['slug'] = $this->uniqueCourseSlug($validated['title']);

        $newImagePath = $request->file('image')?->store('courses', 'public');
        if ($newImagePath) {
            $validated['image_path'] = $newImagePath;
        }

        try {
            DB::transaction(function () use ($validated, $categoryIds): void {
                $course = Course::create($validated);
                $course->categories()->sync($categoryIds);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        return redirect()->route('admin.courses.index')->with('success', __('Course created successfully.'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate($this->courseRules($course));
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['image']);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        $validated['short_description'] = ($validated['short_description'] ?? '')
            ?: Str::limit(trim(strip_tags($validated['description'])), 240);
        $validated['slug'] = $this->uniqueCourseSlug($validated['title'], $course->id);

        $oldImagePath = $course->image_path;
        $newImagePath = $request->file('image')?->store('courses', 'public');
        if ($newImagePath) {
            $validated['image_path'] = $newImagePath;
        }

        try {
            DB::transaction(function () use ($course, $validated, $categoryIds): void {
                $locked = Course::query()->lockForUpdate()->findOrFail($course->id);
                $locked->update($validated);
                $locked->categories()->sync($categoryIds);
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }
            throw $exception;
        }

        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('admin.courses.index')->with('success', __('Course updated successfully.'));
    }

    public function destroy(Course $course): RedirectResponse
    {
        if ($course->combos()->exists()) {
            return back()->with('error', __('Remove this course from every combo before deleting it.'));
        }

        $hasHistory = $course->enrollments()->exists()
            || $course->payments()->exists()
            || $course->certificates()->exists()
            || $course->lessons()->exists()
            || $course->batches()->exists()
            || $course->curriculumItems()->exists()
            || $course->questionSets()->exists()
            || DB::table('question_set_attempts')->where('course_id', $course->id)->exists();

        if ($hasHistory) {
            $course->update(['status' => 'archived']);

            return redirect()->route('admin.courses.index')
                ->with('success', __('Course archived because learning, enrollment, quiz or payment history exists; records were preserved.'));
        }

        $course->load('lessons');
        $videoPaths = $course->lessons->pluck('video_path')->filter()->all();
        $thumbnailPaths = $course->lessons->pluck('thumbnail_path')->filter()->all();
        $attachmentPaths = $course->lessons->pluck('attachment_path')->filter()->all();
        $imagePath = $course->image_path;

        DB::transaction(fn () => $course->delete());

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        foreach ($videoPaths as $path) {
            Storage::disk('lesson_videos')->delete($path);
        }
        foreach ($thumbnailPaths as $path) {
            Storage::disk('public')->delete($path);
        }
        foreach ($attachmentPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('admin.courses.index')->with('success', __('Course deleted successfully.'));
    }

    public function show(Request $request, Course $course): View
    {
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);

        PageView::record($request, 'course', 'course', $course->id);
        $course->views_count = PageView::where('view_type', 'course')->where('viewable_id', $course->id)->count();
        $comments = $course->comments()->with('author')->latest()->paginate(10, ['*'], 'comments_page');
        $course->load('teacher', 'categories', 'enrollments.user', 'lessons', 'curriculumItems.post.categories', 'curriculumItems.post.thumbnail');
        $course->load(['questionSets' => fn ($sets) => $sets
            ->when(! $canManageCourse, fn ($query) => $query->published())
            ->withCount('questions')]);
        if ($user?->isStudent()) {
            $attemptsBySet = QuestionSetAttempt::query()
                ->where('course_id', $course->id)
                ->where('student_id', $user->id)
                ->latest('id')
                ->get()
                ->groupBy('question_set_id');

            $course->questionSets->each(function ($set) use ($attemptsBySet): void {
                $attempts = $attemptsBySet->get($set->id, collect());
                $set->student_attempt_count = $attempts->count();
                $set->student_active_attempt = $attempts->firstWhere('status', 'in_progress');
            });
        }

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
                ->whereNotNull('completed_at')
                ->whereHas('lesson', fn ($query) => $query->where('course_id', $course->id))
                ->pluck('completed_at', 'lesson_id')
            : collect();
        $batches = $course->batches()
            ->where('status', 'open')
            ->with('branch')
            ->withCount('enrollments')
            ->orderBy('starts_at')
            ->get();
        $demoLesson = $course->curriculumItems->first(function ($curriculumItem): bool {
            $post = $curriculumItem->post;

            return $curriculumItem->is_preview
                && $post
                && $post->status === 'published'
                && $post->published_at?->lte(now())
                && $post->video_embed_url;
        });
        $studentCount = $course->enrollments->whereIn('status', ['active', 'completed'])->count();
        $publishedPostCount = $course->curriculumItems->filter(function ($curriculumItem): bool {
            $post = $curriculumItem->post;

            return $post && $post->status === 'published' && $post->published_at?->lte(now());
        })->count();
        $courseContentCount = $publishedPostCount + $course->lessons->count();

        return view('courses.show', compact(
            'course', 'enrollment', 'payment', 'paymentMethods', 'batches',
            'canViewLessons', 'canManageCourse', 'isEnrolled', 'lessonProgress', 'isWishlisted', 'comments',
            'demoLesson', 'studentCount', 'courseContentCount'
        ));
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
        if ($user?->isStudent() && $isEnrolled) {
            LessonProgress::updateOrCreate(
                ['lesson_id' => $lesson->id, 'user_id' => $user->id],
                ['last_viewed_at' => now()]
            );
        }

        $isCompleted = $user && $user->isStudent()
            ? LessonProgress::where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->whereNotNull('completed_at')
                ->exists()
            : false;
        $courseLessons = $course->lessons()->orderBy('order')->orderBy('id')->get(['id', 'course_id', 'title', 'order', 'is_preview', 'duration_minutes']);
        $availableLessons = $courseLessons
            ->filter(fn ($item) => $canManageCourse || $isEnrolled || $item->is_preview)
            ->values();
        $currentIndex = $availableLessons->search(fn ($item) => $item->id === $lesson->id);
        $previousLesson = $currentIndex !== false && $currentIndex > 0 ? $availableLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex !== false ? ($availableLessons[$currentIndex + 1] ?? null) : null;
        $completedLessonIds = $user?->isStudent() && $isEnrolled
            ? LessonProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $courseLessons->pluck('id'))
                ->whereNotNull('completed_at')
                ->pluck('lesson_id')
            : collect();
        $completedLessonsCount = $completedLessonIds->count();
        $totalLessonsCount = $courseLessons->count();
        $progressPercent = $totalLessonsCount > 0
            ? (int) round(($completedLessonsCount / $totalLessonsCount) * 100)
            : 0;

        return response()->view('student.lessons.show', compact(
            'course', 'lesson', 'videoEmbedUrl', 'privateVideoUrl', 'privateAttachmentUrl',
            'isCompleted', 'isEnrolled', 'previousLesson', 'nextLesson',
            'completedLessonsCount', 'totalLessonsCount', 'progressPercent'
        ))
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

        $progress = LessonProgress::firstOrCreate(
            ['lesson_id' => $lesson->id, 'user_id' => $user->id],
            ['completed_at' => now(), 'last_viewed_at' => now()]
        );

        if (! $progress->completed_at) {
            $progress->forceFill([
                'completed_at' => now(),
                'last_viewed_at' => $progress->last_viewed_at ?? now(),
            ])->save();
        }

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
            ->whereIn('user_id', $students->pluck('user_id'))
            ->whereNotNull('completed_at')
            ->get()->groupBy('user_id');
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
            ->whereNotNull('completed_at')
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

    private function courseRules(?Course $course = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:100000'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher'))],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'status' => ['required', Rule::in(['published', 'draft', 'archived'])],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('course_categories', 'id')],
            'tags' => ['nullable', 'string', 'max:2000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'learning_outcomes' => ['nullable', 'string', 'max:10000'],
            'requirements' => ['nullable', 'string', 'max:10000'],
            'level' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:40'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    private function normalizeTags(?string $tags): array
    {
        return collect(explode(',', (string) $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->take(30)
            ->values()
            ->all();
    }

    private function uniqueCourseSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (Course::query()->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

}
