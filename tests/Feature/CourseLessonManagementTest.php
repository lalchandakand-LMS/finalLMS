<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLessonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_course_lesson(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Laravel Foundations',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.courses.lessons.store', $course), [
                'title' => 'Module 1: Setup',
                'content' => 'Install PHP and Laravel with the proper tooling.',
                'duration_minutes' => 30,
                'order' => 1,
            ])
            ->assertRedirect(route('admin.courses.index'));

        $this->assertDatabaseHas('lessons', [
            'course_id' => $course->id,
            'title' => 'Module 1: Setup',
        ]);
    }

    public function test_course_detail_page_lists_lessons(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Laravel Foundations',
        ]);

        $course->lessons()->create([
            'title' => 'Module 1: Setup',
            'content' => 'Install PHP and Laravel.',
            'duration_minutes' => 25,
            'order' => 1,
        ]);

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Module 1: Setup');
    }
}
