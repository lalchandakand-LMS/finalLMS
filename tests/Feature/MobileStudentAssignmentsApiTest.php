<?php

namespace Tests\Feature;

use App\Models\ApiToken;
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

class MobileStudentAssignmentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_only_assignments_from_active_enrollments_with_own_submission(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->fixture();
        $other = User::factory()->create(['role' => 'student']);
        $otherCourse = Course::factory()->create(['teacher_id' => User::factory()->create(['role' => 'teacher'])->id]);
        $otherBranch = Branch::create(['name' => 'Other branch', 'slug' => 'other-branch']);
        $otherBatch = Batch::create([
            'branch_id' => $otherBranch->id,
            'course_id' => $otherCourse->id,
            'teacher_id' => $otherCourse->teacher_id,
            'title' => 'Other batch',
            'capacity' => 10,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        $otherAssignment = $otherBatch->assignments()->create([
            'teacher_id' => $otherCourse->teacher_id,
            'title' => 'Private assignment',
            'instructions' => 'Not for this student.',
            'due_at' => now()->addDay(),
            'max_marks' => 10,
            'status' => 'published',
        ]);
        Enrollment::create([
            'user_id' => $other->id,
            'course_id' => $otherCourse->id,
            'batch_id' => $otherBatch->id,
            'status' => 'active',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'My draft answer.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/my/assignments')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.submission.answer', 'My draft answer.')
            ->assertJsonMissing(['title' => $otherAssignment->title]);
    }

    public function test_student_can_submit_answer_once_and_cannot_resubmit_until_graded(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->fixture();
        $token = $this->token($student);
        $url = '/api/v1/assignments/'.$assignment->id.'/submission';

        $this->withToken($token)->postJson($url, ['answer' => 'My completed answer.'])
            ->assertCreated()
            ->assertJsonPath('submission.answer', 'My completed answer.')
            ->assertJsonPath('submission.status', 'submitted');

        $this->withToken($token)->postJson($url, ['answer' => 'Duplicate answer.'])
            ->assertStatus(409);

        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'My completed answer.',
        ]);
    }

    public function test_graded_submission_can_be_revised_and_closed_or_late_assignments_are_rejected(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->fixture();
        $token = $this->token($student);
        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Old answer.',
            'submitted_at' => now()->subDay(),
            'status' => 'graded',
            'score' => 5,
            'feedback' => 'Please revise.',
        ]);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submission', [
            'answer' => 'Revised answer.',
        ])->assertCreated()->assertJsonPath('submission.answer', 'Revised answer.');

        $this->assertSame('submitted', $submission->fresh()->status);
        $this->assertNull($submission->fresh()->score);
        $assignment->update(['due_at' => now()->subMinute(), 'allow_late' => false]);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submission', [
            'answer' => 'Too late.',
        ])->assertStatus(409);

        $assignment->update(['status' => 'closed', 'due_at' => now()->addDay()]);
        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submission', [
            'answer' => 'Closed assignment.',
        ])->assertStatus(409);
    }

    public function test_non_enrolled_and_inactive_students_cannot_read_or_submit_assignments(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->fixture();
        $other = User::factory()->create(['role' => 'student']);
        $token = $this->token($other);

        $this->withToken($token)->getJson('/api/v1/my/assignments')->assertOk()->assertJsonPath('meta.total', 0);
        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submission', [
            'answer' => 'Unauthorized.',
        ])->assertForbidden();

        Enrollment::where('user_id', $student->id)->where('batch_id', $assignment->batch_id)->update(['status' => 'cancelled']);
        $this->withToken($this->token($student))->getJson('/api/v1/my/assignments')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->withToken($this->token($student))->postJson('/api/v1/assignments/'.$assignment->id.'/submission', [
            'answer' => 'No longer enrolled.',
        ])->assertForbidden();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_instructor_cannot_use_student_assignment_endpoints(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->withToken($this->token($teacher))->getJson('/api/v1/my/assignments')->assertForbidden();
    }

    private function fixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $branch = Branch::create(['name' => 'Assignment branch '.$student->id, 'slug' => 'assignment-branch-'.$student->id]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Assignment batch',
            'capacity' => 10,
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
        $assignment = $batch->assignments()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Mobile assignment',
            'instructions' => 'Write a short answer.',
            'due_at' => now()->addDay(),
            'allow_late' => false,
            'max_marks' => 10,
            'status' => 'published',
        ]);

        return compact('teacher', 'student', 'course', 'batch', 'assignment');
    }

    private function token(User $user): string
    {
        $plain = 'mobile-assignment-token-'.Str::random(48);
        $device = null;
        if ($user->isStudent()) {
            $device = StudentDevice::create([
                'user_id' => $user->id,
                'device_hash' => hash('sha256', Str::random(64)),
                'device_name' => 'Student app test',
                'status' => 'approved',
                'phone_verified_at' => now(),
                'approved_at' => now(),
            ]);
        }

        ApiToken::create([
            'user_id' => $user->id,
            'student_device_id' => $device?->id,
            'name' => 'Mobile app test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
