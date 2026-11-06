<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_course_management(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@lms.test',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/courses')
            ->assertOk();
    }

    public function test_admin_can_create_course(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@lms.test',
            'role' => 'admin',
        ]);

        $teacher = User::factory()->create([
            'email' => 'teacher@lms.test',
            'role' => 'teacher',
        ]);

        $this->actingAs($admin)
            ->post('/admin/courses', [
                'title' => 'Advanced Laravel',
                'description' => 'Production-grade backend training',
                'teacher_id' => $teacher->id,
                'price' => 120,
                'status' => 'published',
            ])
            ->assertRedirect('/admin/courses');

        $this->assertDatabaseHas('courses', ['title' => 'Advanced Laravel']);
    }
}
