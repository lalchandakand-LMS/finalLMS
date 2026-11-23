<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Setting;
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

    public function test_admin_carousel_settings_control_visibility_card_layout_and_item_limit(): void
    {
        Setting::setValue('home_course_carousel_enabled', '1');
        Setting::setValue('home_course_carousel_items', '3');
        Setting::setValue('home_course_carousel_cards', '2');
        Course::factory()->count(4)->create(['status' => 'published']);

        $response = $this->get('/')->assertOk()
            ->assertSee('style="--course-cards:2;--course-gaps:16px;"', false)
            ->assertSee('data-course-carousel-track', false);

        $this->assertSame(3, substr_count($response->getContent(), 'class="home-course-tile"'));

        Setting::setValue('home_course_carousel_enabled', '0');
        $this->get('/')->assertOk()->assertDontSee('class="home-course-grid"', false);
    }

    public function test_unpublished_courses_are_not_in_the_public_course_carousel(): void
    {
        $course = Course::factory()->create(['status' => 'draft']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee($course->title);
    }
}
