<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_profile(): void
    {
        Notification::fake();

        $student = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'student@lms.test',
            'role' => 'student',
        ]);

        $this->actingAs($student)
            ->post(route('profile.update'), [
                'name' => 'Updated Student',
                'email' => 'updated@student.com',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => 'Updated Student',
            'email' => 'updated@student.com',
            'email_verified_at' => null,
        ]);

        Notification::assertSentTo($student->fresh(), VerifyEmail::class);
    }
}
