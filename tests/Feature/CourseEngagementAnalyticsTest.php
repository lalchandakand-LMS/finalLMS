<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseContentPost;
use App\Models\CourseContentProgress;
use App\Models\CourseCurriculumItem;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEngagementAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_engagement_completion_and_thirty_day_dropout_risk_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $activeStudent = User::factory()->create(['role' => 'student']);
        $dropoutStudent = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Analytics course']);
        $post = CourseContentPost::create([
            'author_id' => $teacher->id,
            'title' => 'Analytics lesson',
            'slug' => 'analytics-lesson',
            'long_description' => 'Lesson body',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $item = CourseCurriculumItem::create([
            'course_id' => $course->id,
            'course_content_post_id' => $post->id,
            'position' => 1,
        ]);
        Enrollment::create(['user_id' => $activeStudent->id, 'course_id' => $course->id, 'status' => 'active']);
        Enrollment::create([
            'user_id' => $dropoutStudent->id,
            'course_id' => $course->id,
            'status' => 'active',
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ]);
        CourseContentProgress::create([
            'user_id' => $activeStudent->id,
            'course_curriculum_item_id' => $item->id,
            'last_viewed_at' => now()->subDay(),
            'duration_seconds' => 100,
            'last_position_seconds' => 100,
            'progress_percent' => 100,
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->get(route('admin.course-analytics.index'))
            ->assertOk()
            ->assertSee('Analytics course')
            ->assertSee('100.0%')
            ->assertSee('1');
    }

    public function test_non_admin_cannot_access_course_engagement_analytics(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get(route('admin.course-analytics.index'))->assertForbidden();
    }
}
