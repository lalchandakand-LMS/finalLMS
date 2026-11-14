<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionSetAttemptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_complete_timed_mcq_quiz_and_view_automatic_score_without_answer_key_leak(): void
    {
        [$teacher, $student, $course, $set] = $this->makeCourseQuiz([
            ['type' => 'mcq', 'question' => 'Past tense of go?', 'options' => ['go', 'went', 'gone', 'going'], 'correct_option' => 1, 'marks' => 2],
            ['type' => 'true_false', 'question' => 'A noun names a thing.', 'options' => ['True', 'False'], 'correct_option' => 0, 'marks' => 1],
        ], ['time_limit_minutes' => 15, 'max_attempts' => 2, 'randomize_questions' => false]);

        $this->actingAs($student)->get(route('student.question-sets.show', [$course, $set]))
            ->assertOk()->assertSee('Start quiz');

        $this->post(route('student.question-sets.attempts.start', [$course, $set]))
            ->assertRedirect();
        $attempt = QuestionSetAttempt::firstOrFail();
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNotNull($attempt->deadline_at);
        $this->assertSame(3, $attempt->max_score);

        $this->get(route('student.question-sets.attempts.show', [$course, $set, $attempt]))
            ->assertOk()->assertSee('Past tense of go?')->assertDontSee('correct_option');

        $questions = $attempt->questions()->orderBy('position')->get();
        $this->post(route('student.question-sets.attempts.submit', [$course, $set, $attempt]), [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 0],
        ])->assertRedirect(route('student.question-sets.attempts.result', [$course, $set, $attempt]));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(3, $attempt->auto_score);
        $this->assertSame(3, $attempt->final_score);
        $this->get(route('student.question-sets.attempts.result', [$course, $set, $attempt]))
            ->assertOk()->assertSee('3 / 3')->assertDontSee('correct_option');
    }

    public function test_short_answers_wait_for_teacher_review_and_final_score_is_published_after_grading(): void
    {
        [$teacher, $student, $course, $set] = $this->makeCourseQuiz([
            ['type' => 'mcq', 'question' => 'Choose B.', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 1, 'marks' => 2],
            ['type' => 'short_answer', 'question' => 'Write the plural of child.', 'correct_answer' => 'children', 'marks' => 3],
        ], ['randomize_questions' => false]);

        $this->actingAs($student)->post(route('student.question-sets.attempts.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();
        $questions = $attempt->questions()->orderBy('position')->get();
        $this->post(route('student.question-sets.attempts.submit', [$course, $set, $attempt]), [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 'children'],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('pending_review', $attempt->status);
        $this->assertSame(2, $attempt->auto_score);
        $this->assertNull($attempt->final_score);

        $this->actingAs($teacher)->get(route('question-sets.review.index', [$course, $set]))
            ->assertOk()->assertSee($student->name);
        $this->get(route('question-sets.review.show', [$course, $set, $attempt]))
            ->assertOk()->assertSee('Expected answer');

        $shortQuestion = $questions->firstWhere('type', 'short_answer');
        $this->post(route('question-sets.review.grade', [$course, $set, $attempt]), [
            'scores' => [$shortQuestion->id => 3],
            'question_feedback' => [$shortQuestion->id => 'Correct.'],
            'feedback' => 'Good work.',
        ])->assertRedirect(route('question-sets.review.index', [$course, $set]));

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(3, $attempt->manual_score);
        $this->assertSame(5, $attempt->final_score);
        $this->assertSame($teacher->id, $attempt->graded_by);
    }

    public function test_student_cannot_start_quiz_without_enrollment_or_read_another_students_attempt(): void
    {
        [$teacher, $student, $course, $set, $otherStudent] = $this->makeCourseQuiz([
            ['type' => 'mcq', 'question' => 'Pick A.', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 0, 'marks' => 1],
        ], [], true);

        $this->actingAs($otherStudent)
            ->post(route('student.question-sets.attempts.start', [$course, $set]))
            ->assertForbidden();
        $this->assertDatabaseCount('question_set_attempts', 0);

        $this->actingAs($student)->post(route('student.question-sets.attempts.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();
        $this->actingAs($otherStudent)
            ->get(route('student.question-sets.attempts.show', [$course, $set, $attempt]))
            ->assertNotFound();
    }

    public function test_max_attempt_limit_is_enforced_and_expired_attempt_ignores_late_answers(): void
    {
        [$teacher, $student, $course, $set] = $this->makeCourseQuiz([
            ['type' => 'mcq', 'question' => 'Pick A.', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 0, 'marks' => 1],
        ], ['time_limit_minutes' => 1, 'max_attempts' => 1, 'randomize_questions' => false]);

        $this->actingAs($student)->post(route('student.question-sets.attempts.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();
        $question = $attempt->questions()->firstOrFail();

        $this->travelTo($attempt->deadline_at->copy()->addSecond());
        $this->post(route('student.question-sets.attempts.submit', [$course, $set, $attempt]), [
            'answers' => [$question->id => 0],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('graded', $attempt->status);
        $this->assertSame(0, $attempt->auto_score);
        $this->assertNull($attempt->questions()->first()->selected_option);

        $this->from(route('student.question-sets.show', [$course, $set]))
            ->post(route('student.question-sets.attempts.start', [$course, $set]))
            ->assertSessionHasErrors('attempt');
        $this->assertDatabaseCount('question_set_attempts', 1);
    }

    public function test_question_count_limit_is_applied_and_archived_set_keeps_existing_result_history(): void
    {
        [$teacher, $student, $course, $set] = $this->makeCourseQuiz([
            ['type' => 'mcq', 'question' => 'Question one?', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 0, 'marks' => 1],
            ['type' => 'mcq', 'question' => 'Question two?', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 1, 'marks' => 2],
            ['type' => 'mcq', 'question' => 'Question three?', 'options' => ['A', 'B', 'C', 'D'], 'correct_option' => 2, 'marks' => 3],
        ], ['questions_per_attempt' => 2, 'randomize_questions' => false]);

        $this->actingAs($student)->post(route('student.question-sets.attempts.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();
        $this->assertCount(2, $attempt->questions);

        $questions = $attempt->questions()->orderBy('position')->get();
        $this->post(route('student.question-sets.attempts.submit', [$course, $set, $attempt]), [
            'answers' => [$questions[0]->id => 0, $questions[1]->id => 1],
        ])->assertRedirect();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('admin.question-sets.destroy', $set))->assertRedirect();
        $this->assertDatabaseHas('question_sets', ['id' => $set->id, 'status' => 'archived']);
        $this->assertDatabaseHas('question_set_attempts', ['id' => $attempt->id]);

        $this->actingAs($student)->get(route('student.question-sets.attempts.result', [$course, $set, $attempt]))
            ->assertOk()->assertSee('Answer summary');
        $this->get(route('student.question-sets.show', [$course, $set]))->assertNotFound();
    }

    public function test_teacher_can_only_review_question_sets_attached_to_their_own_courses(): void
    {
        [$teacher, $student, $course, $set] = $this->makeCourseQuiz([
            ['type' => 'short_answer', 'question' => 'Explain a noun.', 'correct_answer' => 'A naming word.', 'marks' => 2],
        ]);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->get(route('question-sets.review.index', [$course, $set]))
            ->assertForbidden();
    }

    private function makeCourseQuiz(array $questions, array $pivot = [], bool $includeOtherStudent = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $set = QuestionSet::create([
            'title' => 'Grammar assessment',
            'description' => 'Test your grammar.',
            'status' => 'published',
            'created_by' => $teacher->id,
        ]);
        foreach ($questions as $index => $question) {
            $set->questions()->create($question + ['difficulty' => 'easy', 'position' => ($index + 1) * 10]);
        }
        $course->questionSets()->attach($set->id, array_merge([
            'section_title' => 'Module 1',
            'position' => 10,
            'max_attempts' => null,
            'time_limit_minutes' => null,
            'questions_per_attempt' => null,
            'randomize_questions' => false,
            'available_from' => null,
        ], $pivot));

        if ($includeOtherStudent) {
            return [$teacher, $student, $course, $set, $otherStudent];
        }

        return [$teacher, $student, $course, $set];
    }
}
