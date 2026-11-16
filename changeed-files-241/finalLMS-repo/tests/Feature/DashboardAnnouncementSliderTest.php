<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAnnouncementSliderTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_teacher_and_guardian_dashboards_receive_only_their_targeted_slides(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $guardian = User::factory()->create(['role' => 'guardian']);

        DashboardAnnouncement::create([
            'title' => 'Student course alert', 'content_type' => 'custom',
            'audience' => 'student', 'is_active' => true,
        ]);
        DashboardAnnouncement::create([
            'title' => 'Guardian update', 'content_type' => 'custom',
            'audience' => 'guardian', 'is_active' => true,
        ]);

        $this->actingAs($student)->get(route('dashboard'))->assertOk()
            ->assertSee('Student course alert')->assertDontSee('Guardian update');
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Student course alert')->assertDontSee('Guardian update');
        $this->actingAs($guardian)->get(route('dashboard'))->assertOk()
            ->assertSee('Guardian update')->assertDontSee('Student course alert');
    }

    public function test_active_all_audience_announcement_is_visible_on_profile(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        DashboardAnnouncement::create([
            'title' => 'Academy-wide notice', 'content_type' => 'custom',
            'audience' => 'all', 'is_active' => true,
        ]);

        $this->actingAs($student)->get(route('profile.edit'))
            ->assertOk()->assertSee('Academy-wide notice');
    }

    public function test_scheduled_or_inactive_announcements_are_not_shown(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        DashboardAnnouncement::create([
            'title' => 'Future notice', 'content_type' => 'custom', 'audience' => 'student',
            'is_active' => true, 'starts_at' => now()->addDay(),
        ]);
        DashboardAnnouncement::create([
            'title' => 'Disabled notice', 'content_type' => 'custom', 'audience' => 'student',
            'is_active' => false,
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()->assertDontSee('Future notice')->assertDontSee('Disabled notice');
    }

    public function test_admin_can_create_and_activate_a_new_course_slide(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id, 'status' => 'published',
        ]);

        $this->actingAs($admin)->post(route('admin.dashboard-announcements.store'), [
            'title' => 'New course is available',
            'body' => 'Enroll now to start learning.',
            'content_type' => 'course',
            'course_id' => $course->id,
            'audience' => 'student',
            'priority' => 50,
            'is_active' => '1',
        ])->assertRedirect(route('admin.dashboard-announcements.index'));

        $this->assertDatabaseHas('dashboard_announcements', [
            'title' => 'New course is available',
            'course_id' => $course->id,
            'audience' => 'student',
            'is_active' => 1,
            'button_url' => route('courses.show', $course),
        ]);
    }

    public function test_non_admin_cannot_manage_dashboard_announcements(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.dashboard-announcements.index'))->assertForbidden();
    }
}
