<?php

namespace Tests\Feature;

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
            ->assertSee('Attendance records');
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

        Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        Course::factory()->create(['teacher_id' => $otherTeacher->id, 'status' => 'published']);

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Your teaching overview')
            ->assertSee('Assigned courses')
            ->assertSee('1')
            ->assertSee('Pending reviews')
            ->assertSee('Graded submissions');
    }

}
