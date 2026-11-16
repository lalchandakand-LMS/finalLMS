<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_shows_learning_progress_from_their_own_enrollments(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'published',
        ]);

        $completedLesson = $course->lessons()->create([
            'title' => 'Completed lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 15,
            'order' => 1,
        ]);
        $course->lessons()->create([
            'title' => 'Upcoming lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 15,
            'order' => 2,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        LessonProgress::create([
            'lesson_id' => $completedLesson->id,
            'user_id' => $student->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Your learning progress')
            ->assertSee('50%')
            ->assertSee('1 / 2')
            ->assertSee('Assignments submitted')
            ->assertSee('Attendance records')
            ->assertViewHas('studentInsights', fn (array $insights) => $insights['enrolled_courses'] === 1
                && $insights['completed_lessons'] === 1
                && $insights['total_lessons'] === 2);
    }

    public function test_student_dashboard_does_not_count_lessons_from_unenrolled_courses(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $enrolledCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id]);

        $enrolledCourse->lessons()->create([
            'title' => 'My lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 10,
            'order' => 1,
        ]);
        $otherCourse->lessons()->create([
            'title' => 'Other student lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 10,
            'order' => 1,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $enrolledCourse->id,
            'status' => 'active',
        ]);

        $this->actingAs($student)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('0%')
            ->assertSee('0 / 1');
    }

    public function test_teacher_dashboard_metrics_are_scoped_to_the_signed_in_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $otherCourse = Course::factory()->create(['teacher_id' => $otherTeacher->id, 'status' => 'published']);

        $branch = Branch::create(['name' => 'Main Branch', 'slug' => 'main-branch']);
        $otherBranch = Branch::create(['name' => 'Other Branch', 'slug' => 'other-branch']);

        $batch = Batch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Teacher batch',
            'capacity' => 25,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 1000,
            'status' => 'open',
        ]);
        $otherBatch = Batch::create([
            'branch_id' => $otherBranch->id,
            'course_id' => $otherCourse->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other teacher batch',
            'capacity' => 25,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'fee' => 1000,
            'status' => 'open',
        ]);

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'status' => 'active']);
        Enrollment::create(['user_id' => $otherStudent->id, 'course_id' => $otherCourse->id, 'batch_id' => $otherBatch->id, 'status' => 'active']);

        $assignment = Assignment::create([
            'batch_id' => $batch->id,
            'teacher_id' => $teacher->id,
            'title' => 'Teacher assignment',
            'instructions' => 'Complete this task.',
            'due_at' => now()->addDays(5),
            'max_marks' => 100,
            'status' => 'published',
        ]);
        $otherAssignment = Assignment::create([
            'batch_id' => $otherBatch->id,
            'teacher_id' => $otherTeacher->id,
            'title' => 'Other teacher assignment',
            'instructions' => 'Complete this task.',
            'due_at' => now()->addDays(5),
            'max_marks' => 100,
            'status' => 'published',
        ]);

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'answer' => 'Pending work',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $otherStudent->id,
            'answer' => 'Graded work',
            'submitted_at' => now(),
            'status' => 'graded',
        ]);
        AssignmentSubmission::create([
            'assignment_id' => $otherAssignment->id,
            'student_id' => $otherStudent->id,
            'answer' => 'Other teacher work',
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Your teaching overview')
            ->assertViewHas('teacherInsights', fn (array $insights) => $insights['assigned_courses'] === 1
                && $insights['total_batches'] === 1
                && $insights['active_batches'] === 1
                && $insights['active_students'] === 1
                && $insights['assignments_created'] === 1
                && $insights['pending_reviews'] === 1
                && $insights['graded_submissions'] === 1);
    }
}
