<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\QuestionSet;
use App\Models\QuestionSetAttempt;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileStudentCourseQuizTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_start_save_and_submit_course_quiz_without_answer_key_leaks(): void
    {
        [$course, $set, $student] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/my/quizzes')
            ->assertOk()
            ->assertJsonPath('data.0.course_slug', $course->slug)
            ->assertJsonPath('data.0.quizzes.0.title', $set->title);

        $this->withToken($token)->getJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id)
            ->assertOk()
            ->assertJsonPath('data.quiz.title', $set->title)
            ->assertJsonPath('data.attempt', null);

        $started = $this->withToken($token)->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertCreated()
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonMissingPath('data.questions.0.correct_option')
            ->assertJsonMissingPath('data.questions.0.correct_answer')
            ->assertJsonMissingPath('data.questions.0.explanation');

        $attemptId = $started->json('data.attempt.id');
        $questions = $started->json('data.questions');
        $mcq = collect($questions)->firstWhere('type', 'mcq');
        $short = collect($questions)->firstWhere('type', 'short_answer');

        $answers = [
            $mcq['id'] => ['selected_option' => 1],
            $short['id'] => ['answer_text' => 'A short mobile quiz response.'],
        ];

        $this->withToken($token)->putJson('/api/v1/question-set-attempts/'.$attemptId.'/answers', [
            'answers' => $answers,
        ])->assertOk()->assertJsonPath('saved', true);

        $this->withToken($token)->postJson('/api/v1/question-set-attempts/'.$attemptId.'/submit', [
            'answers' => $answers,
        ])->assertOk()
            ->assertJsonPath('data.attempt.status', 'awaiting_manual')
            ->assertJsonPath('data.attempt.auto_score', 3)
            ->assertJsonPath('data.attempt.final_score', null)
            ->assertJsonPath('data.questions.1.answer_text', 'A short mobile quiz response.')
            ->assertJsonMissingPath('data.questions.0.correct_option');

        $this->withToken($token)->getJson('/api/v1/question-set-attempts/'.$attemptId)
            ->assertOk()
            ->assertJsonPath('data.attempt.status', 'awaiting_manual')
            ->assertJsonMissingPath('data.questions.0.correct_answer');

        $this->withToken($token)->postJson('/api/v1/question-set-attempts/'.$attemptId.'/submit')
            ->assertStatus(409);
    }

    public function test_quiz_api_rejects_unenrolled_students_and_non_student_roles(): void
    {
        [$course, $set, $student] = $this->fixture();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->withToken($this->token($otherStudent))->getJson('/api/v1/my/quizzes')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->token($otherStudent))
            ->getJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id)
            ->assertForbidden();

        $this->withToken($this->token($otherStudent))
            ->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertForbidden();

        $this->withToken($this->token($teacher))->getJson('/api/v1/my/quizzes')->assertForbidden();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/my/quizzes')->assertUnauthorized();

        $this->assertDatabaseCount('question_set_attempts', 0);
    }

    public function test_quiz_attempt_limit_is_enforced_and_expired_attempt_is_finalized(): void
    {
        [$course, $set, $student] = $this->fixture([
            'max_attempts' => 1,
            'time_limit_minutes' => 1,
        ]);
        $token = $this->token($student);

        $started = $this->withToken($token)->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertCreated();
        $attemptId = $started->json('data.attempt.id');

        $this->travel(2)->minutes();
        $this->withToken($token)->getJson('/api/v1/question-set-attempts/'.$attemptId)
            ->assertOk()
            ->assertJsonPath('data.attempt.status', 'awaiting_manual')
            ->assertJsonPath('data.attempt.auto_score', 0);

        $this->withToken($token)->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertForbidden();

        $this->assertDatabaseCount('question_set_attempts', 1);
    }

    public function test_student_cannot_submit_foreign_question_ids_or_access_another_attempt(): void
    {
        [$course, $set, $student] = $this->fixture();
        $token = $this->token($student);
        $started = $this->withToken($token)->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertCreated();
        $attemptId = $started->json('data.attempt.id');

        $this->withToken($token)->putJson('/api/v1/question-set-attempts/'.$attemptId.'/answers', [
            'answers' => [999999 => ['selected_option' => 0]],
        ])->assertUnprocessable();

        $other = User::factory()->create(['role' => 'student']);
        $this->withToken($this->token($other))
            ->getJson('/api/v1/question-set-attempts/'.$attemptId)
            ->assertNotFound();

        $this->assertDatabaseCount('question_set_attempt_questions', 2);
    }

    public function test_quiz_cannot_start_before_scheduled_availability(): void
    {
        [$course, $set, $student] = $this->fixture([
            'available_from' => now()->addDay(),
        ]);

        $this->withToken($this->token($student))
            ->postJson('/api/v1/courses/'.$course->slug.'/question-sets/'.$set->id.'/start')
            ->assertForbidden();

        $this->assertDatabaseCount('question_set_attempts', 0);
    }

    private function fixture(array $pivot = []): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'published',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $set = QuestionSet::create([
            'title' => 'Mobile Grammar Quiz',
            'description' => 'A mobile course assessment.',
            'status' => 'published',
        ]);
        $set->questions()->create([
            'type' => 'mcq',
            'question' => 'Choose the correct option.',
            'options' => ['Wrong', 'Correct', 'Other', 'Last'],
            'correct_option' => 1,
            'correct_answer' => null,
            'explanation' => 'Do not reveal before submission.',
            'marks' => 3,
            'position' => 10,
        ]);
        $set->questions()->create([
            'type' => 'short_answer',
            'question' => 'Write one sentence.',
            'options' => null,
            'correct_option' => null,
            'correct_answer' => 'Private model answer.',
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

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = null;
        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Mobile Quiz API test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Mobile Quiz API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
