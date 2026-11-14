<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuestionSetGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_teacher_can_review_and_grade_written_answers_and_student_sees_final_result(): void
    {
        [$course, $set, $student, $teacher, $attempt] = $this->submittedAttempt();

        $this->actingAs($teacher)
            ->get(route('teacher.question-set-attempts.index', [$course, $set]))
            ->assertOk()
            ->assertSee('Grammar written assessment')
            ->assertSee($student->name)
            ->assertSee('Awaiting review');

        $this->get(route('teacher.question-set-attempts.show', $attempt))
            ->assertOk()
            ->assertSee('My written response one.')
            ->assertSee('SECRET REFERENCE ANSWER')
            ->assertSee('My written response two.');

        $questions = $attempt->questions()->where('type', 'short_answer')->get();
        $this->post(route('teacher.question-set-attempts.grade', $attempt), [
            'answers' => [
                $questions[0]->id => ['marks_awarded' => 2, 'feedback' => 'Well explained.'],
                $questions[1]->id => ['marks_awarded' => 1, 'feedback' => 'Add one more detail.'],
            ],
            'teacher_feedback' => 'Good effort. Keep practicing.',
        ])->assertRedirect(route('teacher.question-set-attempts.show', $attempt))
            ->assertSessionHas('success');

        $attempt->refresh()->load('questions');
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(3, $attempt->manual_score);
        $this->assertSame(6, $attempt->final_score);
        $this->assertSame($teacher->id, $attempt->graded_by);
        $this->assertNotNull($attempt->graded_at);
        $this->assertSame('Well explained.', $attempt->questions->firstWhere('id', $questions[0]->id)->manual_feedback);
        $this->assertSame($teacher->id, $attempt->questions->firstWhere('id', $questions[0]->id)->graded_by);

        $this->actingAs($student)
            ->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('Final quiz result')
            ->assertSee('6 / 7')
            ->assertSee('Good effort. Keep practicing.')
            ->assertSee('Well explained.')
            ->assertDontSee('SECRET REFERENCE ANSWER');
    }

    public function test_teacher_cannot_grade_an_attempt_for_another_teachers_course(): void
    {
        [$course, $set, $student, $teacher, $attempt] = $this->submittedAttempt();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->get(route('teacher.question-set-attempts.show', $attempt))
            ->assertForbidden();

        $this->post(route('teacher.question-set-attempts.grade', $attempt), [
            'answers' => [
                $attempt->questions()->where('type', 'short_answer')->first()->id => ['marks_awarded' => 1],
            ],
        ])->assertForbidden();

        $this->assertSame('awaiting_manual', $attempt->fresh()->status);
    }

    public function test_grading_requires_every_written_answer_and_rejects_marks_above_question_maximum(): void
    {
        [$course, $set, $student, $teacher, $attempt] = $this->submittedAttempt();
        $questions = $attempt->questions()->where('type', 'short_answer')->get();

        $this->actingAs($teacher)
            ->from(route('teacher.question-set-attempts.show', $attempt))
            ->post(route('teacher.question-set-attempts.grade', $attempt), [
                'answers' => [
                    $questions[0]->id => ['marks_awarded' => 2],
                ],
            ])
            ->assertSessionHasErrors('answers');

        $this->from(route('teacher.question-set-attempts.show', $attempt))
            ->post(route('teacher.question-set-attempts.grade', $attempt), [
                'answers' => [
                    $questions[0]->id => ['marks_awarded' => 3],
                    $questions[1]->id => ['marks_awarded' => 1],
                ],
            ])
            ->assertSessionHasErrors('answers.'.$questions[0]->id.'.marks_awarded');

        $this->assertSame('awaiting_manual', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->final_score);
    }

    public function test_student_cannot_access_teacher_grading_workspace_and_attempt_cannot_be_graded_twice(): void
    {
        [$course, $set, $student, $teacher, $attempt] = $this->submittedAttempt();
        $questions = $attempt->questions()->where('type', 'short_answer')->get();

        $this->actingAs($student)
            ->get(route('teacher.question-set-attempts.index', [$course, $set]))
            ->assertForbidden();

        $this->actingAs($teacher)->post(route('teacher.question-set-attempts.grade', $attempt), [
            'answers' => [
                $questions[0]->id => ['marks_awarded' => 2],
                $questions[1]->id => ['marks_awarded' => 2],
            ],
        ])->assertRedirect();

        $this->post(route('teacher.question-set-attempts.grade', $attempt), [
            'answers' => [
                $questions[0]->id => ['marks_awarded' => 2],
                $questions[1]->id => ['marks_awarded' => 2],
            ],
        ])->assertStatus(409);

        $this->assertSame('graded', $attempt->fresh()->status);
    }

    private function submittedAttempt(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $set = QuestionSet::create([
            'title' => 'Grammar written assessment',
            'status' => 'published',
        ]);
        $set->questions()->create([
            'type' => 'mcq',
            'question' => 'Choose the correct answer.',
            'options' => ['Wrong', 'Correct', 'Other', 'Last'],
            'correct_option' => 1,
            'marks' => 3,
            'position' => 10,
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Explain the first point.',
            'correct_answer' => 'SECRET REFERENCE ANSWER',
            'explanation' => 'Teacher-only explanation.',
            'marks' => 2,
            'position' => 20,
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Explain the second point.',
            'correct_answer' => 'A second reference answer.',
            'marks' => 2,
            'position' => 30,
        ]);
        $course->questionSets()->attach($set->id, [
            'section_title' => 'Module 1',
            'position' => 10,
            'max_attempts' => 3,
            'randomize_questions' => false,
        ]);

        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $mcq = $attempt->questions->firstWhere('type', 'mcq');
        $written = $attempt->questions->where('type', 'short_answer')->values();

        $this->post(route('student.question-sets.submit', [$course, $set, $attempt]), [
            'answers' => [
                $mcq->id => ['selected_option' => 1],
                $written[0]->id => ['answer_text' => 'My written response one.'],
                $written[1]->id => ['answer_text' => 'My written response two.'],
            ],
        ])->assertRedirect();

        $attempt->refresh()->load('questions');
        $this->assertSame('awaiting_manual', $attempt->status);

        return [$course, $set, $student, $teacher, $attempt];
    }
}
