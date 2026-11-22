<?php

namespace Tests\Feature;

use App\Models\DashboardAnnouncement;
use App\Models\MetaBoostCampaign;
use App\Models\SmsCampaign;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCenterMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_live_lms_broadcast_and_marketing_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);

        SmsCampaign::create([
            'created_by' => $admin->id,
            'title' => 'Admission reminder',
            'message' => 'Admission is open.',
            'audience' => 'student',
            'status' => 'completed',
            'recipient_count' => 4,
            'sent_count' => 3,
            'failed_count' => 1,
        ]);

        MetaBoostCampaign::create([
            'created_by' => $admin->id,
            'title' => 'Spoken English launch',
            'destination_url' => 'https://example.test/courses',
            'objective' => 'traffic',
            'daily_budget_bdt' => 500,
            'status' => 'draft',
            'actual_spend_bdt' => 125.50,
        ]);

        SupportTicket::create([
            'user_id' => $student->id,
            'subject' => 'Cannot access lesson',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        DashboardAnnouncement::create([
            'title' => 'New batch announced',
            'body' => 'Registration is open.',
            'content_type' => 'custom',
            'audience' => 'student',
            'priority' => 10,
            'is_active' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('Campaign performance snapshot')
            ->assertSee('SMS campaigns')
            ->assertSee('3 sent')
            ->assertSee('1 failed')
            ->assertSee('Meta boost plans')
            ->assertSee('৳125.50')
            ->assertSee('Open support tickets')
            ->assertSee('Manage in-app announcements');
    }

    public function test_non_admin_cannot_open_admin_marketing_center(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get(route('admin.marketing.index'))
            ->assertForbidden();
    }
}
