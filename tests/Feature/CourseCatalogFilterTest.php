<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private function course(User $teacher, string $title, float $price, string $status = 'published', ?string $description = null): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'slug' => str()->slug($title),
            'description' => $description ?? 'Learn practical communication skills.',
            'price' => $price,
            'status' => $status,
        ]);
    }

    public function test_catalog_searches_course_title_and_description_and_hides_unpublished_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Ayesha Rahman']);
        $this->course($teacher, 'Spoken English', 2500, 'published', 'Confident conversation practice');
        $this->course($teacher, 'IELTS Writing', 3500, 'published', 'Academic essay skills');
        $this->course($teacher, 'Secret Draft Course', 500, 'draft');

        $this->get(route('courses.index', ['q' => 'conversation']))
            ->assertOk()
            ->assertSee('Spoken English')
            ->assertDontSee('IELTS Writing')
            ->assertDontSee('Secret Draft Course');
    }

    public function test_catalog_filters_by_teacher_and_price_range(): void
    {
        $teacherOne = User::factory()->create(['role' => 'teacher', 'name' => 'Teacher One']);
        $teacherTwo = User::factory()->create(['role' => 'teacher', 'name' => 'Teacher Two']);
        $this->course($teacherOne, 'Basic English', 1500);
        $this->course($teacherOne, 'Advanced English', 4500);
        $this->course($teacherTwo, 'Business English', 2500);

        $this->get(route('courses.index', [
            'teacher' => $teacherOne->id,
            'min_price' => 1000,
            'max_price' => 3000,
        ]))->assertOk()
            ->assertSee('Basic English')
            ->assertDontSee('Advanced English')
            ->assertDontSee('Business English');
    }

    public function test_catalog_sorts_by_price_and_paginates_with_query_parameters(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->course($teacher, 'Mid Course', 2500);
        $this->course($teacher, 'Low Course', 1000);
        $this->course($teacher, 'High Course', 5000);

        $response = $this->get(route('courses.index', ['sort' => 'price_low']));
        $response->assertOk()->assertSee('Low Course')->assertSee('High Course');
        $response->assertSee('sort=price_low', false);
    }

    public function test_catalog_rejects_invalid_sort_and_inverted_price_range(): void
    {
        $this->get(route('courses.index', ['sort' => 'random']))->assertSessionHasErrors('sort');
        $this->get(route('courses.index', ['min_price' => 5000, 'max_price' => 1000]))
            ->assertSessionHasErrors('max_price');
    }

    public function test_catalog_is_paginated_and_returns_empty_state_for_no_matches(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->course($teacher, 'Existing Published Course', 1000);

        $this->get(route('courses.index', ['q' => 'no-such-course']))
            ->assertOk()
            ->assertSee('No courses found');
    }
}
