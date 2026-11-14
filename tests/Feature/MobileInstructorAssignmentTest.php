<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileInstructorAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_instructor_can_create_assignments_review_submissions_and_grade_them(): void
    {
        [$teacher, $batch, $student] = $this->fixture();
        $token = $this->token($teacher);

        $this->withToken($token)->getJson('/api/v1/instructor/batches')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $batch->id);

        $created = $this->withToken($token)->postJson('/api/v1/instructor/batches/'.$batch->id.'/assignments', [
            'title' => 'Mobile instructor assignment',
            'instructions' => 'Write a short paragraph.',
            'due_at' => now()->addDays(3)->toIso8601String(),
            'allow_late' => false,
            'max_marks' => 25,
        ])->assertCreated()
            ->assertJsonPath('assignment.status', 'published')
            ->assertJsonPath('assignment.max_marks', 25);

        $assignmentId = $created->json('assignment.id');
        $assignment = Assignment::findOrFail($assignmentId);

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Student answer from mobile.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->withToken($token)->getJson('/api/v1/instructor/assignments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.submissions_count', 1);

        $this->withToken($token)->getJson('/api/v1/instructor/assignments/'.$assignment->id.'/submissions')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.student.id', $student->id)
            ->assertJsonPath('data.0.answer', 'Student answer from mobile.');

        $this->withToken($token)->patchJson('/api/v1/instructor/assignments/'.$assignment->id.'/submissions/'.$submission->id, [
            'score' => 22,
            'feedback' => 'Good work.',
        ])->assertOk()
            ->assertJsonPath('submission.status', 'graded')
            ->assertJsonPath('submission.score', '22.00')
            ->assertJsonPath('submission.feedback', 'Good work.');

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'graded_by' => $teacher->id,
            'status' => 'graded',
            'feedback' => 'Good work.',
        ]);
    }

    public function test_instructor_cannot_access_or_manage_another_teachers_batch_or_assignment(): void
    {
        [$teacher, $batch, $student] = $this->fixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $assignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Owned assignment',
            'instructions' => 'Instructions.',
            'due_at' => now()->addDay(),
            'allow_late' => false,
            'max_marks' => 10,
            'status' => 'published',
        ]);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $otherToken = $this->token($otherTeacher);
        $this->withToken($otherToken)->postJson('/api/v1/instructor/batches/'.$batch->id.'/assignments', [
            'title' => 'Unauthorized',
            'instructions' => 'Must not create.',
            'due_at' => now()->addDay()->toIso8601String(),
            'max_marks' => 10,
        ])->assertNotFound();

        $this->withToken($otherToken)->getJson('/api/v1/instructor/assignments/'.$assignment->id.'/submissions')
            ->assertNotFound();
        $this->withToken($otherToken)->patchJson('/api/v1/instructor/assignments/'.$assignment->id.'/submissions/'.$submission->id, [
            'score' => 8,
        ])->assertNotFound();

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'submitted']);
    }

    public function test_student_cannot_use_instructor_assignment_endpoints_and_grades_are_bounded(): void
    {
        [$teacher, $batch, $student] = $this->fixture();
        $assignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Grading bounds',
            'instructions' => 'Instructions.',
            'due_at' => now()->addDay(),
            'allow_late' => false,
            'max_marks' => 10,
            'status' => 'published',
        ]);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Answer',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $studentToken = $this->token($student);
        $this->withToken($studentToken)->getJson('/api/v1/instructor/batches')->assertForbidden();

        $teacherToken = $this->token($teacher);
        $this->withToken($teacherToken)->patchJson('/api/v1/instructor/assignments/'.$assignment->id.'/submissions/'.$submission->id, [
            'score' => 11,
        ])->assertUnprocessable();

        $this->withToken($teacherToken)->postJson('/api/v1/instructor/batches/'.$batch->id.'/assignments', [
            'title' => 'Invalid due date',
            'instructions' => 'Instructions.',
            'due_at' => now()->subDay()->toIso8601String(),
            'max_marks' => 10,
        ])->assertUnprocessable();
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Instructor Mobile Course',
            'slug' => 'instructor-mobile-course',
            'description' => 'Course for instructor mobile tests.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Instructor Mobile Branch', 'slug' => 'instructor-mobile-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Instructor Mobile Batch',
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

        return [$teacher, $batch, $student];
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = null;
        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Instructor assignment test device',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        $user->apiTokens()->create([
            'student_device_id' => $device?->id,
            'name' => 'Instructor assignment test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
