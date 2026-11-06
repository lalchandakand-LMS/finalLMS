<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Batch $batch): View
    {
        abort_unless(Auth::user()->isAdmin() || $batch->teacher_id === Auth::id(), 403);
        $assignments = $batch->assignments()->withCount('submissions')->latest()->get();

        return view('teacher.assignments.index', compact('batch', 'assignments'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        abort_unless($batch->teacher_id === Auth::id(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['required', 'string', 'max:10000'],
            'due_at' => ['required', 'date', 'after:now'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $batch->assignments()->create([...$validated, 'teacher_id' => Auth::id(), 'status' => 'published']);

        return back()->with('success', 'Assignment published to the batch.');
    }

    public function show(Assignment $assignment): View
    {
        abort_unless(Enrollment::where('batch_id', $assignment->batch_id)->where('user_id', Auth::id())->exists(), 403);
        $assignment->load('batch.course');
        $submission = $assignment->submissions()->where('student_id', Auth::id())->first();

        return view('assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Assignment $assignment, Request $request): RedirectResponse
    {
        abort_unless(Enrollment::where('batch_id', $assignment->batch_id)->where('user_id', Auth::id())->exists(), 403);

        if ($assignment->due_at->isPast() || $assignment->status !== 'published') {
            return back()->with('error', 'This assignment is closed for submissions.');
        }

        $validated = $request->validate(['answer' => ['required', 'string', 'max:50000']]);

        $submission = $assignment->submissions()->firstOrCreate(
            ['student_id' => Auth::id()],
            ['answer' => $validated['answer'], 'submitted_at' => now(), 'status' => 'submitted']
        );

        if (! $submission->wasRecentlyCreated) {
            return back()->with('info', 'You have already submitted this assignment.');
        }

        return back()->with('success', 'Assignment submitted successfully.');
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