<?php

namespace Tests\Feature;

use App\Models\VisitorPopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorPopupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_guest_popup_is_rendered_for_visitors(): void
    {
        VisitorPopup::create([
            'title' => 'Welcome offer',
            'body' => 'Register today for a special offer.',
            'popup_type' => 'registration',
            'audience' => 'guests',
            'frequency' => 'once_per_session',
            'delay_seconds' => 0,
            'is_active' => true,
        ]);

        $this->get(route('landing'))
            ->assertOk()
            ->assertSee('visitor-promo-overlay', false)
            ->assertSee('Welcome offer')
            ->assertSee(route('register'));
    }

    public function test_inactive_or_wrong_audience_popup_is_not_rendered(): void
    {
        VisitorPopup::create([
            'title' => 'Members only offer',
            'popup_type' => 'offer',
            'audience' => 'members',
            'frequency' => 'once_per_day',
            'is_active' => true,
        ]);

        $this->get(route('landing'))->assertOk()->assertDontSee('Members only offer');

        VisitorPopup::create([
            'title' => 'Disabled offer',
            'popup_type' => 'offer',
            'audience' => 'all',
            'is_active' => false,
        ]);

        $this->get(route('landing'))->assertDontSee('Disabled offer');
    }

    public function test_admin_can_create_and_manage_popup_campaigns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.visitor-popups.store'), [
            'title' => 'Course launch banner',
            'body' => 'New course now available.',
            'popup_type' => 'banner',
            'audience' => 'all',
            'frequency' => 'once_per_day',
            'delay_seconds' => 2,
            'button_label' => 'View course',
            'button_url' => '/courses',
            'is_active' => '1',
            'priority' => 10,
        ])->assertRedirect(route('admin.visitor-popups.index'));

        $popup = VisitorPopup::firstOrFail();
        $this->assertTrue($popup->is_active);
        $this->assertSame('/courses', $popup->button_url);

        $this->actingAs($admin)->patch(route('admin.visitor-popups.toggle', $popup))
            ->assertRedirect();
        $this->assertFalse($popup->fresh()->is_active);
    }

    public function test_unsafe_popup_destinations_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.visitor-popups.create'))
            ->post(route('admin.visitor-popups.store'), [
                'title' => 'Unsafe link',
                'popup_type' => 'ad',
                'audience' => 'all',
                'frequency' => 'every_page',
                'delay_seconds' => 0,
                'button_url' => 'javascript:alert(1)',
            ])->assertSessionHasErrors('button_url');

        $this->assertDatabaseCount('visitor_popups', 0);
    }

    public function test_non_admin_cannot_manage_popup_campaigns(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.visitor-popups.index'))->assertForbidden();
    }
}
