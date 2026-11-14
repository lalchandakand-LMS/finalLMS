<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:upcoming,overdue,submitted,graded'],
        ]);

        $query = Assignment::query()
            ->where('status', 'published')
            ->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                ->where('user_id', $student->id)
                ->where('status', 'active'))
            ->with(['batch.course:id,title', 'submissions' => fn ($submissions) => $submissions
                ->where('student_id', $student->id)])
            ->orderBy('due_at');

        match ($validated['status'] ?? null) {
            'upcoming' => $query->where('due_at', '>=', now()),
            'overdue' => $query->where('due_at', '<', now()),
            'submitted' => $query->whereHas('submissions', fn ($submissions) => $submissions
                ->where('student_id', $student->id)->where('status', 'submitted')),
            'graded' => $query->whereHas('submissions', fn ($submissions) => $submissions
                ->where('student_id', $student->id)->where('status', 'graded')),
            default => null,
        };

        $assignments = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $assignments->getCollection()->map(fn (Assignment $assignment) => $this->payload($assignment, true)),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
        ]);
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($assignment->status === 'published', 404);
        $this->authorizeEnrollment($student->id, $assignment);

        $assignment->load(['batch.course:id,title', 'submissions' => fn ($query) => $query
            ->where('student_id', $student->id)]);

        return response()->json(['assignment' => $this->payload($assignment)]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        $this->authorizeEnrollment($student->id, $assignment);

        $validated = $request->validate([
            'answer' => ['nullable', 'string', 'max:50000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $file = $request->file('attachment');
        $storedPath = $file?->store('assignment-submissions/'.$assignment->id, 'local');

        if ($file && ! $storedPath) {
            return response()->json(['message' => 'The attachment could not be stored. Please try again.'], 500);
        }

        try {
            $result = DB::transaction(function () use ($assignment, $student, $validated, $file, $storedPath): array {
                $locked = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
                abort_unless($locked->status === 'published', 404);
                $this->authorizeEnrollment($student->id, $locked);

                if ($locked->due_at->isPast() && ! $locked->allow_late) {
                    return ['state' => 'closed'];
                }

                $submission = AssignmentSubmission::query()
                    ->where('assignment_id', $locked->id)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if ($submission && $submission->status !== 'graded') {
                    return ['state' => 'already_submitted'];
                }

                if (
                    blank($validated['answer'] ?? null)
                    && ! $storedPath
                    && ! $submission?->attachment_path
                ) {
                    throw ValidationException::withMessages([
                        'answer' => 'Please enter an answer or attach a file.',
                    ]);
                }

                $previousAttachmentPath = $submission?->attachment_path;
                $submission ??= new AssignmentSubmission([
                    'assignment_id' => $locked->id,
                    'student_id' => $student->id,
                ]);
                $submission->fill([
                    'answer' => $validated['answer'] ?? $submission->answer ?? '',
                    'attachment_path' => $storedPath ?? $submission->attachment_path,
                    'attachment_name' => $file
                        ? $this->safeOriginalFilename($file->getClientOriginalName())
                        : $submission->attachment_name,
                    'attachment_mime' => $file?->getMimeType() ?? $submission->attachment_mime,
                    'attachment_size' => $file?->getSize() ?? $submission->attachment_size,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                    'score' => null,
                    'feedback' => null,
                    'graded_by' => null,
                ]);
                $submission->assignment_id = $locked->id;
                $submission->student_id = $student->id;
                $submission->save();

                return [
                    'state' => 'saved',
                    'submission' => $submission->fresh(),
                    'previous_attachment_path' => $previousAttachmentPath,
                ];
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        if ($result['state'] !== 'saved') {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            return $result['state'] === 'closed'
                ? response()->json(['message' => 'This assignment is closed for submissions.'], 409)
                : response()->json(['message' => 'Your submission is already received and awaiting review.'], 409);
        }

        $previousPath = $result['previous_attachment_path'];
        if ($storedPath && $previousPath && $previousPath !== $storedPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return response()->json([
            'message' => 'Assignment submitted successfully.',
            'submission' => $this->submissionPayload($result['submission']),
        ], 201);
    }

    public function downloadAttachment(Request $request, AssignmentSubmission $submission): StreamedResponse
    {
        $student = $request->user();
        abort_unless($student?->isStudent(), 403);
        abort_unless((int) $submission->student_id === (int) $student->id, 403);

        $assignment = $submission->assignment;
        $this->authorizeEnrollment($student->id, $assignment);
        abort_unless(
            $submission->attachment_path
                && Storage::disk('local')->exists($submission->attachment_path),
            404
        );

        return Storage::disk('local')->download(
            $submission->attachment_path,
            $this->safeOriginalFilename($submission->attachment_name ?: 'assignment-submission')
        );
    }

    private function authorizeEnrollment(int $studentId, Assignment $assignment): void
    {
        abort_unless(Enrollment::query()
            ->where('batch_id', $assignment->batch_id)
            ->where('user_id', $studentId)
            ->where('status', 'active')
            ->exists(), 403);
    }

    private function payload(Assignment $assignment, bool $includeCourse = false): array
    {
        $submission = $assignment->submissions->first();
        $isOverdue = $assignment->due_at?->isPast() ?? false;

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'allow_late' => (bool) $assignment->allow_late,
            'is_overdue' => $isOverdue,
            'is_closed' => $isOverdue && ! $assignment->allow_late,
            'max_marks' => (int) $assignment->max_marks,
            'course' => $includeCourse ? $assignment->batch?->course?->title : null,
            'submission' => $submission ? $this->submissionPayload($submission) : null,
        ];
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\\x00-\\x1F\\x7F"<>]/u', '_', $filename) ?: 'assignment-submission';

        return Str::limit(trim($filename), 180, '');
    }

    private function submissionPayload(AssignmentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'answer' => $submission->answer,
            'status' => $submission->status,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'score' => $submission->score,
            'feedback' => $submission->feedback,
            'has_attachment' => (bool) $submission->attachment_path,
            'attachment_name' => $submission->attachment_name,
            'attachment_size' => $submission->attachment_size,
            'attachment_url' => $submission->attachment_path
                ? '/api/v1/assignment-submissions/'.$submission->id.'/attachment'
                : null,
        ];
    }
}
