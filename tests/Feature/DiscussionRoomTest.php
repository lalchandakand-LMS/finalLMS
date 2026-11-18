<?php

namespace Tests\Feature;

use App\Models\DiscussionRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_public_room_and_members_can_exchange_messages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($teacher)
            ->post(route('community.rooms.store'), [
                'name' => 'Spoken English Club',
                'description' => 'Practice English together.',
                'visibility' => 'public',
            ])->assertRedirect();

        $room = DiscussionRoom::query()->firstOrFail();

        $this->actingAs($student)
            ->post(route('community.rooms.join', $room))
            ->assertRedirect(route('community.rooms.show', $room));

        $this->post(route('community.rooms.message', $room), ['body' => 'Hello everyone!'])
            ->assertRedirect();

        $this->assertDatabaseHas('discussion_messages', [
            'room_id' => $room->id,
            'user_id' => $student->id,
            'body' => 'Hello everyone!',
        ]);
    }

    public function test_non_members_cannot_read_or_send_messages_in_private_rooms(): void
    {
        $owner = User::factory()->create(['role' => 'teacher', 'email_verified_at' => now()]);
        $outsider = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $room = DiscussionRoom::create([
            'created_by' => $owner->id,
            'name' => 'Private Faculty Room',
            'visibility' => 'private',
            'is_active' => true,
        ]);

        $this->actingAs($outsider)
            ->get(route('community.rooms.show', $room))
            ->assertForbidden();

        $this->post(route('community.rooms.join', $room))->assertForbidden();
        $this->post(route('community.rooms.message', $room), ['body' => 'Intrusion'])
            ->assertForbidden();

        $this->assertDatabaseMissing('discussion_messages', ['room_id' => $room->id]);
    }

    public function test_students_cannot_create_discussion_rooms(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->post(route('community.rooms.store'), [
                'name' => 'Student Room',
                'visibility' => 'public',
            ])
            ->assertForbidden();
    }
}
