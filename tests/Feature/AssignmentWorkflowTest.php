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
}
