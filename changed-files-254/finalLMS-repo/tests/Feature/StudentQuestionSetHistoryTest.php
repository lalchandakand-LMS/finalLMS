<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuestionSetHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_review_attempt_history_after_course_and_question_set_are_archived(): void
    {
        [$course, $set, $student, $enrollment, $attempt] = $this->fixture();

        $set->update(['status' => 'archived']);
        $course->update(['status' => 'archived']);
        $enrollment->update(['status' => 'cancelled']);

        $this->actingAs($student)
            ->get(route('student.question-set-attempts.index'))
            ->assertOk()
            ->assertSee('Your quiz attempts')
            ->assertSee('Grammar history quiz')
            ->assertSee('Archived grammar course')
            ->assertSee('Awaiting teacher review')
            ->assertSee('View attempt');

        $this->get(route('student.question-sets.show', [$course, $set, $attempt]))
            ->assertOk()
            ->assertSee('Awaiting teacher review')
            ->assertSee('My historical response.');
    }

    public function test_quiz_history_is_private_to_the_authenticated_student(): void
    {
        [$course, $set, $student] = $this->fixture();
        $otherStudent = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('student.question-set-attempts.index'))
            ->assertOk()
            ->assertSee('Grammar history quiz');

        $this->actingAs($otherStudent)
            ->get(route('student.question-set-attempts.index'))
            ->assertOk()
            ->assertDontSee('Grammar history quiz');
    }

    public function test_teacher_cannot_access_student_quiz_history_page(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('student.question-set-attempts.index'))
            ->assertForbidden();
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create([
            'title' => 'Archived grammar course',
            'teacher_id' => $teacher->id,
            'status' => 'published',
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $set = QuestionSet::create([
            'title' => 'Grammar history quiz',
            'status' => 'published',
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Write a sentence using a noun.',
            'correct_answer' => 'Reference only',
            'marks' => 2,
            'position' => 10,
        ]);
        $course->questionSets()->attach($set->id, [
            'section_title' => 'Module 1',
            'position' => 10,
            'max_attempts' => 3,
            'randomize_questions' => false,
        ]);

        $this->actingAs($student)->post(route('student.question-sets.start', [$course, $set]));
        $attempt = QuestionSetAttempt::with('questions')->firstOrFail();
        $question = $attempt->questions->first();

        $this->post(route('student.question-sets.submit', [$course, $set, $attempt]), [
            'answers' => [
                $question->id => ['answer_text' => 'My historical response.'],
            ],
        ])->assertRedirect();

        return [$course, $set, $student, $enrollment, $attempt->fresh()];
    }
}
