<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_ticket_and_receive_rule_based_diagnostic(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

        $this->actingAs($student)
            ->post(route('support.store'), [
                'subject' => 'বিকাশ পেমেন্ট সমস্যা',
                'description' => 'Payment completed but course is still locked.',
            ])
            ->assertRedirect(route('support.index'));

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $student->id,
            'category' => 'payment',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $this->assertStringContainsString('PIN', SupportTicket::query()->firstOrFail()->diagnostic_summary);
    }

    public function test_users_can_only_see_their_own_tickets_and_admin_can_manage_status(): void
    {
        $owner = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $other = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $ticket = SupportTicket::create([
            'user_id' => $owner->id,
            'subject' => 'Login problem',
            'description' => 'I cannot sign in to my account.',
            'category' => 'login',
            'priority' => 'normal',
            'status' => 'open',
            'diagnostic_summary' => 'Reset your password.',
        ]);

        $this->actingAs($other)->get(route('support.index'))->assertDontSee('Login problem');
        $this->patch(route('support.update', $ticket), ['status' => 'resolved', 'priority' => 'normal'])->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('support.update', $ticket), ['status' => 'resolved', 'priority' => 'high'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'status' => 'resolved', 'priority' => 'high']);
    }
}
