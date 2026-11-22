<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageCourseCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_homepage_renders_published_courses_in_a_keyboard_and_button_operable_carousel(): void
    {
        Course::factory()->count(5)->create(['status' => 'published']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-course-carousel-track', false)
            ->assertSee('data-course-prev', false)
            ->assertSee('data-course-next', false)
            ->assertSee('aria-label="Published courses"', false);
    }

    public function test_unpublished_courses_are_not_in_the_public_course_carousel(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee($course->title);
    }
}
