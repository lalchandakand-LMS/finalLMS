<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCoursePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_enrolled_courses_and_lesson_progress(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English',
            'status' => 'published',
        ]);

        $firstLesson = $course->lessons()->create([
            'title' => 'Speaking basics',
            'content' => 'Practice introductions.',
            'duration_minutes' => 20,
            'order' => 1,
        ]);
        $course->lessons()->create([
            'title' => 'Daily conversation',
            'content' => 'Practice everyday dialogue.',
            'duration_minutes' => 25,
            'order' => 2,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        LessonProgress::create([
            'lesson_id' => $firstLesson->id,
            'user_id' => $student->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Spoken English')
            ->assertSee('1 / 2')
            ->assertSee('50%');
    }

    public function test_non_student_cannot_open_student_course_portal(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('student.courses.index'))
            ->assertForbidden();
    }
}
