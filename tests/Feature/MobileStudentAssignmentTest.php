<?php

namespace Tests\\Feature;

use App\\Models\\ApiToken;
use App\\Models\\Assignment;
use App\\Models\\Batch;
use App\\Models\\Branch;
use App\\Models\\Course;
use App\\Models\\Enrollment;
use App\\Models\\StudentDevice;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class MobileStudentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_list_view_and_submit_assignment_once(): void
    {
        [$student, $batch, $assignment] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/my/assignments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.course', $batch->course->title);

        $this->withToken($token)->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('assignment.id', $assignment->id)
            ->assertJsonPath('assignment.instructions', $assignment->instructions)
            ->assertJsonPath('assignment.submission', null);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'My mobile assignment answer.',
        ])->assertCreated()
            ->assertJsonPath('submission.status', 'submitted')
            ->assertJsonPath('submission.answer', 'My mobile assignment answer.');

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'Duplicate answer.',
        ])->assertStatus(409);

        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'My mobile assignment answer.',
            'status' => 'submitted',
        ]);
    }

    public function test_assignment_api_is_student_scoped_and_rejects_unenrolled_users(): void
    {
        [$student, , $assignment] = $this->fixture();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $studentToken = $this->token($student);
        $otherToken = $this->token($otherStudent);
        $teacherToken = $this->token($teacher);

        $this->withToken($otherToken)->getJson('/api/v1/my/assignments')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->withToken($otherToken)->getJson('/api/v1/assignments/'.$assignment->id)->assertForbidden();
        $this->withToken($otherToken)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'Unauthorized',
        ])->assertForbidden();

        $this->withToken($teacherToken)->getJson('/api/v1/my/assignments')->assertForbidden();
        $this->withToken($studentToken)->getJson('/api/v1/assignments/'.$assignment->id)->assertOk();
        $this->getJson('/api/v1/my/assignments')->assertUnauthorized();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_assignment_api_validates_answers_and_enforces_late_submission_policy(): void
    {
        [$student, , $assignment] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => '',
        ])->assertUnprocessable();

        $assignment->update(['due_at' => now()->subMinute(), 'allow_late' => false]);
        $this->withToken($token)->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertOk()->assertJsonPath('assignment.is_closed', true);
        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'Late answer',
        ])->assertStatus(409);

        $assignment->update(['allow_late' => true]);
        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'Allowed late answer',
        ])->assertCreated();

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Allowed late answer',
        ]);
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Assignment Course',
            'slug' => 'mobile-assignment-course',
            'description' => 'Course for mobile assignment tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Mobile Branch', 'slug' => 'mobile-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Mobile Batch',
            'capacity' => 20,
            'starts_at' => now(),
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
        $assignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Mobile homework',
            'instructions' => 'Write a short answer.',
            'due_at' => now()->addDay(),
            'allow_late' => false,
            'max_marks' => 10,
            'status' => 'published',
        ]);

        return [$student, $batch->load('course'), $assignment];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = null;
        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Assignment test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Assignment API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
