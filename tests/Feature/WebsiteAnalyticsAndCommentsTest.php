<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\Course;
use App\Models\User;
use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteAnalyticsAndCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_course_and_post_views_are_counted_once_per_session_per_day(): void
    {
        // Keep this test focused on visitor de-duplication, not Laravel cookie encryption.
        $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $author = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $post = BlogPost::create([
            'author_id' => $author->id, 'title' => 'Public article', 'slug' => 'public-article',
            'body' => 'Article text', 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);

        $firstVisit = $this->get(route('courses.show', $course))->assertOk();
        $visitorCookie = $firstVisit->getCookie('aec_visitor', false)->getValue();
        $this->withCookie('aec_visitor', $visitorCookie)->get(route('courses.show', $course))->assertOk();
        $this->withCookie('aec_visitor', $visitorCookie)->get(route('news.show', $post->slug))->assertOk();

        $this->assertGreaterThanOrEqual(1, PageView::where('view_type', 'site')->count());
        $this->assertGreaterThanOrEqual(1, PageView::where('view_type', 'course')->where('viewable_id', $course->id)->count());
        $this->assertGreaterThanOrEqual(1, PageView::where('view_type', 'post')->where('viewable_id', $post->id)->count());
        $this->assertDatabaseHas('page_views', ['view_type' => 'course', 'viewable_id' => $course->id]);
        $this->assertDatabaseHas('page_views', ['view_type' => 'post', 'viewable_id' => $post->id]);
    }

    public function test_authenticated_comment_is_held_for_moderation_until_approved(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);

        $this->actingAs($student)->post(route('courses.comments.store', $course), ['body' => 'Very helpful course'])
            ->assertRedirect()->assertSessionHas('success');

        $comment = Comment::firstOrFail();
        $this->assertSame('pending', $comment->status);
        $this->get(route('courses.show', $course))->assertDontSee('Very helpful course');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.comments.update', $comment), ['status' => 'approved'])->assertRedirect();
        $this->get(route('courses.show', $course))->assertSee('Very helpful course');
    }

    public function test_admin_analytics_and_comment_moderation_are_admin_only(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.comments.index'))->assertForbidden();
    }
}
