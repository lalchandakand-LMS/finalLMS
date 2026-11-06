<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_publishes_exam_and_enrolled_student_completes_single_attempt_with_automatic_scoring(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();

        $this->actingAs($teacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'English Grammar Test',
            'instructions' => 'Choose the correct answer.',
            'starts_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'duration_minutes' => 30,
            'pass_marks' => 2,
            'questions' => [
                ['question' => 'Plural of child?', 'options' => ['childs', 'children', 'childes', 'child'], 'correct_option' => 1, 'marks' => 2],
                ['question' => 'Past of go?', 'options' => ['goed', 'gone', 'went', 'goes'], 'correct_option' => 2, 'marks' => 1],
            ],
        ])->assertRedirect();

        $exam = Exam::firstOrFail();
        $this->assertDatabaseHas('exam_questions', ['exam_id' => $exam->id, 'question' => 'Plural of child?']);

        $this->actingAs($student)->post(route('student.exams.start', $exam))->assertRedirect(route('student.exams.show', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->post(route('student.exams.submit', $attempt), [
            'answers' => [$exam->questions[0]->id => 1, $exam->questions[1]->id => 0],
        ])->assertRedirect(route('student.exams.show', $exam));

        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id, 'score' => 2, 'status' => 'submitted']);
        $this->assertDatabaseCount('exam_answers', 2);

        $this->post(route('student.exams.start', $exam))->assertSessionHas('info');
        $this->assertDatabaseCount('exam_attempts', 1);
    }

    public function test_student_cannot_access_exam_without_active_batch_enrollment(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent(false);
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Private test',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'status' => 'published',
        ]);

        $this->actingAs($student)->get(route('student.exams.show', $exam))->assertForbidden();
        $this->post(route('student.exams.start', $exam))->assertForbidden();
    }

    public function test_other_teacher_cannot_create_exam_for_batch(): void
    {
        [$teacher, $student, $batch] = $this->makeBatchWithStudent();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)->post(route('teacher.batches.exams.store', $batch), [
            'title' => 'Unauthorized exam',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'duration_minutes' => 30,
            'pass_marks' => 0,
            'questions' => [['question' => 'Q?', 'options' => ['A','B','C','D'], 'correct_option' => 0, 'marks' => 1]],
        ])->assertForbidden();

        $this->assertDatabaseCount('exams', 0);
    }

    private function makeBatchWithStudent(bool $enroll = true): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Grammar',
            'slug' => 'grammar-'.uniqid(),
            'description' => 'Grammar practice.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Main Branch', 'slug' => 'main-'.uniqid()]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Morning Batch',
            'capacity' => 25,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        if ($enroll) Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);

        return [$teacher, $student, $batch];
    }
}
