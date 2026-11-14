<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuestionSetAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_start_and_view_a_question_snapshot_without_answer_keys(): void
    {
        [$course, $set, $student] = $this->fixture();

        $response = $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();

        $response->assertRedirect(route('student.question-sets.show', [$course, $set, $attempt]));
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame(5, $attempt->max_score);
        $this->assertCount(2, $attempt->questions);

        $this->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('Choose the correct answer')
            ->assertSee('Write a short sentence')
            ->assertDontSee('SECRET ANSWER KEY')
            ->assertDontSee('SECRET EXPLANATION');
    }

    public function test_submission_auto_grades_objective_questions_and_waits_for_written_answer_review(): void
    {
        [$course, $set, $student] = $this->fixture();
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $mcq = $attempt->questions->firstWhere('type', 'mcq');
        $shortAnswer = $attempt->questions->firstWhere('type', 'short_answer');

        $this->post(route('student.question-sets.submit', [$course, $set, $attempt]), [
            'answers' => [
                $mcq->id => ['selected_option' => 1],
                $shortAnswer->id => ['answer_text' => 'My written response.'],
            ],
        ])->assertRedirect(route('student.question-sets.show', [$course, $set, $attempt]));

        $attempt->refresh()->load('questions');
        $this->assertSame('awaiting_manual', $attempt->status);
        $this->assertSame(3, $attempt->auto_score);
        $this->assertNull($attempt->final_score);
        $this->assertSame('My written response.', $attempt->questions->firstWhere('id', $shortAnswer->id)->answer_text);
        $this->assertTrue($attempt->questions->firstWhere('id', $mcq->id)->is_correct);

        $this->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('Awaiting teacher review')
            ->assertSee('3 / 5')
            ->assertDontSee('SECRET ANSWER KEY');
    }

    public function test_student_cannot_access_another_students_attempt_or_start_without_enrollment(): void
    {
        [$course, $set, $student] = $this->fixture();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();

        $this->actingAs($otherStudent)
            ->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertNotFound();

        $otherCourse = Course::factory()->create(['status' => 'published']);
        $otherSet = QuestionSet::create(['title' => 'Other quiz', 'status' => 'published']);
        $otherSet->questions()->create([
            'type' => 'mcq',
            'question' => 'Other question?',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_option' => 0,
            'marks' => 1,
            'position' => 10,
        ]);
        $otherCourse->questionSets()->attach($otherSet->id, ['position' => 10, 'randomize_questions' => false]);

        $this->post(route('student.question-sets.start', [$otherCourse, $otherSet]))->assertForbidden();
        $this->assertDatabaseCount('question_set_attempts', 1);
    }

    public function test_attempt_limit_is_enforced_and_expired_attempt_is_finalized(): void
    {
        [$course, $set, $student] = $this->fixture([
            'max_attempts' => 1,
            'time_limit_minutes' => 1,
        ]);

        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::firstOrFail();
        $this->travel(2)->minutes();

        $this->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('Quiz result');
        $this->assertSame('submitted', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->submitted_at);

        $this->post(route('student.question-sets.start', [$course, $set]))->assertForbidden();
        $this->assertDatabaseCount('question_set_attempts', 1);
    }

    public function test_student_cannot_submit_question_ids_from_outside_the_attempt(): void
    {
        [$course, $set, $student] = $this->fixture();
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $foreignId = $attempt->questions->last()->id + 1000;

        $this->from(route('student.question-sets.show', [$course, $set, $attempt]))
            ->post(route('student.question-sets.submit', [$course, $set, $attempt]), [
                'answers' => [$foreignId => ['selected_option' => 0]],
            ])
            ->assertSessionHasErrors('answers');

        $this->assertSame('in_progress', $attempt->fresh()->status);
    }

    private function fixture(array $pivot = []): array
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
            'title' => 'Grammar assessment',
            'description' => 'A short assessment.',
            'status' => 'published',
        ]);
        $set->questions()->create([
            'type' => 'mcq',
            'question' => 'Choose the correct answer',
            'options' => ['Wrong', 'Correct', 'Other', 'Last'],
            'correct_option' => 1,
            'correct_answer' => null,
            'explanation' => 'SECRET EXPLANATION',
            'marks' => 3,
            'position' => 10,
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Write a short sentence',
            'options' => null,
            'correct_option' => null,
            'correct_answer' => 'SECRET ANSWER KEY',
            'marks' => 2,
            'position' => 20,
        ]);
        $course->questionSets()->attach($set->id, array_merge([
            'section_title' => 'Module 1',
            'position' => 10,
            'max_attempts' => null,
            'time_limit_minutes' => null,
            'questions_per_attempt' => null,
            'randomize_questions' => false,
            'available_from' => null,
        ], $pivot));

        return [$course, $set, $student];
    }
}
