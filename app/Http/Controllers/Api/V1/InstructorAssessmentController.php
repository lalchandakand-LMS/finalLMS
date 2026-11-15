<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorAssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher?->isTeacher(), 403);

        $exams = Exam::query()
            ->whereHas('batch', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->with('batch.course:id,title')
            ->withCount('questions')
            ->withCount(['attempts as submitted_attempts_count' => fn ($query) => $query->where('status', 'submitted')])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $exams->getCollection()->map(fn (Exam $exam): array => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status,
                'batch' => $exam->batch?->title,
                'course' => $exam->batch?->course?->title,
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'pass_marks' => $exam->pass_marks,
                'questions_count' => $exam->questions_count,
                'submitted_attempts_count' => $exam->submitted_attempts_count,
            ])->values(),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'last_page' => $exams->lastPage(),
                'total' => $exams->total(),
            ],
        ]);
    }

    public function results(Request $request, Exam $exam): JsonResponse
    {
        $teacher = $request->user();
        abort_unless($teacher?->isTeacher() || $teacher?->isAdmin(), 403);
        abort_unless($exam->batch()->where('teacher_id', $teacher->id)->exists(), 404);

        $attempts = $exam->attempts()
            ->where('status', 'submitted')
            ->with('student:id,name,email')
            ->latest('submitted_at')
            ->paginate(30);

        $scores = $exam->attempts()
            ->where('status', 'submitted')
            ->selectRaw('COUNT(*) as attempts_count, AVG(score) as average_score, MAX(score) as highest_score')
            ->first();

        $passed = $exam->attempts()
            ->where('status', 'submitted')
            ->where('score', '>=', $exam->pass_marks)
            ->count();

        $count = (int) ($scores?->attempts_count ?? 0);

        return response()->json([
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'pass_marks' => $exam->pass_marks,
                'batch' => $exam->batch?->title,
                'course' => $exam->batch?->course?->title,
            ],
            'summary' => [
                'submitted_attempts' => $count,
                'passed_attempts' => $passed,
                'pass_rate' => $count > 0 ? round(($passed / $count) * 100, 1) : 0,
                'average_score' => $scores?->average_score !== null ? round((float) $scores->average_score, 2) : null,
                'highest_score' => $scores?->highest_score !== null ? (float) $scores->highest_score : null,
            ],
            'data' => $attempts->getCollection()->map(fn ($attempt): array => [
                'id' => $attempt->id,
                'student' => [
                    'id' => $attempt->student?->id,
                    'name' => $attempt->student?->name ?? 'Student',
                ],
                'score' => (float) $attempt->score,
                'passed' => (float) $attempt->score >= (float) $exam->pass_marks,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'total' => $attempts->total(),
            ],
        ]);
    }
}
