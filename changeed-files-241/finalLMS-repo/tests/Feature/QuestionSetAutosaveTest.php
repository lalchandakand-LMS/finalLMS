<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionSetAutosaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_answers_are_autosaved_restored_and_used_when_timed_attempt_expires(): void
    {
        [$course, $set, $student] = $this->fixture();
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $mcq = $attempt->questions->firstWhere('type', 'mcq');
        $written = $attempt->questions->firstWhere('type', 'short_answer');

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [
                $mcq->id => ['selected_option' => 1],
                $written->id => ['answer_text' => 'My saved written response.'],
            ],
        ])->assertOk()->assertJsonPath('saved', true);

        $this->assertDatabaseHas('question_set_attempt_questions', [
            'id' => $mcq->id,
            'selected_option' => 1,
        ]);
        $this->assertDatabaseHas('question_set_attempt_questions', [
            'id' => $written->id,
            'answer_text' => 'My saved written response.',
        ]);

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [$written->id => ['answer_text' => 'Updated draft response.']],
        ])->assertOk()->assertJsonPath('saved', true);
        $this->assertSame(1, $mcq->fresh()->selected_option);
        $this->assertSame('Updated draft response.', $written->fresh()->answer_text);

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [
                $mcq->id => ['selected_option' => 1],
                $written->id => ['answer_text' => 'My saved written response.'],
            ],
        ])->assertOk();

        $this->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('My saved written response.')
            ->assertSee('checked', false)
            ->assertDontSee('SECRET ANSWER KEY');

        $this->travelTo($attempt->deadline_at->copy()->addSecond());
        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [
                $mcq->id => ['selected_option' => 0],
                $written->id => ['answer_text' => 'Late answer should not replace saved response.'],
            ],
        ])->assertStatus(409)->assertJsonPath('expired', true);

        $attempt->refresh();
        $this->assertSame('awaiting_manual', $attempt->status);
        $this->assertSame(3, $attempt->auto_score);
        $this->assertSame(1, $attempt->questions()->whereKey($mcq->id)->value('selected_option'));
        $this->assertSame('My saved written response.', $attempt->questions()->whereKey($written->id)->value('answer_text'));
    }

    public function test_autosave_rejects_question_ids_outside_the_attempt_and_invalid_options(): void
    {
        [$course, $set, $student] = $this->fixture();
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $mcq = $attempt->questions->firstWhere('type', 'mcq');
        $foreignId = $mcq->id + 999;

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [$foreignId => ['selected_option' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers');

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [$mcq->id => ['selected_option' => 9]],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers.'.$mcq->id.'.selected_option');

        $this->assertSame('in_progress', $attempt->fresh()->status);
        $this->assertNull($mcq->fresh()->selected_option);
    }

    public function test_autosave_is_private_to_attempt_owner_and_stops_after_submission(): void
    {
        [$course, $set, $student] = $this->fixture();
        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $mcq = $attempt->questions->firstWhere('type', 'mcq');
        $otherStudent = User::factory()->create(['role' => 'student']);

        $this->actingAs($otherStudent)
            ->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
                'answers' => [$mcq->id => ['selected_option' => 1]],
            ])->assertNotFound();

        $this->actingAs($student)->post(route('student.question-sets.submit', [$course, $set, $attempt]), [
            'answers' => [$mcq->id => ['selected_option' => 1]],
        ])->assertRedirect();

        $this->postJson(route('student.question-sets.save', [$course, $set, $attempt]), [
            'answers' => [$mcq->id => ['selected_option' => 0]],
        ])->assertStatus(409);
        $this->assertSame(1, $mcq->fresh()->selected_option);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);

        $set = QuestionSet::create(['title' => 'Timed grammar quiz', 'status' => 'published']);
        $set->questions()->create([
            'type' => 'mcq',
            'question' => 'Choose the correct answer.',
            'options' => ['Wrong', 'Correct', 'Other', 'Last'],
            'correct_option' => 1,
            'explanation' => 'SECRET EXPLANATION',
            'marks' => 3,
            'position' => 10,
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Write a sentence.',
            'correct_answer' => 'SECRET ANSWER KEY',
            'marks' => 2,
            'position' => 20,
        ]);
        $course->questionSets()->attach($set->id, [
            'position' => 10,
            'max_attempts' => 1,
            'time_limit_minutes' => 1,
            'questions_per_attempt' => null,
            'randomize_questions' => false,
            'available_from' => null,
        ]);

        return [$course, $set, $student];
    }
}
