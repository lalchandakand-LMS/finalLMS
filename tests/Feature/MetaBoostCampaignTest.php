<?php

namespace Tests\Feature;

use App\Models\MetaBoostCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaBoostCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_draft_boost_plan_with_utm_tracking(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.marketing.boost-campaigns.store'), [
                'title' => 'Rangpur IELTS Campaign',
                'destination_url' => 'https://academy.example/courses/ielts?ref=homepage',
                'objective' => 'traffic',
                'target_location' => 'Rangpur, Bangladesh',
                'daily_budget_bdt' => 500,
                'total_budget_bdt' => 5000,
                'starts_at' => '2026-12-01T09:00',
                'ends_at' => '2026-12-10T21:00',
                'audience_description' => 'IELTS learners aged 18+',
            ])
            ->assertRedirect(route('admin.marketing.boost-campaigns.index'))
            ->assertSessionHas('success');

        $campaign = MetaBoostCampaign::firstOrFail();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame($admin->id, $campaign->created_by);
        $this->assertStringContainsString('utm_source=facebook', $campaign->tracking_url);
        $this->assertStringContainsString('utm_medium=paid_social', $campaign->tracking_url);
        $this->assertStringContainsString('utm_campaign=rangpur-ielts-campaign', $campaign->tracking_url);
        $this->assertStringContainsString('ref=homepage', $campaign->tracking_url);
    }

    public function test_boost_plan_requires_a_valid_https_or_http_destination(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.marketing.boost-campaigns.index'))
            ->post(route('admin.marketing.boost-campaigns.store'), [
                'title' => 'Invalid destination',
                'destination_url' => 'javascript:alert(1)',
                'objective' => 'traffic',
                'daily_budget_bdt' => 100,
            ])
            ->assertSessionHasErrors('destination_url');

        $this->assertDatabaseCount('meta_boost_campaigns', 0);
    }

    public function test_admin_can_record_results_and_only_delete_draft_plans(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $campaign = MetaBoostCampaign::create([
            'title' => 'Test campaign',
            'destination_url' => 'https://academy.example/course',
            'objective' => 'traffic',
            'daily_budget_bdt' => 300,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.marketing.boost-campaigns.update', $campaign), [
                'status' => 'running',
                'meta_campaign_id' => 'meta-123',
                'actual_spend_bdt' => 1200,
                'impressions' => 9000,
                'clicks' => 450,
                'leads' => 25,
                'conversions' => 12,
                'notes' => 'Results imported from Ads Manager',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meta_boost_campaigns', [
            'id' => $campaign->id,
            'status' => 'running',
            'meta_campaign_id' => 'meta-123',
            'actual_spend_bdt' => 1200,
            'impressions' => 9000,
            'clicks' => 450,
            'leads' => 25,
            'conversions' => 12,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.marketing.boost-campaigns.destroy', $campaign))
            ->assertStatus(422);

        $this->assertDatabaseHas('meta_boost_campaigns', ['id' => $campaign->id]);
    }

    public function test_non_admin_cannot_access_boost_planner(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->get(route('admin.marketing.boost-campaigns.index'))
            ->assertForbidden();
    }
}
