<?php

namespace Tests\Feature;

use App\Jobs\SendSmsCampaignRecipient;
use App\Models\SmsCampaign;
use App\Models\SmsCampaignRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsDeliveryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_campaign_can_target_guardians_and_branch_managers(): void
    {
        Queue::fake();
        config([
            'services.twilio.sid' => 'test-account-sid',
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15005550006',
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $guardian = User::factory()->create([
            'role' => 'guardian',
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
            'sms_marketing_opt_in' => true,
        ]);
        $manager = User::factory()->create([
            'role' => 'branch_manager',
            'phone_number' => '+8801812345678',
            'phone_verified_at' => now(),
            'sms_marketing_opt_in' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.sms-campaigns.store'), [
            'title' => 'Guardian reminder',
            'message' => 'Academy update',
            'audience' => 'guardian',
        ])->assertRedirect(route('admin.sms-campaigns.index'));

        $campaign = SmsCampaign::query()->where('title', 'Guardian reminder')->firstOrFail();
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertDatabaseHas('sms_campaign_recipients', [
            'sms_campaign_id' => $campaign->id,
            'user_id' => $guardian->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('sms_campaign_recipients', [
            'sms_campaign_id' => $campaign->id,
            'user_id' => $manager->id,
        ]);
        Queue::assertPushed(SendSmsCampaignRecipient::class, 1);
    }

    public function test_admin_can_view_paginated_campaign_delivery_report(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $student = User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801712345678',
            'email_verified_at' => now(),
        ]);
        $campaign = SmsCampaign::create([
            'created_by' => $admin->id,
            'title' => 'Exam reminder',
            'message' => 'Your exam is tomorrow.',
            'audience' => 'student',
            'status' => 'completed',
            'recipient_count' => 1,
            'sent_count' => 1,
        ]);
        SmsCampaignRecipient::create([
            'sms_campaign_id' => $campaign->id,
            'user_id' => $student->id,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sms-campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Exam reminder')
            ->assertSee('Your exam is tomorrow.')
            ->assertSee('+8801712345678')
            ->assertSee('Sent');
    }

    public function test_admin_can_see_secure_twilio_setup_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        config([
            'services.twilio.sid' => null,
            'services.twilio.token' => null,
            'services.twilio.from' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sms-campaigns.index'))
            ->assertOk()
            ->assertSee('SMS gateway')
            ->assertSee('TWILIO_ACCOUNT_SID')
            ->assertSee('TWILIO_AUTH_TOKEN')
            ->assertSee('TWILIO_FROM')
            ->assertSee('Needs setup');
    }

    public function test_sms_campaign_rejects_unsupported_audience(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->from(route('admin.sms-campaigns.index'))
            ->post(route('admin.sms-campaigns.store'), [
                'title' => 'Invalid audience',
                'message' => 'Test message',
                'audience' => 'unknown',
            ])
            ->assertSessionHasErrors('audience');
    }
}
