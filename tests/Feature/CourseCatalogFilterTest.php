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

    public function test_catalog_searches_title_description_and_teacher_but_hides_unpublished_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Ayesha Rahman']);
        $this->course($teacher, 'Spoken English', 2500, 'published', 'Confident conversation practice');
        $this->course($teacher, 'IELTS Writing', 3500, 'published', 'Academic essay skills');
        $this->course($teacher, 'Secret Draft Course', 500, 'draft');

        $this->get(route('courses.index', ['q' => 'conversation']))
            ->assertOk()->assertSee('Spoken English')->assertDontSee('IELTS Writing')->assertDontSee('Secret Draft Course');
        $this->get(route('courses.index', ['q' => 'Ayesha']))
            ->assertOk()->assertSee('Spoken English')->assertSee('IELTS Writing');
    }

    public function test_catalog_filters_by_teacher_and_price_range(): void
    {
        $one = User::factory()->create(['role' => 'teacher']);
        $two = User::factory()->create(['role' => 'teacher']);
        $this->course($one, 'Basic English', 1500);
        $this->course($one, 'Advanced English', 4500);
        $this->course($two, 'Business English', 2500);

        $this->get(route('courses.index', ['teacher' => $one->id, 'min_price' => 1000, 'max_price' => 3000]))
            ->assertOk()->assertSee('Basic English')->assertDontSee('Advanced English')->assertDontSee('Business English');
    }

    public function test_catalog_sorts_and_preserves_filters_in_pagination_links(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->course($teacher, 'Mid Course', 2500);
        $this->course($teacher, 'Low Course', 1000);
        $this->course($teacher, 'High Course', 5000);
        for ($number = 1; $number <= 10; $number++) {
            $this->course($teacher, 'Extra Course '.$number, 3000 + $number);
        }

        $response = $this->get(route('courses.index', ['sort' => 'price_low', 'q' => 'Course']));
        $response->assertOk()->assertSee('sort=price_low', false)->assertSee('q=Course', false);
        $response->assertSeeInOrder(['Low Course', 'Mid Course', 'High Course']);
    }

    public function test_catalog_rejects_invalid_sort_and_inverted_price_range(): void
    {
        $this->get(route('courses.index', ['sort' => 'random']))->assertSessionHasErrors('sort');
        $this->get(route('courses.index', ['min_price' => 5000, 'max_price' => 1000]))->assertSessionHasErrors('max_price');
    }

    public function test_catalog_shows_empty_state_for_no_matches(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->course($teacher, 'Existing Published Course', 1000);
        $this->get(route('courses.index', ['q' => 'no-such-course']))->assertOk()->assertSee('No courses found');
    }
}
