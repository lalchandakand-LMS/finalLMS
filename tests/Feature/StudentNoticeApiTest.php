<?php

namespace Tests\\Feature;

use App\\Models\\ApiToken;
use App\\Models\\Branch;
use App\\Models\\DashboardAnnouncement;
use App\\Models\\StudentDevice;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Str;
use Tests\\TestCase;

class StudentNoticeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_only_active_notices_for_their_role_and_branch(): void
    {
        $branch = Branch::create(['name' => 'Rajshahi', 'slug' => 'rajshahi', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Dhaka', 'slug' => 'dhaka', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $token = $this->token($student);

        $visible = $this->notice(['title' => 'Student branch notice', 'audience' => 'student', 'branch_id' => $branch->id]);
        $global = $this->notice(['title' => 'Academy-wide notice', 'audience' => 'all']);
        $this->notice(['title' => 'Wrong branch notice', 'audience' => 'student', 'branch_id' => $otherBranch->id]);
        $this->notice(['title' => 'Guardian-only notice', 'audience' => 'guardian']);
        $this->notice(['title' => 'Expired notice', 'ends_at' => now()->subMinute()]);
        $this->notice(['title' => 'Future notice', 'starts_at' => now()->addDay()]);

        $this->withToken($token)->getJson('/api/v1/my/notices')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 2)
            ->assertJsonFragment(['id' => $visible->id, 'title' => 'Student branch notice'])
            ->assertJsonFragment(['id' => $global->id, 'title' => 'Academy-wide notice'])
            ->assertJsonMissing(['title' => 'Wrong branch notice'])
            ->assertJsonMissing(['title' => 'Guardian-only notice'])
            ->assertJsonMissing(['title' => 'Expired notice'])
            ->assertJsonMissing(['title' => 'Future notice']);
    }

    public function test_student_can_mark_a_visible_notice_read_and_unread_count_updates(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->token($student);
        $notice = $this->notice(['title' => 'Exam schedule']);

        $this->withToken($token)->postJson('/api/v1/notices/'.$notice->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $notice->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('dashboard_announcement_reads', [
            'announcement_id' => $notice->id,
            'user_id' => $student->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/my/notices')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0)
            ->assertJsonPath('data.0.is_read', true);

        $this->withToken($token)->postJson('/api/v1/notices/'.$notice->id.'/read')->assertOk();
        $this->assertDatabaseCount('dashboard_announcement_reads', 1);
    }

    public function test_student_cannot_read_another_role_or_branch_notice(): void
    {
        $branch = Branch::create(['name' => 'Rajshahi', 'slug' => 'rajshahi', 'is_active' => true]);
        $otherBranch = Branch::create(['name' => 'Dhaka', 'slug' => 'dhaka', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'branch_id' => $branch->id]);
        $token = $this->token($student);
        $notice = $this->notice(['title' => 'Private notice', 'audience' => 'guardian', 'branch_id' => $otherBranch->id]);

        $this->withToken($token)->postJson('/api/v1/notices/'.$notice->id.'/read')->assertNotFound();
        $this->assertDatabaseCount('dashboard_announcement_reads', 0);
    }

    public function test_non_student_cannot_access_student_notice_api(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $token = $this->token($teacher);

        $this->withToken($token)->getJson('/api/v1/my/notices')->assertForbidden();
    }

    private function notice(array $overrides = []): DashboardAnnouncement
    {
        return DashboardAnnouncement::create(array_merge([
            'title' => 'Academy notice',
            'body' => 'Important academy information.',
            'content_type' => 'custom',
            'audience' => 'all',
            'is_active' => true,
        ], $overrides));
    }

    private function token(User $user): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $user->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Notice API test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $user->id,
            'student_device_id' => $device->id,
            'name' => 'Notice API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
