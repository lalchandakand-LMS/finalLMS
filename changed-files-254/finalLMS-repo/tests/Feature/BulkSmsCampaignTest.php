<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkSmsCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_sends_only_to_verified_users_who_opted_in(): void
    {
        config([
            'services.twilio.sid' => 'AC'.str_repeat('0', 32),
            'services.twilio.token' => 'test-token',
            'services.twilio.from' => '+15005550006',
            'queue.default' => 'sync',
        ]);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        $admin = User::factory()->create(['role' => 'admin']);
        $subscribed = User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801712345678',
            'phone_verified_at' => now(),
            'sms_marketing_opt_in' => true,
        ]);
        User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801812345678',
            'phone_verified_at' => now(),
            'sms_marketing_opt_in' => false,
        ]);
        User::factory()->create([
            'role' => 'student',
            'phone_number' => '+8801912345678',
            'phone_verified_at' => null,
            'sms_marketing_opt_in' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.sms-campaigns.store'), [
                'title' => 'New English course',
                'message' => 'Enrollment is open now.',
                'audience' => 'all',
            ])
            ->assertRedirect(route('admin.sms-campaigns.index'));

        $this->assertDatabaseHas('sms_campaigns', [
            'title' => 'New English course',
            'recipient_count' => 1,
            'sent_count' => 1,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('sms_campaign_recipients', [
            'user_id' => $subscribed->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('sms_campaign_recipients', 1);
        Http::assertSent(fn ($request) => $request['To'] === $subscribed->phone_number);

        $this->get(route('admin.sms-campaigns.index'))
            ->assertOk()
            ->assertSee('Bulk SMS campaigns')
            ->assertSee('New English course')
            ->assertSee('1 sent');
    }
}
