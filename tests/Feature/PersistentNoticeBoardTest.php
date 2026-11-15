<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DashboardAnnouncement;
use App\Models\DashboardAnnouncementRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistentNoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'is_active' => true,
        ]);
    }

    public function test_user_can_view_targeted_notices_and_unread_count(): void
    {
        $branch = $this->branch('Rangpur');
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $notice = DashboardAnnouncement::create([
            'title' => 'Student branch notice',
            'content_type' => 'custom',
            'audience' => 'student',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->get(route('notices.index'))
            ->assertOk()
            ->assertSee('Notice Board')
            ->assertSee('Student branch notice')
            ->assertSee('1 unread');

        $this->assertDatabaseHas('dashboard_announcement_reads', [
            'announcement_id' => $notice->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_user_can_mark_visible_notice_as_read(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $notice = DashboardAnnouncement::create([
            'title' => 'Exam timetable notice',
            'content_type' => 'custom',
            'audience' => 'student',
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->post(route('notices.read', $notice))
            ->assertRedirect();

        $this->assertDatabaseHas('dashboard_announcement_reads', [
            'announcement_id' => $notice->id,
            'user_id' => $student->id,
        ]);
        $this->assertNotNull(DashboardAnnouncementRead::query()
            ->where('announcement_id', $notice->id)
            ->where('user_id', $student->id)
            ->value('read_at'));
    }

    public function test_user_cannot_read_notice_for_another_role_or_branch(): void
    {
        $rangpur = $this->branch('Rangpur');
        $dhaka = $this->branch('Dhaka');
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $rangpur->id]);
        $notice = DashboardAnnouncement::create([
            'title' => 'Private Dhaka guardian notice',
            'content_type' => 'custom',
            'audience' => 'guardian',
            'branch_id' => $dhaka->id,
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->get(route('notices.index'))
            ->assertOk()
            ->assertDontSee('Private Dhaka guardian notice');

        $this->actingAs($student)
            ->post(route('notices.read', $notice))
            ->assertNotFound();

        $this->assertDatabaseMissing('dashboard_announcement_reads', [
            'announcement_id' => $notice->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_read_status_is_individual_to_each_user(): void
    {
        $first = User::factory()->create(['role' => 'student']);
        $second = User::factory()->create(['role' => 'student']);
        $notice = DashboardAnnouncement::create([
            'title' => 'Shared academy notice',
            'content_type' => 'custom',
            'audience' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($first)->post(route('notices.read', $notice))->assertRedirect();

        $this->actingAs($second)->get(route('notices.index'))
            ->assertOk()
            ->assertSee('Shared academy notice')
            ->assertSee('1 unread');
    }
}
