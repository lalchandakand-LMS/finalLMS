<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\SupportTicket;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_view_and_reply_to_their_support_ticket(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $token = $this->token($student);

        $created = $this->withToken($token)->postJson('/api/v1/my/support-tickets', [
            'subject' => 'Cannot open lesson video',
            'message' => 'The video player shows an error on my phone.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.subject', 'Cannot open lesson video');

        $ticketId = $created->json('data.id');

        $this->withToken($token)->getJson('/api/v1/my/support-tickets')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ticketId)
            ->assertJsonPath('data.0.last_message', 'The video player shows an error on my phone.');

        $this->withToken($token)->getJson('/api/v1/my/support-tickets/'.$ticketId)
            ->assertOk()
            ->assertJsonPath('data.messages.0.sender_role', 'student');

        $this->withToken($token)->postJson('/api/v1/my/support-tickets/'.$ticketId.'/messages', [
            'message' => 'I have attached more details in this reply.',
        ])->assertCreated()
            ->assertJsonPath('data.sender_role', 'student');

        $this->assertDatabaseCount('support_ticket_messages', 2);
    }

    public function test_student_cannot_view_or_reply_to_another_students_ticket(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'subject' => 'Private support request',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $token = $this->token($other);

        $this->withToken($token)->getJson('/api/v1/my/support-tickets/'.$ticket->id)->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/my/support-tickets/'.$ticket->id.'/messages', [
            'message' => 'Trying to access another ticket.',
        ])->assertNotFound();
    }

    public function test_closed_support_ticket_rejects_student_replies(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $ticket = SupportTicket::create([
            'user_id' => $student->id,
            'subject' => 'Closed request',
            'status' => 'closed',
            'closed_at' => now(),
            'last_message_at' => now(),
        ]);

        $this->withToken($this->token($student))
            ->postJson('/api/v1/my/support-tickets/'.$ticket->id.'/messages', [
                'message' => 'Please reopen this request.',
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('support_ticket_messages', 0);
    }

    public function test_admin_can_reply_and_close_a_student_support_ticket(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $student->id,
            'subject' => 'Need help with payment',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $ticket->messages()->create([
            'sender_id' => $student->id,
            'sender_role' => 'student',
            'body' => 'My payment reference is not showing.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('My payment reference is not showing.');

        $this->actingAs($admin)
            ->post(route('admin.support-tickets.reply', $ticket), [
                'message' => 'We have checked your payment. Please refresh your account.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticket->id,
            'sender_id' => $admin->id,
            'sender_role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.support-tickets.status', $ticket), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'closed']);
    }

    private function token(User $student): string
    {
        $plain = Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', Str::random(64)),
            'device_name' => 'Support API test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        ApiToken::create([
            'user_id' => $student->id,
            'student_device_id' => $device->id,
            'name' => 'Support API test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
