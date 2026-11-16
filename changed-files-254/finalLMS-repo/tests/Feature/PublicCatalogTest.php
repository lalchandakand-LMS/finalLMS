<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_published_courses_but_not_drafts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Public English Course',
            'slug' => 'public-english-course',
            'description' => 'Published description.',
            'price' => 100,
            'status' => 'published',
        ]);
        Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Secret Draft Course',
            'slug' => 'secret-draft-course',
            'description' => 'Draft description.',
            'price' => 100,
            'status' => 'draft',
        ]);

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Public English Course')
            ->assertDontSee('Secret Draft Course');

        $this->get(route('courses.show', 'public-english-course'))->assertOk();
        $this->get(route('courses.show', 'secret-draft-course'))->assertNotFound();
    }
}
