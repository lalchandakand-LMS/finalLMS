<?php

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Assignment;
use App\\Models\\Attendance;
use App\\Models\\Batch;
use App\\Models\\Enrollment;
use App\\Models\\Exam;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;

class InstructorAnalyticsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher?->isTeacher(), 403);

        $batchIds = Batch::query()
            ->where('teacher_id', $teacher->id)
            ->select('id');

        $attendance = Attendance::query()
            ->whereIn('batch_id', $batchIds);

        $attendanceTotal = (clone $attendance)->count();
        $present = (clone $attendance)->where('status', 'present')->count();
        $absent = (clone $attendance)->where('status', 'absent')->count();
        $late = (clone $attendance)->where('status', 'late')->count();
        $excused = (clone $attendance)->where('status', 'excused')->count();

        $publishedAssignments = Assignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', 'published');

        $assignmentCount = (clone $publishedAssignments)->count();

        $submissionTotals = DB::table('assignment_submissions')
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->where('assignments.teacher_id', $teacher->id)
            ->where('assignments.status', 'published')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN assignment_submissions.status = 'graded' THEN 1 ELSE 0 END) as graded")
            ->first();

        $publishedExams = Exam::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', 'published');

        $examCount = (clone $publishedExams)->count();

        $examTotals = DB::table('exam_attempts')
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exams.teacher_id', $teacher->id)
            ->where('exams.status', 'published')
            ->where('exam_attempts.status', 'submitted')
            ->selectRaw('COUNT(*) as submitted')
            ->selectRaw('AVG(exam_attempts.score) as average_score')
            ->selectRaw('SUM(CASE WHEN exam_attempts.score >= exams.pass_marks THEN 1 ELSE 0 END) as passed')
            ->first();

        $submittedExams = (int) ($examTotals->submitted ?? 0);
        $passedExams = (int) ($examTotals->passed ?? 0);

        return response()->json([
            'data' => [
                'batches_count' => Batch::query()->where('teacher_id', $teacher->id)->count(),
                'active_students_count' => Enrollment::query()
                    ->whereIn('batch_id', $batchIds)
                    ->where('status', 'active')
                    ->distinct('user_id')
                    ->count('user_id'),
                'attendance' => [
                    'total_records' => $attendanceTotal,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'attendance_rate' => $attendanceTotal > 0
                        ? round(($present / $attendanceTotal) * 100, 1)
                        : null,
                ],
                'assignments' => [
                    'published' => $assignmentCount,
                    'submissions' => (int) ($submissionTotals->total ?? 0),
                    'graded' => (int) ($submissionTotals->graded ?? 0),
                ],
                'assessments' => [
                    'published' => $examCount,
                    'submitted_attempts' => $submittedExams,
                    'passed_attempts' => $passedExams,
                    'pass_rate' => $submittedExams > 0
                        ? round(($passedExams / $submittedExams) * 100, 1)
                        : null,
                    'average_score' => $examTotals?->average_score !== null
                        ? round((float) $examTotals->average_score, 2)
                        : null,
                ],
            ],
        ]);
    }
}
