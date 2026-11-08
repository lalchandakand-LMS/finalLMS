<?php

namespace Tests\Feature;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_messages_are_visible_only_to_the_two_participants_and_marked_read(): void
    {
        $sender = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $recipient = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $outsider = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($sender)
            ->post(route('community.messages.store', $recipient), ['body' => 'Can you help with lesson 2?'])
            ->assertRedirect(route('community.messages.show', $recipient));

        $message = DirectMessage::query()->firstOrFail();

        $this->actingAs($outsider)
            ->get(route('community.messages.show', $recipient))
            ->assertDontSee('Can you help with lesson 2?');

        $this->actingAs($recipient)
            ->get(route('community.messages.show', $sender))
            ->assertSee('Can you help with lesson 2?');

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_user_cannot_send_a_direct_message_to_themselves(): void
    {
        $user = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('community.messages.store', $user), ['body' => 'Hello'])
            ->assertNotFound();

        $this->assertSame(0, DirectMessage::query()->count());
    }
}
