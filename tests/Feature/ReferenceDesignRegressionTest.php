<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceDesignRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_keeps_reference_sections_and_responsive_design(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Analytics overview')
            ->assertSee('Course progress')
            ->assertSee('Upcoming classes')
            ->assertSee('Top students')
            ->assertSee('Recent activity')
            ->assertSee('Quick actions')
            ->assertSee('prefers-reduced-motion', false)
            ->assertSee('aec-dashboard-lower', false);
    }

    public function test_public_homepage_keeps_slider_learning_paths_and_two_direction_reviews(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('home-carousel', false)
            ->assertSee('home-overview', false)
            ->assertSee('home-paths', false)
            ->assertSee('home-testimonial-track--reverse', false)
            ->assertSee('home-testimonial-viewport:hover', false)
            ->assertSee('prefers-reduced-motion', false);
    }
}
