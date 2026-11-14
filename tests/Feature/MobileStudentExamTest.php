<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileStudentExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_start_save_and_submit_exam_without_receiving_answer_key(): void
    {
        [$student, $exam] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/my/exams')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $exam->id);

        $this->withToken($token)->getJson('/api/v1/exams/'.$exam->id)
            ->assertOk()
            ->assertJsonPath('data.id', $exam->id)
            ->assertJsonCount(0, 'data.questions');

        $start = $this->withToken($token)->postJson('/api/v1/exams/'.$exam->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.attempt.status', 'in_progress')
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonPath('data.attempt.status', 'in_progress')
            ->assertJsonStructure(['data' => ['deadline']])
            ->assertJsonMissingPath('data.questions.0.correct_option');

        $questions = $start->json('data.questions');
        $correctId = (string) $exam->questions()->where('correct_option', 1)->value('id');
        $this->assertContains($correctId, array_map(fn ($q) => (string) $q['id'], $questions));

        $this->withToken($token)->putJson('/api/v1/exam-attempts/'.$start->json('data.attempt.id').'/answers', [
            'answers' => [$correctId => 1],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->withToken($token)->postJson('/api/v1/exam-attempts/'.$start->json('data.attempt.id').'/submit', [
            'answers' => [$correctId => 1],
        ])->assertOk()->assertJsonPath('data.score', 5)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.passed', true);

        $this->withToken($token)->postJson('/api/v1/exam-attempts/'.$start->json('data.attempt.id').'/submit')
            ->assertStatus(409);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $start->json('data.attempt.id'),
            'student_id' => $student->id,
            'score' => 5,
            'status' => 'submitted',
        ]);
    }

    public function test_exam_api_rejects_unenrolled_students_and_non_student_roles(): void
    {
        [$student, $exam] = $this->fixture();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->withToken($this->token($otherStudent))->getJson('/api/v1/exams/'.$exam->id)->assertForbidden();
        $this->withToken($this->token($otherStudent))->postJson('/api/v1/exams/'.$exam->id.'/start')->assertForbidden();
        $this->withToken($this->token($teacher))->getJson('/api/v1/my/exams')->assertForbidden();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/my/exams')->assertUnauthorized();

        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_student_cannot_save_or_submit_answers_for_another_attempt_or_unassigned_question(): void
    {
        [$student, $exam] = $this->fixture();
        $token = $this->token($student);
        $started = $this->withToken($token)->postJson('/api/v1/exams/'.$exam->id.'/start')->assertOk();
        $attemptId = $started->json('data.attempt.id');

        $this->withToken($token)->putJson('/api/v1/exam-attempts/'.$attemptId.'/answers', [
            'answers' => [999999 => 0],
        ])->assertUnprocessable();

        $other = User::factory()->create(['role' => 'student']);
        $this->withToken($this->token($other))->putJson('/api/v1/exam-attempts/'.$attemptId.'/answers', [
            'answers' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('exam_answers', 0);
    }


    public function test_student_cannot_resume_an_attempt_after_its_server_deadline(): void
    {
        [$student, $exam] = $this->fixture();
        $token = $this->token($student);
        $started = $this->withToken($token)->postJson('/api/v1/exams/'.$exam->id.'/start')->assertOk();
        $attemptId = $started->json('data.attempt.id');

        \App\Models\ExamAttempt::query()->whereKey($attemptId)->update([
            'started_at' => now()->subMinutes(31),
        ]);

        $this->withToken($token)->postJson('/api/v1/exams/'.$exam->id.'/start')
            ->assertStatus(409);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Exam Course',
            'slug' => 'mobile-exam-course',
            'description' => 'Course for mobile exam tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Exam Branch', 'slug' => 'exam-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Exam Batch',
            'capacity' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $exam = Exam::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Mobile API Exam',
            'instructions' => 'Choose the correct answer.',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'duration_minutes' => 30,
            'pass_marks' => 3,
            'status' => 'published',
        ]);
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => 'One plus one?',
            'options' => ['1', '2', '3', '4'],
            'correct_option' => 1,
            'marks' => 5,
            'position' => 1,
        ]);
        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => 'Two plus two?',
            'options' => ['3', '4', '5', '6'],
            'correct_option' => 1,
            'marks' => 2,
            'position' => 2,
        ]);

        return [$student, $exam];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = null;
        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Exam API test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }
        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Exam API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
