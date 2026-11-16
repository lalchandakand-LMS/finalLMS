<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\Course;
use App\Models\User;
use App\Models\WebsitePage;
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

    public function test_existing_courses_and_pages_keep_inactive_level_assignments_editable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $level = AcademicLevel::create(['name' => 'Archived Level', 'slug' => 'archived-level', 'is_active' => false]);

        $course = Course::create([
            'teacher_id' => $teacher->id,
            'academic_level_id' => $level->id,
            'title' => 'Archived Level Course',
            'slug' => 'archived-level-course',
            'description' => 'Existing course',
            'price' => 0,
            'status' => 'draft',
        ]);
        $page = WebsitePage::create([
            'title' => 'Archived Level Page',
            'slug' => 'archived-level-page',
            'template' => 'standard',
            'status' => 'draft',
            'created_by' => $admin->id,
            'academic_level_id' => $level->id,
            'sections' => [],
        ]);

        $this->actingAs($admin)->get(route('admin.courses.edit', $course))
            ->assertOk()
            ->assertSee('value="'.$level->id.'" selected', false);
        $this->get(route('admin.website-pages.edit', $page))
            ->assertOk()
            ->assertSee('value="'.$level->id.'" selected', false);
    }

    public function test_migration_refuses_to_drop_non_empty_academic_levels(): void
    {
        AcademicLevel::create(['name' => 'Keep this level', 'slug' => 'keep-this-level', 'is_active' => true]);
        $migration = require database_path('migrations/2026_11_16_160000_create_academic_levels_and_assignments.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
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
    public function test_academic_level_exam_list_is_scoped_and_searchable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $primary = AcademicLevel::create(['name' => 'Primary', 'slug' => 'primary', 'is_active' => true]);
        $secondary = AcademicLevel::create(['name' => 'Secondary', 'slug' => 'secondary', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Main Branch',
            'slug' => 'main-branch',
            'address' => 'Dhaka',
            'phone' => '000',
            'is_active' => true,
        ]);

        $createExam = function (AcademicLevel $level, string $courseTitle, string $examTitle) use ($teacher, $branch): Exam {
            $course = Course::create([
                'teacher_id' => $teacher->id,
                'academic_level_id' => $level->id,
                'title' => $courseTitle,
                'slug' => strtolower(str_replace(' ', '-', $courseTitle)),
                'description' => $courseTitle,
                'price' => 0,
                'status' => 'published',
            ]);
            $batch = Batch::create([
                'branch_id' => $branch->id,
                'course_id' => $course->id,
                'teacher_id' => $teacher->id,
                'title' => $courseTitle.' Batch',
                'capacity' => 30,
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(30),
                'fee' => 0,
                'status' => 'open',
            ]);

            return Exam::create([
                'batch_id' => $batch->id,
                'teacher_id' => $teacher->id,
                'title' => $examTitle,
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(),
                'duration_minutes' => 60,
                'pass_marks' => 5,
                'status' => 'published',
            ]);
        };

        $createExam($primary, 'Primary Mathematics', 'Primary Mathematics Final');
        $createExam($secondary, 'Secondary Physics', 'Secondary Physics Final');

        $this->actingAs($admin)->get(route('admin.academic-levels.show', $primary))
            ->assertOk()
            ->assertSee('Primary Mathematics Final')
            ->assertDontSee('Secondary Physics Final');

        $this->get(route('admin.academic-levels.show', ['academicLevel' => $primary->id, 'exam_search' => 'Mathematics']))
            ->assertOk()
            ->assertSee('Primary Mathematics Final')
            ->assertDontSee('Secondary Physics Final');

        $this->get(route('admin.academic-levels.show', ['academicLevel' => $primary->id, 'exam_search' => 'No matching exam']))
            ->assertOk()
            ->assertSee('No exams match this academic level and search.');
    }

    public function test_academic_level_exam_search_rejects_oversized_terms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $level = AcademicLevel::create(['name' => 'Primary', 'slug' => 'primary-search', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.academic-levels.show', ['academicLevel' => $level->id, 'exam_search' => str_repeat('x', 121)]))
            ->assertSessionHasErrors('exam_search');
    }

}
