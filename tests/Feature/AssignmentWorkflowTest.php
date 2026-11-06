<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
