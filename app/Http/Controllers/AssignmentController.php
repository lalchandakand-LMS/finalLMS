<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Batch $batch): View
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $assignments = $batch->assignments()->with(['submissions.student'])->withCount('submissions')->latest()->get();

        return view('teacher.assignments.index', compact('batch', 'assignments'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        abort_unless($batch->teacher_id === Auth::id(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['required', 'string', 'max:10000'],
            'due_at' => ['required', 'date', 'after:now'],
            'allow_late' => ['nullable', 'boolean'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $batch->assignments()->create([...$validated, 'allow_late' => $request->boolean('allow_late'), 'teacher_id' => Auth::id(), 'status' => 'published']);

        return back()->with('success', 'Assignment published to the batch.');
    }

    public function show(Assignment $assignment): View
    {
        abort_unless($assignment->status === 'published', 404);
        abort_unless(Enrollment::where('batch_id', $assignment->batch_id)->where('user_id', Auth::id())->where('status', 'active')->exists(), 403);
        $assignment->load('batch.course');
        $submission = $assignment->submissions()->where('student_id', Auth::id())->first();

        return view('assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Assignment $assignment, Request $request): RedirectResponse
    {
        abort_unless(Enrollment::where('batch_id', $assignment->batch_id)->where('user_id', Auth::id())->where('status', 'active')->exists(), 403);

        if ($assignment->status !== 'published' || ($assignment->due_at->isPast() && ! $assignment->allow_late)) {
            return back()->with('error', 'This assignment is closed for submissions.');
        }

        $validated = $request->validate([
            'answer' => ['nullable', 'string', 'max:50000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ]);

        $existingSubmission = $assignment->submissions()->where('student_id', Auth::id())->first();
        if (blank($validated['answer'] ?? null) && ! $request->hasFile('attachment') && ! $existingSubmission?->attachment_path) {
            return back()->withErrors(['answer' => 'Please enter an answer or attach a file.'])->withInput();
        }
        if ($existingSubmission && $existingSubmission->status !== 'graded') {
            return back()->with('info', 'Your submission is already received and awaiting review.');
        }

        $file = $request->file('attachment');
        $path = $file?->store('assignment-submissions/'.$assignment->id, 'local');
        if ($file && ! $path) {
            return back()->withErrors(['attachment' => 'The attachment could not be stored. Please try again.'])->withInput();
        }

        $submission = $existingSubmission ?? new AssignmentSubmission(['student_id' => Auth::id()]);
        $submission->fill([
                'answer' => $validated['answer'] ?? '',
                'attachment_path' => $path ?? $existingSubmission?->attachment_path,
                'attachment_name' => $file?->getClientOriginalName() ?? $existingSubmission?->attachment_name,
                'attachment_mime' => $file?->getMimeType() ?? $existingSubmission?->attachment_mime,
                'attachment_size' => $file?->getSize() ?? $existingSubmission?->attachment_size,
                'submitted_at' => now(),
                'status' => 'submitted',
                'score' => null,
                'feedback' => null,
                'graded_by' => null,
            ]);
        $submission->assignment()->associate($assignment);
        $submission->save();

        if ($path && $existingSubmission?->attachment_path && $existingSubmission->attachment_path !== $path) {
            Storage::disk('local')->delete($existingSubmission->attachment_path);
        }

        return back()->with('success', $existingSubmission ? 'Your revised assignment has been submitted.' : 'Assignment submitted successfully.');
    }

    public function download(AssignmentSubmission $submission): StreamedResponse
    {
        $assignment = $submission->assignment;
        $user = Auth::user();
        abort_unless($user->isAdmin() || $assignment->teacher_id === $user->id || ($submission->student_id === $user->id && $user->isStudent()), 403);
        abort_unless($submission->attachment_path && Storage::disk('local')->exists($submission->attachment_path), 404);

        return Storage::disk('local')->download($submission->attachment_path, $submission->attachment_name ?: 'assignment-submission');
    }

    public function grade(Assignment $assignment, AssignmentSubmission $submission, Request $request): RedirectResponse
    {
        abort_unless($assignment->teacher_id === Auth::id(), 403);
        abort_unless($submission->assignment_id === $assignment->id, 404);

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assignment->max_marks],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([...$validated, 'graded_by' => Auth::id(), 'status' => 'graded']);

        return back()->with('success', 'Assignment graded successfully.');
    }
}