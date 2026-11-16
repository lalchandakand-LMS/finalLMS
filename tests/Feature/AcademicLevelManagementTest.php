<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_level_and_assign_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $level = AcademicLevel::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);

        $course = Course::create([
            'teacher_id' => $teacher->id,
            'academic_level_id' => $level->id,
            'title' => 'Primary English',
            'slug' => 'primary-english',
            'description' => 'Primary course',
            'price' => 0,
            'status' => 'draft',
        ]);

        $this->assertSame($level->id, $course->academicLevel->id);
        $this->actingAs($admin)->get(route('admin.academic-levels.index'))->assertOk()->assertSee('Primary');
    }

    public function test_level_detail_links_to_prefilled_course_and_page_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $level = AcademicLevel::create(['name' => 'Early Years', 'slug' => 'early-years', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.academic-levels.show', $level))
            ->assertOk()
            ->assertSee('Add course')
            ->assertSee('Add page');

        $this->get(route('admin.courses.create', ['academic_level_id' => $level->id]))
            ->assertOk()
            ->assertSee('value="'.$level->id.'" selected', false);

        $this->get(route('admin.website-pages.create', ['academic_level_id' => $level->id]))
            ->assertOk()
            ->assertSee('value="'.$level->id.'" selected', false);
    }

    public function test_level_cannot_be_deleted_while_courses_or_pages_are_assigned(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $level = AcademicLevel::create(['name' => 'Secondary', 'slug' => 'secondary', 'is_active' => true]);
        Course::create([
            'teacher_id' => $teacher->id,
            'academic_level_id' => $level->id,
            'title' => 'Secondary English',
            'slug' => 'secondary-english',
            'description' => 'Secondary course',
            'price' => 0,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete(route('admin.academic-levels.destroy', $level))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('academic_levels', ['id' => $level->id]);
    }
}
