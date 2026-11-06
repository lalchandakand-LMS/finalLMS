<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\LiveClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiveClassController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $query = LiveClass::with(['batch.course', 'batch.branch', 'batch.teacher'])
            ->orderBy('starts_at');

        if ($user->isTeacher()) {
            $query->whereHas('batch', fn ($batches) => $batches->where('teacher_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'completed']));
        }

        if ($user->isStudent()) {
            $query->with(['batch.enrollments' => fn ($enrollments) => $enrollments->where('user_id', $user->id)]);
        }

        $liveClasses = $query->paginate(25);

        return view('live-classes.index', compact('liveClasses'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatch($batch);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'recording_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $batch->liveClasses()->create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Live class scheduled successfully.');
    }

    public function update(LiveClass $liveClass, Request $request): RedirectResponse
    {
        $this->authorizeBatch($liveClass->batch);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'recording_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(['scheduled', 'live', 'completed', 'cancelled'])],
        ]);

        $liveClass->update($validated);

        return back()->with('success', 'Live class updated successfully.');
    }

    public function destroy(LiveClass $liveClass): RedirectResponse
    {
        $this->authorizeBatch($liveClass->batch);
        $liveClass->delete();

        return back()->with('success', 'Live class deleted successfully.');
    }

    private function authorizeBatch(Batch $batch): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || ($user->isTeacher() && $batch->teacher_id === $user->id)), 403);
    }
}
