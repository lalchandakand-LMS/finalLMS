<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_private_attachment_and_only_authorized_users_can_download_it(): void
    {
        Storage::fake('local');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id, 'title' => 'File Course', 'slug' => 'file-course',
            'description' => 'Files.', 'price' => 0, 'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'File Branch', 'slug' => 'file-branch']);
        $batch = Batch::create([
            'branch_id' => $branch->id, 'course_id' => $course->id, 'teacher_id' => $teacher->id,
            'title' => 'File Batch', 'capacity' => 10, 'starts_at' => now(),
            'ends_at' => now()->addMonth(), 'fee' => 0, 'status' => 'open',
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $assignment = $batch->assignments()->create([
            'teacher_id' => $teacher->id, 'title' => 'Upload work', 'instructions' => 'Attach a PDF.',
            'due_at' => now()->addDay(), 'max_marks' => 10, 'status' => 'published',
        ]);

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $submissionId = DB::table('assignment_submissions')->value('id');
        $submission = \App\Models\AssignmentSubmission::findOrFail($submissionId);
        Storage::disk('local')->assertExists($submission->attachment_path);

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('duplicate.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('info');
        $this->assertCount(1, Storage::disk('local')->allFiles('assignment-submissions/'.$assignment->id));

        $this->actingAs($student)->get(route('assignment-submissions.attachment', $submission))->assertOk();
        $this->actingAs($teacher)->get(route('assignment-submissions.attachment', $submission))->assertOk();
        $this->actingAs($otherStudent)->get(route('assignment-submissions.attachment', $submission))->assertForbidden();
    }

    public function test_teacher_can_publish_assignment_student_submits_once_and_teacher_grades(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'English Writing',
            'slug' => 'english-writing',
            'description' => 'Writing practice.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Dhaka Center', 'slug' => 'dhaka-center']);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Writing Batch',
            'capacity' => 20,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'fee' => 0,
            'status' => 'open',
        ]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);

        $this->actingAs($teacher)
            ->from(route('teacher.batches.assignments.index', $batch))
            ->post(route('teacher.batches.assignments.store', $batch), [
                'title' => 'My introduction',
                'instructions' => 'Write one paragraph about yourself.',
                'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'max_marks' => 10,
            ])
            ->assertRedirect(route('teacher.batches.assignments.index', $batch));

        $assignmentId = DB::table('assignments')->value('id');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignmentId), ['answer' => 'My name is ...'])
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignmentId,
            'student_id' => $student->id,
            'answer' => 'My name is ...',
            'status' => 'submitted',
        ]);

        $this->post(route('student.assignments.submit', $assignmentId), ['answer' => 'Duplicate'])
            ->assertSessionHas('info');
        $this->assertDatabaseCount('assignment_submissions', 1);

        $submissionId = DB::table('assignment_submissions')->value('id');
        $this->actingAs($teacher)
            ->post(route('teacher.assignments.grade', [$assignmentId, $submissionId]), [
                'score' => 8,
                'feedback' => 'Good work.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submissionId,
            'score' => '8.00',
            'status' => 'graded',
        ]);
    }

    public function test_non_enrolled_student_cannot_view_or_submit_assignment(): void
    {
        ['teacher' => $teacher, 'batch' => $batch, 'assignment' => $assignment] = $this->assignmentFixture();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('student.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answer' => 'Unauthorized'])
            ->assertForbidden();

        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_assignment_creation_rejects_non_owner_and_invalid_values(): void
    {
        ['teacher' => $teacher, 'batch' => $batch] = $this->assignmentFixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)->post(route('teacher.batches.assignments.store', $batch), [
            'title' => 'Unauthorized assignment',
            'instructions' => 'Should not be created.',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'max_marks' => 10,
        ])->assertForbidden();

        $this->actingAs($teacher)->from(route('teacher.batches.assignments.index', $batch))
            ->post(route('teacher.batches.assignments.store', $batch), [
                'title' => '',
                'instructions' => 'Invalid marks.',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'max_marks' => 0,
            ])->assertSessionHasErrors(['title', 'max_marks']);

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_assignment_upload_rejects_unsupported_and_oversized_files(): void
    {
        Storage::fake('local');
        ['student' => $student, 'assignment' => $assignment] = $this->assignmentFixture();

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload'),
        ])->assertSessionHasErrors('attachment');

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'attachment' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('assignment_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('assignment-submissions/'.$assignment->id));
    }

    public function test_late_submission_policy_is_enforced_and_can_be_enabled_by_assignment(): void
    {
        ['student' => $student, 'assignment' => $assignment] = $this->assignmentFixture();
        $assignment->update(['due_at' => now()->subMinute(), 'allow_late' => false]);

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'answer' => 'Too late.',
        ])->assertSessionHas('error');
        $this->assertDatabaseCount('assignment_submissions', 0);

        $assignment->update(['allow_late' => true]);
        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'answer' => 'Late, but accepted.',
        ])->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Late, but accepted.',
            'status' => 'submitted',
        ]);
    }

    public function test_graded_submission_can_be_resubmitted_without_losing_existing_attachment(): void
    {
        Storage::fake('local');
        ['teacher' => $teacher, 'student' => $student, 'assignment' => $assignment] = $this->assignmentFixture();
        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'answer' => 'First version.',
            'attachment' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $submission = \App\Models\AssignmentSubmission::firstOrFail();
        $oldPath = $submission->attachment_path;
        Storage::disk('local')->assertExists($oldPath);

        $this->actingAs($teacher)->post(route('teacher.assignments.grade', [$assignment, $submission]), [
            'score' => 8,
            'feedback' => 'Please improve the explanation.',
        ])->assertRedirect();

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), [
            'answer' => 'Revised answer only.',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('Revised answer only.', $submission->answer);
        $this->assertSame($oldPath, $submission->attachment_path);
        Storage::disk('local')->assertExists($oldPath);
        $this->assertSame('submitted', $submission->status);
        $this->assertNull($submission->score);
        $this->assertNull($submission->feedback);
    }

    public function test_teacher_cannot_grade_another_teachers_assignment_and_score_is_bounded(): void
    {
        ['teacher' => $teacher, 'student' => $student, 'assignment' => $assignment] = $this->assignmentFixture();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $submission = \App\Models\AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'My answer.',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($otherTeacher)->post(route('teacher.assignments.grade', [$assignment, $submission]), [
            'score' => 5,
        ])->assertForbidden();

        $this->actingAs($teacher)->post(route('teacher.assignments.grade', [$assignment, $submission]), [
            'score' => 11,
        ])->assertSessionHasErrors('score');

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'status' => 'submitted',
            'score' => null,
        ]);
    }

    /**
     * Build an isolated teacher, student, batch and published assignment.
     *
     * @return array{teacher: User, student: User, batch: Batch, assignment: \\App\\Models\\Assignment}
     */
    private function assignmentFixture(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Regression Course',
            'slug' => 'regression-course-'.$teacher->id,
            'description' => 'Assignment regression fixture.',
            'price' => 0,
            'status' => 'published',
        ]);
        $branch = Branch::create(['name' => 'Regression Branch '.$teacher->id, 'slug' => 'regression-branch-'.$teacher->id]);
        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Regression Batch',
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
            'title' => 'Regression Assignment',
            'instructions' => 'Complete the regression task.',
            'due_at' => now()->addDay(),
            'max_marks' => 10,
            'allow_late' => false,
            'status' => 'published',
        ]);

        return compact('teacher', 'student', 'batch', 'assignment');
    }

}
