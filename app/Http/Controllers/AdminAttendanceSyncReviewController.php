<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSyncReview;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAttendanceSyncReviewController extends Controller
{
    public function index(): View
    {
        $reviews = AttendanceSyncReview::query()
            ->with(['teacher:id,name,email', 'student:id,name,email', 'batch:id,title'])
            ->where('review_status', 'pending')
            ->orderBy('created_at')
            ->paginate(30);

        return view('admin.attendance-sync-reviews.index', compact('reviews'));
    }

    public function resolve(Request $request, AttendanceSyncReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $review, $validated): void {
            $locked = AttendanceSyncReview::query()
                ->whereKey($review->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->review_status === 'pending', 409, 'This review has already been processed.');

            if ($validated['decision'] === 'approve') {
                $batch = Batch::query()
                    ->whereKey($locked->batch_id)
                    ->where('teacher_id', $locked->teacher_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless(
                    $batch->enrollments()
                        ->where('user_id', $locked->student_id)
                        ->where('status', 'active')
                        ->exists(),
                    409,
                    'The student no longer has an active enrollment in this batch.'
                );

                $existing = Attendance::query()
                    ->where('batch_id', $batch->id)
                    ->where('student_id', $locked->student_id)
                    ->whereDate('attendance_date', $locked->attendance_date)
                    ->lockForUpdate()
                    ->first();

                abort_if($existing, 409, 'Attendance already exists for this student and date; resolve that record instead.');

                $attendance = Attendance::query()->create([
                    'batch_id' => $batch->id,
                    'student_id' => $locked->student_id,
                    'marked_by' => $locked->teacher_id,
                    'attendance_date' => $locked->attendance_date,
                    'status' => $locked->attendance_status,
                    'idempotency_key' => $locked->idempotency_key,
                    'location_latitude' => $locked->location_latitude,
                    'location_longitude' => $locked->location_longitude,
                    'location_accuracy_meters' => $locked->location_accuracy_meters,
                    'location_captured_at' => $locked->location_captured_at,
                ]);

                DB::table('attendance_change_logs')->insert([
                    'attendance_id' => $attendance->id,
                    'actor_id' => $request->user()->id,
                    'previous_status' => null,
                    'new_status' => $attendance->status,
                    'reason' => trim($validated['reason']),
                    'source' => 'stale_location_manual_review',
                    'created_at' => now(),
                ]);

                $locked->update([
                    'review_status' => 'approved',
                    'reviewed_by' => $request->user()->id,
                    'review_reason' => trim($validated['reason']),
                    'reviewed_at' => now(),
                ]);

                return;
            }

            $locked->update([
                'review_status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'review_reason' => trim($validated['reason']),
                'reviewed_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->route('admin.attendance-sync-reviews.index')
            ->with('success', $validated['decision'] === 'approve'
                ? 'Stale attendance was approved and added to the audit log.'
                : 'Stale attendance was rejected and the decision was recorded.');
    }
}
