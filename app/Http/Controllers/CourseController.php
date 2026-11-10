<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\BlogPost;
use App\Models\CourseSection;
use App\Models\CourseCategory;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:150'],
            'level' => ['nullable', 'string', 'max:40'],
            'language' => ['nullable', 'string', 'max:40'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sort' => ['nullable', 'in:latest,price_low,price_high,title'],
        ]);

        $query = Course::with(['teacher', 'categories'])->where('status', 'published');
        if (!empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('short_description', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhereJsonContains('tags', $term));
        }
        if (!empty($filters['category'])) {
            $query->whereHas('categories', fn ($builder) => $builder
                ->where('slug', $filters['category'])->where('is_active', true));
        }
        if (!empty($filters['level'])) $query->where('level', $filters['level']);
        if (!empty($filters['language'])) $query->where('language', $filters['language']);
        if (isset($filters['min_price'])) $query->where('price', '>=', $filters['min_price']);
        if (isset($filters['max_price'])) $query->where('price', '<=', $filters['max_price']);

        match ($filters['sort'] ?? 'latest') {
            'price_low' => $query->orderBy('price')->orderByDesc('created_at'),
            'price_high' => $query->orderByDesc('price')->orderByDesc('created_at'),
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $courses = $query->paginate(12)->withQueryString();
        $categories = CourseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('courses.index', compact('courses', 'categories', 'filters'));
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

    public function adminIndex(Request $request): View
    {
        $query = Course::with(['teacher', 'categories', 'lessons'])
            ->withCount(['enrollments', 'lessons'])
            ->latest();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'));
        }
        if (in_array($request->query('status'), ['published', 'draft', 'archived'], true)) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('teacher_id') && ctype_digit((string) $request->query('teacher_id'))) {
            $query->where('teacher_id', (int) $request->query('teacher_id'));
        }

        $courses = $query->paginate(20)->withQueryString();
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();

        return view('admin.courses.index', compact('courses', 'teachers'));
    }

    public function create(): View
    {
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();
        $categories = CourseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.courses.form', ['course' => null, 'teachers' => $teachers, 'categories' => $categories]);
    }

    public function edit(Course $course): View
    {
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();
        $categories = CourseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.courses.form', ['course' => $course->load('categories'), 'teachers' => $teachers, 'categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->courseRules());
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['image']);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        $validated['short_description'] = ($validated['short_description'] ?? '') ?: Str::limit(trim(strip_tags($validated['description'])), 240);
        $validated['slug'] = $this->uniqueCourseSlug($validated['title']);

        $newImagePath = $request->file('image')?->store('courses', 'public');
        if ($newImagePath) $validated['image_path'] = $newImagePath;

        try {
            $course = DB::transaction(function () use ($validated, $categoryIds) {
                $course = Course::create($validated);
                $course->categories()->sync($categoryIds);
                return $course;
            });
        } catch (\Throwable $exception) {
            if ($newImagePath) Storage::disk('public')->delete($newImagePath);
            throw $exception;
        }

        return redirect()->route('admin.courses.index')->with('success', 'Course created successfully.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate($this->courseRules($course));
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids'], $validated['image']);
        $validated['tags'] = $this->normalizeTags($request->input('tags'));
        $validated['short_description'] = ($validated['short_description'] ?? '') ?: Str::limit(trim(strip_tags($validated['description'])), 240);
        $validated['slug'] = $this->uniqueCourseSlug($validated['title'], $course->id);

        $oldImagePath = $course->image_path;
        $newImagePath = $request->file('image')?->store('courses', 'public');
        if ($newImagePath) $validated['image_path'] = $newImagePath;

        try {
            DB::transaction(function () use ($course, $validated, $categoryIds) {
                $course->update($validated);
                $course->categories()->sync($categoryIds);
            });
        } catch (\Throwable $exception) {
            if ($newImagePath) Storage::disk('public')->delete($newImagePath);
            throw $exception;
        }

        // Delete the old image only after the database update and category sync commit.
        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return redirect()->route('admin.courses.index')->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $hasHistory = $course->enrollments()->exists()
            || $course->payments()->exists()
            || $course->certificates()->exists()
            || $course->lessons()->exists()
            || $course->sections()->exists()
            || $course->batches()->exists();

        if ($hasHistory) {
            $course->update(['status' => 'archived']);
            return redirect()->route('admin.courses.index')
                ->with('success', 'Course archived because learning or payment history exists; records were preserved.');
        }

        $imagePath = $course->image_path;
        DB::transaction(fn () => $course->delete());
        if ($imagePath) Storage::disk('public')->delete($imagePath);

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted successfully.');
    }

    private function courseRules(?Course $course = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:100000'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher'))],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'status' => ['required', 'in:published,draft,archived'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('course_categories', 'id')->where('is_active', true)],
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
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function show(Course $course): View
    {
        $user = Auth::user();
        $canManageCourse = $user && ($user->isAdmin() || ($user->isTeacher() && $course->teacher_id === $user->id));
        abort_unless($course->status === 'published' || $canManageCourse, 404);

        $course->load('teacher', 'categories', 'enrollments.user', 'lessons', 'sections.blogPosts');

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
            'section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'order' => ['required', 'integer', 'min:1'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);

        if (!empty($validated['section_id'])) abort_unless($course->sections()->whereKey($validated['section_id'])->exists(), 422);
        $validated['is_preview'] = $request->boolean('is_preview');
        $course->lessons()->create($validated);

        return $user->isTeacher()
            ? redirect()->route('teacher.courses.lessons.index', $course)->with('success', 'Lesson added successfully.')
            : redirect()->route('admin.courses.index')->with('success', 'Lesson added successfully.');
    }


    public function teacherLessons(Course $course): View
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        $course->load(['lessons.section', 'sections.lessons', 'sections.blogPosts']);
        $availablePosts = BlogPost::query()->when(!$user->isAdmin(), fn ($query) => $query->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now()))->orderByDesc('published_at')->limit(300)->get();
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
        return view('teacher.courses.lessons', compact('course', 'students', 'availablePosts'));
    }

    public function updateLesson(Course $course, Lesson $lesson, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($lesson->course_id === $course->id, 404);
        $validated = $request->validate([
            'section_id' => ['nullable', 'integer', 'exists:course_sections,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'order' => ['required', 'integer', 'min:1'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'is_preview' => ['sometimes', 'boolean'],
        ]);
        if (!empty($validated['section_id'])) abort_unless($course->sections()->whereKey($validated['section_id'])->exists(), 422);
        $validated['is_preview'] = $request->boolean('is_preview');
        $lesson->update($validated);
        return back()->with('success', 'Lesson updated successfully.');
    }

    public function destroyLesson(Course $course, Lesson $lesson): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($lesson->course_id === $course->id, 404);
        $lesson->delete();
        return back()->with('success', 'Lesson deleted successfully.');
    }


    public function storeSection(Course $course, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        $data = $request->validate([
            'title' => ['required','string','max:180'],
            'description' => ['nullable','string','max:2000'],
            'position' => ['required','integer','min:1','max:10000'],
            'status' => ['required','in:draft,published'],
        ]);
        $course->sections()->create($data);
        return back()->with('success','Course section created.');
    }

    public function updateSection(Course $course, \App\Models\CourseSection $section, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($section->course_id === $course->id, 404);
        $data = $request->validate([
            'title' => ['required','string','max:180'],
            'description' => ['nullable','string','max:2000'],
            'position' => ['required','integer','min:1','max:10000'],
            'status' => ['required','in:draft,published'],
        ]);
        $section->update($data);
        return back()->with('success','Course section updated.');
    }

    public function destroySection(Course $course, \App\Models\CourseSection $section): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($section->course_id === $course->id, 404);
        $section->delete();
        return back()->with('success','Section deleted; lessons remain in the course.');
    }


    public function attachPost(Course $course, CourseSection $section, Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($section->course_id === $course->id, 404);
        $data = $request->validate([
            'blog_post_id' => ['required','integer','exists:blog_posts,id'],
            'position' => ['nullable','integer','min:1','max:10000'],
        ]);
        $post = BlogPost::findOrFail($data['blog_post_id']);
        if (!$user->isAdmin()) {
            abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 403);
        }
        $section->blogPosts()->syncWithoutDetaching([
            $post->id => ['position'=>$data['position'] ?? ($section->blogPosts()->count()+1),'attached_by'=>$user->id],
        ]);
        return back()->with('success','Content post attached to this course module.');
    }

    public function detachPost(Course $course, CourseSection $section, BlogPost $post): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->isAdmin() || ($user?->isTeacher() && $course->teacher_id === $user->id), 403);
        abort_unless($section->course_id === $course->id, 404);
        $section->blogPosts()->detach($post->id);
        return back()->with('success','Content post removed from this module; the original post remains in the library.');
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
