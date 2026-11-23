<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceFeePeriod;
use App\Models\Batch;
use App\Models\LiveClass;
use App\Models\LiveClassAttendance;
use App\Models\User;
use App\Services\Plugins\PluginResourceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiveClassController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        abort_unless($user && ($user->isAdmin() || $user->isTeacher() || $user->isStudent() || $user->isGuardian()), 403);

        $query = LiveClass::with(['batch.course', 'batch.branch', 'batch.teacher', 'attendances'])
            ->orderBy('starts_at');

        if ($user->isAdmin() || $user->isTeacher()) {
            $query->with(['batch.enrollments' => fn ($enrollments) => $enrollments->whereIn('status', ['active', 'completed'])->with('user')]);
        }

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
            'meeting_url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
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
            'meeting_url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
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


    public function storeAttendance(LiveClass $liveClass, Request $request): RedirectResponse
    {
        $this->authorizeBatch($liveClass->batch);
        abort_if($liveClass->status === 'cancelled', 422, 'Cancelled classes cannot receive attendance.');
        abort_unless($liveClass->ends_at->isPast(), 422, 'Attendance can be finalized after the class ends.');
        $data = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(['present', 'late', 'absent', 'excused'])],
            'records.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($liveClass, $data): void {
            // Use the same batch-first lock order as invoice generation to prevent
            // an attendance write racing a monthly billing snapshot.
            $lockedBatch = Batch::query()->lockForUpdate()->findOrFail($liveClass->batch_id);
            $session = LiveClass::query()->where('batch_id', $lockedBatch->id)->lockForUpdate()->findOrFail($liveClass->id);
            $billingPeriod = $session->starts_at->format('Y-m');
            abort_if(
                AttendanceFeePeriod::query()->where('batch_id', $lockedBatch->id)->where('billing_period', $billingPeriod)->exists(),
                409,
                'Class attendance is locked because this billing period has already been invoiced. Use a finance adjustment instead of changing billed attendance.'
            );

            $activeStudentIds = $lockedBatch->enrollments()
                ->whereIn('status', ['active', 'completed'])
                ->lockForUpdate()
                ->pluck('user_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            foreach ($data['records'] as $record) {
                abort_unless(in_array((int) $record['student_id'], $activeStudentIds, true), 422, 'Only currently enrolled students can be marked for this class.');
                LiveClassAttendance::query()->updateOrCreate(
                    ['live_class_id' => $session->id, 'student_id' => $record['student_id']],
                    ['marked_by' => Auth::id(), 'status' => $record['status'], 'note' => $record['note'] ?? null]
                );
            }

            $session->update(['status' => 'completed']);
        }, 3);

        try {
            ActivityLog::query()->create([
                'user_id' => Auth::id(),
                'event' => 'live_class.attendance_recorded',
                'description' => 'Recorded attendance for live class #'.$liveClass->id.' ('.count($data['records']).' student record(s)).',
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Class-session attendance saved successfully.');
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
