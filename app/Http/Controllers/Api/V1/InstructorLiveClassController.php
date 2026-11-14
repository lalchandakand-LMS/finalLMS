<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\LiveClass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstructorLiveClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request);
        $validated = $request->validate([
            'batch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:scheduled,live,completed,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $classes = LiveClass::query()
            ->whereHas('batch', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->with(['batch:id,title,course_id', 'batch.course:id,title'])
            ->when($validated['batch_id'] ?? null, fn ($query, $batchId) => $query->where('batch_id', $batchId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('starts_at')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $classes->getCollection()->map(fn (LiveClass $liveClass) => $this->payload($liveClass)),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
            ],
        ]);
    }

    public function store(Request $request, Batch $batch): JsonResponse
    {
        $teacher = $this->teacher($request);
        abort_unless((int) $batch->teacher_id === (int) $teacher->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $liveClass = $batch->liveClasses()->create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => $teacher->id,
        ]);

        return response()->json([
            'message' => 'Live class scheduled successfully.',
            'live_class' => $this->payload($liveClass->load(['batch:id,title,course_id', 'batch.course:id,title'])),
        ], 201);
    }

    public function update(Request $request, LiveClass $liveClass): JsonResponse
    {
        $teacher = $this->teacher($request);
        $liveClass->loadMissing('batch');
        abort_unless((int) $liveClass->batch->teacher_id === (int) $teacher->id, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'meeting_url' => ['sometimes', 'required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'in:scheduled,live,completed,cancelled'],
            'recording_url' => ['sometimes', 'nullable', 'url', 'max:2048', 'regex:/^https:\\/\\/(?:www\\.)?(?:youtube\\.com|youtu\\.be|youtube-nocookie\\.com|vimeo\\.com)(?:\\/|$)/i'],
            'recording_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $startsAt = isset($validated['starts_at']) ? Carbon::parse($validated['starts_at']) : $liveClass->starts_at;
        $endsAt = isset($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : $liveClass->ends_at;
        abort_unless($endsAt->greaterThan($startsAt), 422, 'The live class end time must be after its start time.');

        DB::transaction(function () use ($liveClass, $validated): void {
            $locked = LiveClass::query()->lockForUpdate()->findOrFail($liveClass->id);
            $changes = $validated;

            if (array_key_exists('recording_url', $changes)) {
                $changes['recording_published_at'] = filled($changes['recording_url'] ?? null) ? now() : null;
            }

            $locked->update($changes);
        });

        return response()->json([
            'message' => 'Live class updated successfully.',
            'live_class' => $this->payload($liveClass->fresh()->load(['batch:id,title,course_id', 'batch.course:id,title'])),
        ]);
    }

    private function payload(LiveClass $liveClass): array
    {
        return [
            'id' => $liveClass->id,
            'batch_id' => $liveClass->batch_id,
            'batch' => $liveClass->batch?->title,
            'course' => $liveClass->batch?->course?->title,
            'title' => $liveClass->title,
            'description' => $liveClass->description,
            'meeting_url' => $liveClass->meeting_url,
            'starts_at' => $liveClass->starts_at?->toIso8601String(),
            'ends_at' => $liveClass->ends_at?->toIso8601String(),
            'status' => $liveClass->status,
            'recording_url' => $liveClass->recording_url,
            'recording_published_at' => $liveClass->recording_published_at?->toIso8601String(),
            'recording_notes' => $liveClass->recording_notes,
        ];
    }

    private function teacher(Request $request): \App\Models\User
    {
        $teacher = $request->user();
        abort_unless($teacher && $teacher->isTeacher(), 403);

        return $teacher;
    }
}
