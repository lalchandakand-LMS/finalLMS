<?php

namespace App\Http\Controllers;

use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StudentTranscriptController extends Controller
{
    public function show(): View
    {
        $student = Auth::user();
        abort_unless($student && $student->isStudent(), 403);

        $enrollments = Enrollment::query()
            ->with(['course:id,title', 'course.lessons:id,course_id'])
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->latest('created_at')
            ->get();

        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
        $completedLessonsByCourse = LessonProgress::query()
            ->with('lesson:id,course_id')
            ->where('user_id', $student->id)
            ->whereNotNull('completed_at')
            ->whereHas('lesson', fn ($lessons) => $lessons->whereIn('course_id', $courseIds))
            ->get()
            ->groupBy(fn ($progress) => $progress->lesson?->course_id)
            ->map(fn ($progress) => $progress->count());

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

        $certificates = Certificate::query()
            ->with('course:id,title')
            ->where('user_id', $student->id)
            ->where('status', 'issued')
            ->latest('issued_at')
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
            'completedLessonsByCourse',
            'examAttempts',
            'assignmentSubmissions',
            'certificates',
            'examAverage'
        ));
    }
}
