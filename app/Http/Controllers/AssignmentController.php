<?php

namespace App\\Http\\Controllers;

use App\\Models\\Assignment;
use App\\Models\\AssignmentSubmission;
use App\\Models\\Batch;
use App\\Models\\Enrollment;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Storage;
use Illuminate\\Support\\Str;
use Illuminate\\Validation\\ValidationException;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;
use Illuminate\\View\\View;
use Throwable;

class AssignmentController extends Controller
{
    public function index(Batch $batch): View
    {
        $this->authorizeBatchManagement($batch);

        $assignments = $batch->assignments()
            ->with(['submissions.student'])
            ->withCount('submissions')
            ->latest()
            ->get();

        $isAdmin = Auth::user()->isAdmin();

        return view('teacher.assignments.index', compact('batch', 'assignments', 'isAdmin'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatchManagement($batch);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['required', 'string', 'max:10000'],
            'due_at' => ['required', 'date', 'after:now'],
            'allow_late' => ['nullable', 'boolean'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $batch->assignments()->create([
            ...$validated,
            'allow_late' => $request->boolean('allow_late'),
            // Admins manage the batch on behalf of its assigned teacher.
            'teacher_id' => $batch->teacher_id,
            'status' => 'published',
        ]);

        return back()->with('success', 'Assignment published to the batch.');
    }

    public function show(Assignment $assignment): View
    {
        abort_unless(Auth::user()->isStudent(), 403);
        abort_unless($assignment->status === 'published', 404);
        $this->authorizeActiveEnrollment($assignment);

        $assignment->load('batch.course');
        $submission = $assignment->submissions()
            ->where('student_id', Auth::id())
            ->first();

        return view('assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Assignment $assignment, Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->isStudent(), 403);

        $this->authorizeActiveEnrollment($assignment);

        $validated = $request->validate([
            'answer' => ['nullable', 'string', 'max:50000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $file = $request->file('attachment');
        $storedPath = $file?->store('assignment-submissions/'.$assignment->id, 'local');

        if ($file && ! $storedPath) {
            return back()
                ->withErrors(['attachment' => 'The attachment could not be stored. Please try again.'])
                ->withInput();
        }

        try {
            $result = DB::transaction(function () use ($assignment, $validated, $file, $storedPath): array {
                // Serialize submissions for one assignment to prevent duplicate first submissions.
                $lockedAssignment = Assignment::query()
                    ->lockForUpdate()
                    ->findOrFail($assignment->id);

                abort_unless(
                    Enrollment::query()
                        ->where('batch_id', $lockedAssignment->batch_id)
                        ->where('user_id', Auth::id())
                        ->where('status', 'active')
                        ->exists(),
                    403
                );

                if (
                    $lockedAssignment->status !== 'published'
                    || ($lockedAssignment->due_at->isPast() && ! $lockedAssignment->allow_late)
                ) {
                    return ['state' => 'closed'];
                }

                $submission = AssignmentSubmission::query()
                    ->where('assignment_id', $lockedAssignment->id)
                    ->where('student_id', Auth::id())
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
                    'assignment_id' => $lockedAssignment->id,
                    'student_id' => Auth::id(),
                ]);

                $submission->fill([
                    'answer' => $validated['answer'] ?? '',
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
                $submission->assignment_id = $lockedAssignment->id;
                $submission->student_id = Auth::id();
                $submission->save();

                return [
                    'state' => 'saved',
                    'previous_attachment_path' => $previousAttachmentPath,
                    'was_revision' => $submission->wasRecentlyCreated === false,
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
                ? back()->with('error', 'This assignment is closed for submissions.')
                : back()->with('info', 'Your submission is already received and awaiting review.');
        }

        $previousPath = $result['previous_attachment_path'];
        if ($storedPath && $previousPath && $previousPath !== $storedPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with(
            'success',
            $result['was_revision'] ? 'Your revised assignment has been submitted.' : 'Assignment submitted successfully.'
        );
    }

    public function download(AssignmentSubmission $submission): StreamedResponse
    {
        $assignment = $submission->assignment;
        $user = Auth::user();

        $isAuthorizedTeacher = $user->isTeacher() && (int) $assignment->teacher_id === (int) $user->id;
        $isAuthorizedStudent = $user->isStudent()
            && (int) $submission->student_id === (int) $user->id
            && Enrollment::query()
                ->where('batch_id', $assignment->batch_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

        abort_unless($user->isAdmin() || $isAuthorizedTeacher || $isAuthorizedStudent, 403);
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

    public function grade(Assignment $assignment, AssignmentSubmission $submission, Request $request): RedirectResponse
    {
        $user = Auth::user();
        $isAuthorizedTeacher = $user->isTeacher() && (int) $assignment->teacher_id === (int) $user->id;

        abort_unless($user->isAdmin() || $isAuthorizedTeacher, 403);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);

        DB::transaction(function () use ($assignment, $submission, $request): void {
            $lockedAssignment = Assignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->id);
            $lockedSubmission = AssignmentSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submission->id);

            abort_unless((int) $lockedSubmission->assignment_id === (int) $lockedAssignment->id, 404);

            $validated = $request->validate([
                'score' => ['required', 'numeric', 'min:0', 'max:'.$lockedAssignment->max_marks],
                'feedback' => ['nullable', 'string', 'max:5000'],
            ]);

            $lockedSubmission->update([
                ...$validated,
                'graded_by' => Auth::id(),
                'status' => 'graded',
            ]);
        });

        return back()->with('success', 'Assignment graded successfully.');
    }

    private function authorizeBatchManagement(Batch $batch): void
    {
        $user = Auth::user();
        $isAssignedTeacher = $user->isTeacher() && (int) $batch->teacher_id === (int) $user->id;

        abort_unless($user->isAdmin() || $isAssignedTeacher, 403);
    }

    private function authorizeActiveEnrollment(Assignment $assignment): void
    {
        abort_unless(
            Enrollment::query()
                ->where('batch_id', $assignment->batch_id)
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    private function safeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\\\', '/', $filename));
        $filename = preg_replace('/[\\x00-\\x1F\\x7F"<>]/u', '_', $filename) ?: 'assignment-submission';

        return Str::limit(trim($filename), 180, '');
    }
}
