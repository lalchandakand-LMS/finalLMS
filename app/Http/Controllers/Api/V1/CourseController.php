<?php

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Course;
use App\\Models\\Lesson;
use App\\Models\\LessonProgress;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:newest,oldest,price_low,price_high,title'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Course::query()->with('teacher:id,name')->where('status', 'published');
        if (! empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')
                ->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', '%'.$term.'%')));
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'price_low' => $query->orderBy('price'),
            'price_high' => $query->orderByDesc('price'),
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        $courses = $query->paginate($validated['per_page'] ?? 12)->withQueryString();

        return response()->json([
            'data' => $courses->getCollection()->map(fn (Course $course) => $this->coursePayload($course)),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $user = $request->user();
        $enrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($course->status === 'published', 404);

        $course->load(['teacher:id,name', 'lessons']);
        $lessons = $course->lessons
            ->filter(fn (Lesson $lesson) => $enrolled || $lesson->is_preview)
            ->values();

        return response()->json([
            'course' => $this->coursePayload($course),
            'lessons' => $lessons->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'duration_minutes' => $lesson->duration_minutes,
                'order' => $lesson->order,
                'is_preview' => (bool) $lesson->is_preview,
                'content_excerpt' => mb_substr(strip_tags($lesson->content), 0, 240),
            ]),
            'is_enrolled' => (bool) $enrolled,
        ]);
    }

    public function myCourses(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $enrollments = $request->user()->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->whereHas('course', fn ($query) => $query->where('status', 'published'))
            ->with(['course.teacher:id,name', 'course.lessons'])
            ->latest()
            ->paginate(20);

        $lessonIds = $enrollments->getCollection()->flatMap(fn ($enrollment) => $enrollment->course->lessons->pluck('id'))->unique();
        $completed = LessonProgress::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('completed_at', 'lesson_id');

        return response()->json([
            'data' => $enrollments->getCollection()->map(function ($enrollment) use ($completed) {
                $lessons = $enrollment->course->lessons;
                $done = $lessons->filter(fn ($lesson) => $completed->has($lesson->id))->count();

                return [
                    'enrollment_id' => $enrollment->id,
                    'status' => $enrollment->status,
                    'progress_percent' => $lessons->isEmpty() ? 0 : (int) round($done / $lessons->count() * 100),
                    'completed_lessons' => $done,
                    'total_lessons' => $lessons->count(),
                    'course' => $this->coursePayload($enrollment->course),
                ];
            }),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->course_id === $course->id && $course->status === 'published', 404);
        $user = $request->user();
        $enrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $enrolled, 403);

        $progress = $user?->isStudent()
            ? LessonProgress::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists()
            : false;

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'course_id' => $course->id,
                'title' => $lesson->title,
                'content' => $lesson->content,
                'video_url' => $lesson->video_url,
                'duration_minutes' => $lesson->duration_minutes,
                'order' => $lesson->order,
                'is_preview' => (bool) $lesson->is_preview,
                'is_completed' => (bool) $progress,
            ],
        ]);
    }

    private function coursePayload(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'price' => (float) $course->price,
            'image_url' => $course->image_path ? url('/storage/'.$course->image_path) : null,
            'instructor' => $course->teacher ? ['id' => $course->teacher->id, 'name' => $course->teacher->name] : null,
        ];
    }
}
