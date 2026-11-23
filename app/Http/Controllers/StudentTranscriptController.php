<?php

namespace App\\Http\\Controllers;

use App\\Models\\AssignmentSubmission;
use App\\Models\\Enrollment;
use App\\Models\\ExamAttempt;
use Illuminate\\Support\\Facades\\Auth;
use Illuminate\\View\\View;

class StudentTranscriptController extends Controller
{
    public function show(): View
    {
        $student = Auth::user();
        abort_unless($student && $student->isStudent(), 403);

        $enrollments = Enrollment::query()
            ->with('course:id,title')
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->latest('created_at')
            ->get();

        $examAttempts = ExamAttempt::query()
            ->with(['exam.batch.course:id,title', 'exam.questions:id,exam_id,marks'])
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->whereHas('exam')
            ->latest('submitted_at')
            ->get();

        $assignmentSubmissions = AssignmentSubmission::query()
            ->with(['assignment.batch.course:id,title'])
            ->where('student_id', $student->id)
            ->where('status', 'graded')
            ->whereNotNull('score')
            ->latest('submitted_at')
            ->get();

        $gradedExamAttempts = $examAttempts->filter(fn ($attempt) =>
            $attempt->score !== null && (int) $attempt->exam->questions->sum('marks') > 0
        );
        $examAverage = $gradedExamAttempts->isNotEmpty()
            ? round($gradedExamAttempts->avg(function ($attempt) {
                $maximum = (int) $attempt->exam->questions->sum('marks');
                return min(100, ((float) $attempt->score / $maximum) * 100);
            }), 2)
            : null;

        return view('student.transcript', compact(
            'student',
            'enrollments',
            'examAttempts',
            'assignmentSubmissions',
            'examAverage'
        ));
    }
}
