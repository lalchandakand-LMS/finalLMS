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

    public function test_enrolled_student_can_mark_a_lesson_complete_once(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $course->lessons()->create([
            'title' => 'First lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 15,
            'order' => 1,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $url = route('courses.lessons.complete', [$course, $lesson]);

        $this->actingAs($student)->post($url)->assertRedirect();
        $this->actingAs($student)->post($url)->assertRedirect();

        $this->assertDatabaseCount('lesson_progress', 1);
        $this->assertDatabaseHas('lesson_progress', [
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_student_without_active_enrollment_cannot_complete_a_lesson(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $course->lessons()->create([
            'title' => 'Locked lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 15,
            'order' => 1,
        ]);

        $this->actingAs($student)
            ->post(route('courses.lessons.complete', [$course, $lesson]))
            ->assertNotFound();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_student_cannot_complete_a_lesson_from_another_course(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = $otherCourse->lessons()->create([
            'title' => 'Different course lesson',
            'content' => 'Lesson content',
            'duration_minutes' => 15,
            'order' => 1,
        ]);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->actingAs($student)
            ->post(route('courses.lessons.complete', [$course, $lesson]))
            ->assertForbidden();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

}
