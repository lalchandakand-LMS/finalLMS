<?php

namespace Tests\Feature;

use Aloms\CarouselBuilder\Carousel;
use Aloms\CarouselBuilder\CarouselBuilderController;
use App\Models\Comment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CarouselBuilderPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('plugins/aec/carousel-builder/1.0.0/src/Carousel.php');
        require_once base_path('plugins/aec/carousel-builder/1.0.0/src/CarouselItem.php');
        require_once base_path('plugins/aec/carousel-builder/1.0.0/src/CarouselBuilderController.php');

        if (! Schema::hasTable('website_carousels')) {
            $migration = require base_path('plugins/aec/carousel-builder/1.0.0/migrations/2026_11_23_010000_create_website_carousel_tables.php');
            $migration->up();
        }

        Route::get('/__test-carousels', [CarouselBuilderController::class, 'index'])->name('admin.carousels.index');
        Route::post('/__test-carousels', [CarouselBuilderController::class, 'store'])->name('admin.carousels.store');
        Route::put('/__test-carousels/{carousel}', [CarouselBuilderController::class, 'update'])->name('admin.carousels.update');
        Route::delete('/__test-carousels/{carousel}', [CarouselBuilderController::class, 'destroy'])->name('admin.carousels.destroy');
    }

    public function test_admin_can_create_and_update_a_reusable_manual_carousel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'title' => 'Student success stories',
            'slug' => 'student-success',
            'source_type' => 'manual',
            'item_limit' => 8,
            'desktop_cards' => 3,
            'mobile_cards' => 1,
            'interval_ms' => 5000,
            'status' => 'published',
            'sort_order' => 2,
            'autoplay' => '1',
            'show_arrows' => '1',
            'show_dots' => '1',
            'items' => [
                ['title' => 'Speaking confidence', 'description' => 'A learner story', 'image_url' => '/storage/story.jpg', 'link_url' => '/courses/spoken-english', 'sort_order' => 0],
                ['title' => 'Exam success', 'description' => 'Another learner story', 'image_url' => '', 'link_url' => '', 'sort_order' => 1],
            ],
        ];

        $this->actingAs($admin)->post('/__test-carousels', $payload)->assertRedirect(route('admin.carousels.index'));

        $carousel = Carousel::with('items')->where('slug', 'student-success')->firstOrFail();
        $this->assertSame(2, $carousel->items->count());
        $this->assertTrue($carousel->autoplay);
        $this->assertSame('/storage/story.jpg', $carousel->items->first()->image_url);

        $payload['title'] = 'Our learner stories';
        $payload['items'] = [['title' => 'New story', 'description' => 'Updated', 'image_url' => null, 'link_url' => null, 'sort_order' => 0]];
        $this->actingAs($admin)->put('/__test-carousels/'.$carousel->id, $payload)->assertRedirect(route('admin.carousels.index'));

        $this->assertDatabaseHas('website_carousels', ['id' => $carousel->id, 'title' => 'Our learner stories']);
        $this->assertDatabaseHas('website_carousel_items', ['carousel_id' => $carousel->id, 'title' => 'New story']);
        $this->assertDatabaseMissing('website_carousel_items', ['carousel_id' => $carousel->id, 'title' => 'Speaking confidence']);
    }

    public function test_dynamic_sources_use_only_published_courses_and_approved_course_testimonials(): void
    {
        $published = Course::factory()->create(['status' => 'published', 'title' => 'Published English']);
        $draft = Course::factory()->create(['status' => 'draft', 'title' => 'Private draft']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Verified learner']);

        Comment::create(['author_id' => $student->id, 'commentable_type' => Course::class, 'commentable_id' => $published->id, 'body' => 'Excellent class', 'status' => 'approved']);
        Comment::create(['author_id' => $student->id, 'commentable_type' => Course::class, 'commentable_id' => $draft->id, 'body' => 'Private course review', 'status' => 'approved']);
        Comment::create(['author_id' => $student->id, 'commentable_type' => Course::class, 'commentable_id' => $published->id, 'body' => 'Unmoderated review', 'status' => 'pending']);

        $courseCarousel = Carousel::create(['title' => 'Courses', 'slug' => 'courses', 'source_type' => 'courses', 'item_limit' => 10, 'desktop_cards' => 3, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'published']);
        $testimonialCarousel = Carousel::create(['title' => 'Testimonials', 'slug' => 'testimonials', 'source_type' => 'testimonials', 'item_limit' => 10, 'desktop_cards' => 3, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'published']);

        $this->assertSame(['Published English'], $courseCarousel->displayItems()->pluck('title')->all());
        $this->assertSame(['Excellent class'], $testimonialCarousel->displayItems()->pluck('description')->all());
    }

    public function test_visibility_scope_respects_publication_status_and_schedule(): void
    {
        $visible = Carousel::create(['title' => 'Visible', 'slug' => 'visible', 'source_type' => 'manual', 'item_limit' => 4, 'desktop_cards' => 2, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'published']);
        Carousel::create(['title' => 'Draft', 'slug' => 'draft', 'source_type' => 'manual', 'item_limit' => 4, 'desktop_cards' => 2, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'draft']);
        Carousel::create(['title' => 'Expired', 'slug' => 'expired', 'source_type' => 'manual', 'item_limit' => 4, 'desktop_cards' => 2, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'published', 'ends_at' => now()->subMinute()]);
        Carousel::create(['title' => 'Future', 'slug' => 'future', 'source_type' => 'manual', 'item_limit' => 4, 'desktop_cards' => 2, 'mobile_cards' => 1, 'interval_ms' => 5000, 'status' => 'published', 'starts_at' => now()->addDay()]);

        $this->assertSame([$visible->id], Carousel::visible()->pluck('id')->all());
    }

    public function test_carousel_admin_routes_reject_non_admins_and_unsafe_external_urls(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get('/__test-carousels')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/__test-carousels', [
            'title' => 'Unsafe', 'slug' => 'unsafe', 'source_type' => 'manual',
            'item_limit' => 5, 'desktop_cards' => 2, 'mobile_cards' => 1,
            'interval_ms' => 5000, 'status' => 'published', 'sort_order' => 0,
            'items' => [['title' => 'Unsafe link', 'image_url' => 'javascript:alert(1)', 'link_url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors(['items.0.image_url', 'items.0.link_url']);

        $this->assertDatabaseMissing('website_carousels', ['slug' => 'unsafe']);
    }

    public function test_plugin_manifest_uses_the_verified_executable_package_contract(): void
    {
        $manifest = json_decode(file_get_contents(base_path('plugins/aec/carousel-builder/1.0.0/plugin.json')), true, 64, JSON_THROW_ON_ERROR);

        $this->assertSame('aec.carousel-builder', $manifest['key']);
        $this->assertSame([], app(\App\Services\Plugins\PluginManifestValidator::class)->errors($manifest));
    }
}
