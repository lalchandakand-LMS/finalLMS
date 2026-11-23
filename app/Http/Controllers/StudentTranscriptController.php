<?php

namespace App\Http\Controllers;

use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\CourseContentProgress;
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
            ->with([
                'course:id,title',
                'course.lessons:id,course_id',
                'course.curriculumItems' => fn ($items) => $items
                    ->whereHas('post', fn ($posts) => $posts->where('status', 'published')
                        ->whereNotNull('published_at')->where('published_at', '<=', now()))
                    ->with('post:id,status,published_at'),
            ])
            ->where('user_id', $student->id)
            ->whereIn('status', ['active', 'completed'])
            ->latest('created_at')
            ->get();

        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values();

        $completedLessonCounts = LessonProgress::query()
            ->with('lesson:id,course_id')
            ->where('user_id', $student->id)
            ->whereNotNull('completed_at')
            ->whereHas('lesson', fn ($lessons) => $lessons->whereIn('course_id', $courseIds))
            ->get()
            ->groupBy(fn ($progress) => $progress->lesson?->course_id)
            ->map(fn ($progress) => $progress->count());

        $completedContentCounts = CourseContentProgress::query()
            ->with('curriculumItem:id,course_id')
            ->where('user_id', $student->id)
            ->whereNotNull('completed_at')
            ->whereHas('curriculumItem', fn ($items) => $items
                ->whereIn('course_id', $courseIds)
                ->whereHas('post', fn ($posts) => $posts->where('status', 'published')
                    ->whereNotNull('published_at')->where('published_at', '<=', now())))
            ->get()
            ->groupBy(fn ($progress) => $progress->curriculumItem?->course_id)
            ->map(fn ($progress) => $progress->count());

        $courseProgress = $enrollments->mapWithKeys(function ($enrollment) use ($completedLessonCounts, $completedContentCounts): array {
            $courseId = $enrollment->course_id;
            $total = ($enrollment->course?->lessons->count() ?? 0)
                + ($enrollment->course?->curriculumItems->count() ?? 0);
            $completed = (int) ($completedLessonCounts[$courseId] ?? 0)
                + (int) ($completedContentCounts[$courseId] ?? 0);

            return [$courseId => [
                'completed' => min($completed, $total),
                'total' => $total,
                'percent' => $total > 0 ? min(100, (int) round(($completed / $total) * 100)) : 0,
            ]];
        });

        $examAttempts = ExamAttempt::query()
            ->with(['exam.batch.course:id,title', 'attemptQuestions.question:id,marks'])
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->whereHas('exam')
            ->latest('submitted_at')
            ->get();

        $examAttempts->each(function ($attempt): void {
            $attempt->setAttribute('maximum_score', (int) $attempt->attemptQuestions
                ->sum(fn ($attemptQuestion) => (int) ($attemptQuestion->question?->marks ?? 0)));
            // Match the student exam page: do not expose marks before the teacher publishes results.
            $attempt->setAttribute(
                'transcript_score',
                $attempt->exam?->result_status === 'published' ? $attempt->score : null
            );
        });

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
            $attempt->transcript_score !== null && $attempt->maximum_score > 0
        );
        $examAverage = $gradedExamAttempts->isNotEmpty()
            ? round($gradedExamAttempts->avg(fn ($attempt) =>
                min(100, ((float) $attempt->transcript_score / $attempt->maximum_score) * 100)
            ), 2)
            : null;

        return view('student.transcript', compact(
            'student',
            'enrollments',
            'courseProgress',
            'examAttempts',
            'assignmentSubmissions',
            'certificates',
            'examAverage'
        ));
    }
}
