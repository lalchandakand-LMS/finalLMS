<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $isInstructor = $user && $user->isTeacher() && (int) $course->teacher_id === (int) $user->id;
        abort_unless($course->status === 'published' || $isInstructor, 404);
        $enrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($course->status === 'published' || $isInstructor, 404);

        $course->load(['teacher:id,name', 'lessons']);
        $lessons = $course->lessons
            ->filter(fn (Lesson $lesson) => $enrolled || $isInstructor || $lesson->is_preview)
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
            'can_manage' => (bool) $isInstructor,
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
        abort_unless($lesson->course_id === $course->id, 404);
        $user = $request->user();
        $isInstructor = $user && $user->isTeacher() && (int) $course->teacher_id === (int) $user->id;
        abort_unless($course->status === 'published' || $isInstructor, 404);
        $enrolled = $user && $user->isStudent()
            && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['active', 'completed'])->exists();
        abort_unless($lesson->is_preview || $enrolled || $isInstructor, 403);

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

    public function completeLesson(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published' && $lesson->course_id === $course->id, 404);
        abort_unless($course->enrollments()
            ->where('user_id', $student->id)
            ->where('status', 'active')
            ->exists(), 403);

        $progress = LessonProgress::query()->firstOrCreate(
            ['user_id' => $student->id, 'lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        return response()->json([
            'completed' => true,
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ]);
    }

    public function teachingCourses(Request $request): JsonResponse
    {
        abort_unless($request->user()->isTeacher(), 403);
        $courses = $request->user()->courses()
            ->withCount(['enrollments as active_students_count' => fn ($query) => $query->whereIn('status', ['active', 'completed'])])
            ->with('teacher:id,name')
            ->whereIn('status', ['published', 'draft'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $courses->getCollection()->map(fn (Course $course) => [
                ...$this->coursePayload($course),
                'status' => $course->status,
                'students_count' => (int) $course->active_students_count,
            ]),
            'meta' => ['current_page' => $courses->currentPage(), 'last_page' => $courses->lastPage(), 'total' => $courses->total()],
        ]);
    }

    public function courseStudents(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->isTeacher() && (int) $course->teacher_id === (int) $request->user()->id, 403);
        $students = $course->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('user:id,name,email,phone_number')
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $students->getCollection()->map(fn ($enrollment) => [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'student' => [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'email' => $enrollment->user->email,
                    'phone_number' => $enrollment->user->phone_number,
                ],
            ]),
            'meta' => ['current_page' => $students->currentPage(), 'last_page' => $students->lastPage(), 'total' => $students->total()],
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
