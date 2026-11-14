<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:upcoming,overdue,submitted,graded'],
        ]);

        $studentId = $request->user()->id;
        $query = Assignment::query()
            ->where('status', 'published')
            ->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                ->where('user_id', $studentId)
                ->where('status', 'active'))
            ->with(['batch.course:id,title,slug'])
            ->with(['submissions' => fn ($submissions) => $submissions->where('student_id', $studentId)])
            ->orderBy('due_at');

        match ($validated['status'] ?? null) {
            'upcoming' => $query->where('due_at', '>=', now()),
            'overdue' => $query->where('due_at', '<', now())->where('allow_late', false),
            'submitted' => $query->whereHas('submissions', fn ($submissions) => $submissions
                ->where('student_id', $studentId)->where('status', 'submitted')),
            'graded' => $query->whereHas('submissions', fn ($submissions) => $submissions
                ->where('student_id', $studentId)->where('status', 'graded')),
            default => null,
        };

        $assignments = $query->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'data' => $assignments->getCollection()->map(fn (Assignment $assignment) => $this->payload($assignment)),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:50000'],
        ]);

        $result = DB::transaction(function () use ($request, $assignment, $validated): array {
            $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);

            abort_unless(
                Enrollment::query()
                    ->where('batch_id', $locked->batch_id)
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'active')
                    ->exists(),
                403
            );

            if ($locked->status !== 'published' || ($locked->due_at->isPast() && ! $locked->allow_late)) {
                return ['state' => 'closed'];
            }

            $submission = AssignmentSubmission::query()
                ->where('assignment_id', $locked->id)
                ->where('student_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($submission && $submission->status !== 'graded') {
                return ['state' => 'already_submitted'];
            }

            $submission ??= new AssignmentSubmission([
                'assignment_id' => $locked->id,
                'student_id' => $request->user()->id,
            ]);
            $submission->fill([
                'answer' => $validated['answer'],
                'submitted_at' => now(),
                'status' => 'submitted',
                'score' => null,
                'feedback' => null,
                'graded_by' => null,
            ]);
            $submission->assignment_id = $locked->id;
            $submission->student_id = $request->user()->id;
            $submission->save();

            return ['state' => 'saved', 'submission' => $submission->fresh()];
        });

        if ($result['state'] === 'closed') {
            return response()->json(['message' => __('This assignment is closed for submissions.')], 409);
        }
        if ($result['state'] === 'already_submitted') {
            return response()->json(['message' => __('Your submission is already received and awaiting review.')], 409);
        }

        return response()->json([
            'message' => __('Assignment submitted successfully.'),
            'submission' => $this->submissionPayload($result['submission']),
        ], 201);
    }

    private function payload(Assignment $assignment): array
    {
        $submission = $assignment->submissions->first();

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'allow_late' => (bool) $assignment->allow_late,
            'max_marks' => $assignment->max_marks,
            'status' => $assignment->status,
            'is_overdue' => $assignment->due_at?->isPast() ?? false,
            'batch' => [
                'id' => $assignment->batch->id,
                'title' => $assignment->batch->title,
                'course' => $assignment->batch->course ? [
                    'id' => $assignment->batch->course->id,
                    'title' => $assignment->batch->course->title,
                    'slug' => $assignment->batch->course->slug,
                ] : null,
            ],
            'submission' => $submission ? $this->submissionPayload($submission) : null,
        ];
    }

    private function submissionPayload(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'answer' => $submission->answer,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'status' => $submission->status,
            'score' => $submission->score,
            'feedback' => $submission->feedback,
        ];
    }
}
