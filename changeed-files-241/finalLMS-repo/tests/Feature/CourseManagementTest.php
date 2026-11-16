<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_course_creation_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($admin)
            ->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('Create course')
            ->assertSee('name="title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="teacher_id"', false)
            ->assertSee($teacher->name);
    }

    public function test_admin_can_open_the_course_edit_form_with_existing_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English',
            'slug' => 'spoken-english',
            'description' => 'Build confidence in everyday English.',
            'price' => 2500,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee('Edit course')
            ->assertSee('Spoken English')
            ->assertSee('Build confidence in everyday English.')
            ->assertSee('value="2500.00"', false)
            ->assertSee($teacher->name);
    }
}
