<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherLessonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_teacher_can_create_update_and_delete_lessons(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);

        $this->actingAs($teacher)->post(route('teacher.courses.lessons.store', $course), [
            'title' => 'Pronunciation',
            'content' => 'Practice vowel sounds.',
            'duration_minutes' => 25,
            'order' => 1,
            'video_url' => 'https://youtu.be/abcdefghijk',
            'is_preview' => '1',
        ])->assertRedirect(route('teacher.courses.lessons.index', $course));

        $lesson = $course->lessons()->firstOrFail();
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'is_preview' => true]);

        $this->put(route('teacher.courses.lessons.update', [$course, $lesson]), [
            'title' => 'Updated pronunciation',
            'content' => 'Updated notes.',
            'duration_minutes' => 30,
            'order' => 2,
            'video_url' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Updated pronunciation']);

        $this->delete(route('teacher.courses.lessons.destroy', [$course, $lesson]))->assertRedirect();
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_teacher_cannot_manage_another_teachers_course_lessons(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $other = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('teacher.courses.lessons.index', $course))
            ->assertForbidden();
    }
}
