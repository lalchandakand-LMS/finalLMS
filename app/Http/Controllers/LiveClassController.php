<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\LiveClass;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
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
        abort_unless($user && ($user->isAdmin() || $user->isTeacher() || $user->isStudent() || $user->isGuardian()), 403);

        $query = LiveClass::with(['batch.course', 'batch.branch', 'batch.teacher'])
            ->orderBy('starts_at');

        if ($user->isTeacher()) {
            $query->whereHas('batch', fn ($batches) => $batches->where('teacher_id', $user->id));
        } elseif ($user->isGuardian()) {
            // Guardians may see only sessions belonging to currently consented,
            // same-branch linked students. Never expose the meeting URL to guardians.
            $linkedStudentIds = app(PluginResourceAccess::class)
                ->scopeQuery(User::query(), $user)
                ->pluck('users.id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $query->where(function ($classes) use ($linkedStudentIds): void {
                if ($linkedStudentIds === []) {
                    $classes->whereRaw('1 = 0');
                    return;
                }

                $classes->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                    ->whereIn('user_id', $linkedStudentIds)
                    ->where('status', 'active'))
                    ->orWhere(function ($recordedClasses) use ($linkedStudentIds): void {
                        $recordedClasses
                            ->whereNotNull('recording_url')
                            ->whereNotNull('recording_published_at')
                            ->where('recording_published_at', '<=', now())
                            ->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                                ->whereIn('user_id', $linkedStudentIds)
                                ->where('status', 'completed'));
                    });
            });

            $query->with(['batch.enrollments' => fn ($enrollments) => $enrollments
                ->whereIn('user_id', $linkedStudentIds)]);
        } elseif ($user->isStudent()) {
            // Active students can see their batch sessions. Completed students retain
            // access only to recordings that the teacher has already published.
            $query->where(function ($classes) use ($user): void {
                $classes->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                    ->where('user_id', $user->id)
                    ->where('status', 'active'))
                    ->orWhere(function ($recordedClasses) use ($user): void {
                        $recordedClasses
                            ->whereNotNull('recording_url')
                            ->whereNotNull('recording_published_at')
                            ->where('recording_published_at', '<=', now())
                            ->whereHas('batch.enrollments', fn ($enrollments) => $enrollments
                                ->where('user_id', $user->id)
                                ->where('status', 'completed'));
                    });
            });

            $query->with(['batch.enrollments' => fn ($enrollments) => $enrollments
                ->where('user_id', $user->id)]);
        }

        $liveClasses = $query->paginate(25);
        $liveClasses->getCollection()->each(function (LiveClass $liveClass) use ($user): void {
            $canManage = $user->isAdmin() || ($user->isTeacher() && $liveClass->batch->teacher_id === $user->id);
            $hasActiveEnrollment = $user->isStudent()
                && $liveClass->batch->enrollments->contains(fn ($enrollment) => $enrollment->status === 'active');
            $isPublished = $liveClass->recording_url
                && $liveClass->recording_published_at
                && $liveClass->recording_published_at->isPast();

            $liveClass->setAttribute('student_has_active_enrollment', $hasActiveEnrollment);
            $canViewRecording = $canManage || $user->isStudent() || $user->isGuardian();
            $liveClass->setAttribute('recording_embed_url', $canViewRecording && $isPublished
                ? $this->recordingEmbedUrl($liveClass->recording_url)
                : null);
        });

        return view('live-classes.index', compact('liveClasses'));
    }

    public function store(Batch $batch, Request $request): RedirectResponse
    {
        $this->authorizeBatch($batch);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_url' => ['required', 'url', 'max:2048'],
            'recording_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'recording_published_at' => ['nullable', 'date'],
            'recording_notes' => ['nullable', 'string', 'max:5000'],

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
            'meeting_url' => ['required', 'url', 'max:2048'],
            'recording_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'recording_published_at' => ['nullable', 'date'],
            'recording_notes' => ['nullable', 'string', 'max:5000'],
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

    private function recordingEmbedUrl(?string $url): ?string
    {
        if (! $url) return null;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = $host === 'youtu.be' ? explode('/', $path)[0] : ($query['v'] ?? basename($path));
            return preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $id = basename($path);
            return preg_match('/^[0-9]+$/', $id) ? 'https://player.vimeo.com/video/'.$id : null;
        }
        return null;
    }

    private function authorizeBatch(Batch $batch): void
    {
        $user = Auth::user();

        abort_unless($user && ($user->isAdmin() || ($user->isTeacher() && $batch->teacher_id === $user->id)), 403);
    }
}
