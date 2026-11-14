<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorAssignmentController extends Controller
{
    public function batches(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $batches = Batch::query()
            ->where('teacher_id', $teacher->id)
            ->with('course:id,title,slug')
            ->withCount('assignments')
            ->orderByDesc('starts_at')
            ->paginate(50);

        return response()->json([
            'data' => $batches->getCollection()->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'title' => $batch->title,
                'status' => $batch->status,
                'course' => $batch->course?->title,
                'course_slug' => $batch->course?->slug,
                'assignments_count' => $batch->assignments_count,
            ]),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    public function showBatch(Request $request, Batch $batch): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $batch->teacher_id === (int) $teacher->id, 404);

        $validated = $request->validate([
            'status' => ['nullable', 'in:active,completed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $enrollments = $batch->enrollments()
            ->whereIn('status', $validated['status'] ? [$validated['status']] : ['active', 'completed'])
            ->with('user:id,name,email,phone_number')
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 30);

        return response()->json([
            'batch' => [
                'id' => $batch->id,
                'title' => $batch->title,
                'status' => $batch->status,
                'starts_at' => $batch->starts_at?->toIso8601String(),
                'ends_at' => $batch->ends_at?->toIso8601String(),
                'course' => $batch->course()->value('title'),
                'capacity' => $batch->capacity,
                'fee' => (float) $batch->fee,
                'enrolled_count' => $batch->enrollments()->whereIn('status', ['active', 'completed'])->count(),
            ],
            'data' => $enrollments->getCollection()->map(fn ($enrollment) => [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->created_at?->toIso8601String(),
                'student' => [
                    'id' => $enrollment->user?->id,
                    'name' => $enrollment->user?->name,
                    'email' => $enrollment->user?->email,
                    'phone_number' => $enrollment->user?->phone_number,
                ],
            ]),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request);
        $assignments = Assignment::query()
            ->where('teacher_id', $teacher->id)
            ->with('batch.course:id,title')
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $assignments->getCollection()->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'batch_id' => $assignment->batch_id,
                'batch' => $assignment->batch?->title,
                'course' => $assignment->batch?->course?->title,
                'title' => $assignment->title,
                'instructions' => $assignment->instructions,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'allow_late' => (bool) $assignment->allow_late,
                'max_marks' => (int) $assignment->max_marks,
                'status' => $assignment->status,
                'submissions_count' => $assignment->submissions_count,
            ]),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    public function store(Request $request, Batch $batch): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $batch->teacher_id === (int) $teacher->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['required', 'string', 'max:10000'],
            'due_at' => ['required', 'date', 'after:now'],
            'allow_late' => ['sometimes', 'boolean'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $assignment = $batch->assignments()->create([
            ...$validated,
            'teacher_id' => $teacher->id,
            'allow_late' => (bool) ($validated['allow_late'] ?? false),
            'status' => 'published',
        ]);

        return response()->json([
            'message' => 'Assignment published successfully.',
            'assignment' => [
                'id' => $assignment->id,
                'batch_id' => $assignment->batch_id,
                'title' => $assignment->title,
                'due_at' => $assignment->due_at?->toIso8601String(),
                'max_marks' => $assignment->max_marks,
                'status' => $assignment->status,
            ],
        ], 201);
    }

    public function submissions(Request $request, Assignment $assignment): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $assignment->teacher_id === (int) $teacher->id, 404);

        $submissions = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->with('student:id,name,email')
            ->orderByDesc('submitted_at')
            ->paginate(50);

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'max_marks' => (int) $assignment->max_marks,
                'due_at' => $assignment->due_at?->toIso8601String(),
            ],
            'data' => $submissions->getCollection()->map(fn (AssignmentSubmission $submission) => [
                'id' => $submission->id,
                'student' => [
                    'id' => $submission->student?->id,
                    'name' => $submission->student?->name,
                    'email' => $submission->student?->email,
                ],
                'answer' => $submission->answer,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'score' => $submission->score,
                'feedback' => $submission->feedback,
            ]),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    public function grade(Request $request, Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $assignment->teacher_id === (int) $teacher->id, 404);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_marks],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $graded = DB::transaction(function () use ($assignment, $submission, $validated, $teacher): AssignmentSubmission {
            $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless((int) $lockedAssignment->teacher_id === (int) $teacher->id, 404);
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless((int) $lockedSubmission->assignment_id === (int) $lockedAssignment->id, 404);
            abort_unless($lockedSubmission->status === 'submitted' || $lockedSubmission->status === 'graded', 409);

            $lockedSubmission->update([
                'score' => $validated['score'],
                'feedback' => $validated['feedback'] ?? null,
                'graded_by' => $teacher->id,
                'status' => 'graded',
            ]);

            return $lockedSubmission->fresh();
        });

        return response()->json([
            'message' => 'Submission graded successfully.',
            'submission' => [
                'id' => $graded->id,
                'status' => $graded->status,
                'score' => $graded->score,
                'feedback' => $graded->feedback,
            ],
        ]);
    }

    private function teacher(Request $request): \App\Models\User
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);

        return $teacher;
    }
}
