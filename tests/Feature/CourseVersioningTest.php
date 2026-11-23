<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_edits_are_versioned_and_admin_can_restore_previous_course_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = CourseCategory::create(['name' => 'Speaking', 'slug' => 'speaking']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'title' => 'Original English Course',
            'slug' => 'original-english-course',
            'description' => 'Original description.',
            'status' => 'draft',
        ]);
        $course->categories()->attach($category->id);

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'title' => 'Updated English Course',
            'description' => 'Updated description.',
            'teacher_id' => $teacher->id,
            'price' => 2500,
            'status' => 'published',
            'category_ids' => [$category->id],
            'tags' => 'speaking, grammar',
        ])->assertRedirect(route('admin.courses.index'));

        $version = CourseVersion::query()->where('course_id', $course->id)->firstOrFail();
        $this->assertSame(1, $version->version_number);
        $this->assertSame('Original English Course', $version->snapshot['title']);
        $this->assertSame('draft', $version->snapshot['status']);

        $this->post(route('admin.courses.versions.restore', [$course->fresh(), $version]))
            ->assertRedirect(route('admin.courses.versions', $course));

        $this->assertSame('Original English Course', $course->fresh()->title);
        $this->assertSame('draft', $course->fresh()->status);
        $this->assertDatabaseCount('course_versions', 2);
        $this->assertDatabaseHas('course_versions', [
            'course_id' => $course->id,
            'version_number' => 2,
            'change_type' => 'restore',
        ]);
    }

    public function test_non_admin_cannot_open_course_revision_history(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher)->get(route('admin.courses.versions', $course))->assertForbidden();
    }
}
