<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Course;
use App\Models\User;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_manage_academic_levels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.academic-levels.store'), [
            'name' => 'SSC',
            'slug' => 'ssc',
            'description' => 'Secondary School Certificate',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect();

        $level = AcademicLevel::where('slug', 'ssc')->firstOrFail();
        $this->put(route('admin.academic-levels.update', $level), [
            'name' => 'SSC & Equivalent',
            'slug' => 'ssc-equivalent',
            'description' => 'Secondary level',
            'sort_order' => 3,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('academic_levels', ['id' => $level->id, 'slug' => 'ssc-equivalent']);
        $this->get(route('admin.academic-levels.index'))->assertOk()->assertSee('SSC & Equivalent');
    }

    public function test_courses_and_pages_can_be_assigned_to_a_level_and_level_cannot_be_deleted_while_in_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $level = AcademicLevel::create(['name' => 'HSC', 'slug' => 'hsc', 'is_active' => true]);

        $course = Course::create([
            'teacher_id' => $teacher->id,
            'academic_level_id' => $level->id,
            'title' => 'HSC Physics',
            'slug' => 'hsc-physics',
            'description' => 'Physics for HSC.',
            'price' => 0,
            'status' => 'published',
        ]);
        $page = WebsitePage::create([
            'title' => 'HSC Programs',
            'slug' => 'hsc-programs',
            'academic_level_id' => $level->id,
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->assertSame($level->id, $course->fresh()->academicLevel->id);
        $this->assertSame($level->id, $page->fresh()->academicLevel->id);
        $this->actingAs($admin)->delete(route('admin.academic-levels.destroy', $level))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('academic_levels', ['id' => $level->id]);
    }

    public function test_non_admin_cannot_manage_academic_levels(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.academic-levels.index'))->assertForbidden();
    }
}
