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
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/my/assignments')->assertUnauthorized();

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

    public function test_student_can_filter_assignments_and_revise_only_graded_submissions(): void
    {
        [$student, $batch, $assignment] = $this->fixture();
        $token = $this->token($student);

        $gradedAssignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $batch->teacher_id,
            'title' => 'Previously graded assignment',
            'instructions' => 'Revise this answer.',
            'due_at' => now()->addDays(2),
            'allow_late' => false,
            'max_marks' => 20,
            'status' => 'published',
        ]);
        $lateAssignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $batch->teacher_id,
            'title' => 'Late-enabled assignment',
            'instructions' => 'Late submissions are allowed.',
            'due_at' => now()->subDay(),
            'allow_late' => true,
            'max_marks' => 10,
            'status' => 'published',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $gradedAssignment->id,
            'student_id' => $student->id,
            'answer' => 'Old graded answer.',
            'submitted_at' => now()->subDay(),
            'status' => 'graded',
            'score' => 15,
            'feedback' => 'Please improve the conclusion.',
        ]);

        $this->withToken($token)->getJson('/api/v1/my/assignments?status=graded')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $gradedAssignment->id);

        $this->withToken($token)->getJson('/api/v1/my/assignments?status=upcoming')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->withToken($token)->getJson('/api/v1/my/assignments?status=overdue')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $lateAssignment->id);

        $this->withToken($token)->getJson('/api/v1/my/assignments?per_page=51')
            ->assertUnprocessable();

        $this->withToken($token)->postJson('/api/v1/assignments/'.$gradedAssignment->id.'/submit', [
            'answer' => 'Revised answer after feedback.',
        ])->assertCreated()
            ->assertJsonPath('submission.status', 'submitted')
            ->assertJsonPath('submission.score', null)
            ->assertJsonPath('submission.feedback', null);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$gradedAssignment->id.'/submit', [
            'answer' => 'A second pending revision.',
        ])->assertStatus(409);
    }

    public function test_unpublished_assignment_cannot_be_viewed_or_submitted(): void
    {
        [$student, , $assignment] = $this->fixture();
        $assignment->update(['status' => 'draft']);
        $token = $this->token($student);

        $this->withToken($token)->getJson('/api/v1/assignments/'.$assignment->id)
            ->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'This must not be accepted.',
        ])->assertNotFound();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }


    public function test_student_can_upload_private_assignment_attachment_and_only_owner_can_download_it(): void
    {
        Storage::fake('local');
        [$student, , $assignment] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->post('/api/v1/assignments/'.$assignment->id.'/submit', [
            'attachment' => UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('submission.has_attachment', true)
            ->assertJsonPath('submission.attachment_name', 'homework.pdf');

        $submission = AssignmentSubmission::query()->firstOrFail();
        Storage::disk('local')->assertExists($submission->attachment_path);

        $this->withToken($token)->get('/api/v1/assignment-submissions/'.$submission->id.'/attachment')
            ->assertOk();

        $otherStudent = User::factory()->create(['role' => 'student']);
        $this->withToken($this->token($otherStudent))
            ->getJson('/api/v1/assignment-submissions/'.$submission->id.'/attachment')
            ->assertForbidden();

        $this->withToken($token)->post('/api/v1/assignments/'.$assignment->id.'/submit', [
            'attachment' => UploadedFile::fake()->create('duplicate.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->assertCount(1, Storage::disk('local')->allFiles('assignment-submissions/'.$assignment->id));
    }

    public function test_mobile_assignment_upload_rejects_unsupported_and_oversized_files_without_orphan_files(): void
    {
        Storage::fake('local');
        [$student, , $assignment] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->post('/api/v1/assignments/'.$assignment->id.'/submit', [
            'attachment' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->withToken($token)->post('/api/v1/assignments/'.$assignment->id.'/submit', [
            'attachment' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('assignment_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('assignment-submissions/'.$assignment->id));
    }

    public function test_mobile_revision_after_grading_preserves_existing_private_attachment(): void
    {
        Storage::fake('local');
        [$student, , $assignment] = $this->fixture();
        $token = $this->token($student);

        $this->withToken($token)->post('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'First version.',
            'attachment' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $submission = AssignmentSubmission::query()->firstOrFail();
        $oldPath = $submission->attachment_path;
        Storage::disk('local')->assertExists($oldPath);
        $submission->update(['status' => 'graded', 'score' => 8, 'feedback' => 'Revise this.']);

        $this->withToken($token)->postJson('/api/v1/assignments/'.$assignment->id.'/submit', [
            'answer' => 'Revised text answer.',
        ])->assertCreated()
            ->assertJsonPath('submission.status', 'submitted')
            ->assertJsonPath('submission.has_attachment', true);

        $submission->refresh();
        $this->assertSame($oldPath, $submission->attachment_path);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertNull($submission->score);
        $this->assertNull($submission->feedback);
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
