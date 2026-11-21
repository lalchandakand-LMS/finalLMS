<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Exam;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileInstructorExamAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_publish_an_assessment_with_questions_for_their_own_batch(): void
    {
        [$teacher, $batch] = $this->fixture();
        $response = $this->withToken($this->token($teacher))->postJson(
            '/api/v1/instructor/batches/'.$batch->id.'/exams',
            $this->payload()
        );

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Mobile vocabulary quiz')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.questions_count', 2);

        $exam = Exam::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame(1, $exam->questions()->orderBy('position')->first()->position);
    }

    public function test_instructor_cannot_create_an_assessment_for_another_teachers_batch(): void
    {
        [$teacher, $batch] = $this->fixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherBatch = Batch::create([
            'branch_id' => $batch->branch_id,
            'course_id' => $batch->course_id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Private batch',
            'capacity' => 10,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        $this->withToken($this->token($teacher))
            ->postJson('/api/v1/instructor/batches/'.$otherBatch->id.'/exams', $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('exams', 0);
    }

    public function test_assessment_authoring_rejects_invalid_pass_marks_and_malformed_questions(): void
    {
        [$teacher, $batch] = $this->fixture();
        $payload = $this->payload();
        $payload['pass_marks'] = 99;

        $this->withToken($this->token($teacher))
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/exams', $payload)
            ->assertUnprocessable();

        $payload = $this->payload();
        $payload['questions'][0]['options'] = ['Only one option'];

        $this->withToken($this->token($teacher))
            ->postJson('/api/v1/instructor/batches/'.$batch->id.'/exams', $payload)
            ->assertUnprocessable();

        $this->assertDatabaseCount('exams', 0);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Assessment Course',
            'slug' => 'mobile-assessment-course',
            'description' => 'Mobile assessment API tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Mobile Assessment Branch', 'slug' => 'mobile-assessment-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Assessment Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);

        return [$teacher, $batch];
    }

    private function payload(): array
    {
        return [
            'title' => 'Mobile vocabulary quiz',
            'instructions' => 'Choose one correct answer.',
            'starts_at' => now()->addDay()->toIso8601String(),
            'ends_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 20,
            'pass_marks' => 2,
            'questions_per_attempt' => 1,
            'questions' => [
                [
                    'question' => 'Choose the correct translation of hello.',
                    'options' => ['হ্যালো', 'বিদায়', 'ধন্যবাদ', 'দুঃখিত'],
                    'correct_option' => 0,
                    'marks' => 2,
                    'negative_marks' => 0,
                ],
                [
                    'question' => 'Choose the opposite of hot.',
                    'options' => ['Warm', 'Cold', 'Boiling', 'Heat'],
                    'correct_option' => 1,
                    'marks' => 2,
                    'negative_marks' => 1,
                ],
            ],
        ];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $user->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Instructor authoring test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $user->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => 'Instructor authoring test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
