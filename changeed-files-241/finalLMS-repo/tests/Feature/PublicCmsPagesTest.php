<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_about_and_contact_pages_render_admin_content_as_text(): void
    {
        Setting::setValue('about_title', 'About Aloms English Center');
        Setting::setValue('about_body', "We help learners build confidence.\n<script>alert('xss')</script>");
        Setting::setValue('contact_title', 'Contact our team');
        Setting::setValue('contact_body', 'Phone: 01700000000');

        $this->get('/about')
            ->assertOk()
            ->assertSee('About Aloms English Center')
            ->assertSee('We help learners build confidence.')
            ->assertSee('&lt;script&gt;alert', false);

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Contact our team')
            ->assertSee('Phone: 01700000000');
    }

    public function test_faq_page_only_shows_complete_question_answer_pairs(): void
    {
        Setting::setValue('faq_title', 'Student help');
        Setting::setValue('faq_1_question', 'How do I enroll?');
        Setting::setValue('faq_1_answer', 'Create an account and choose a course.');
        Setting::setValue('faq_2_question', 'Incomplete question');
        Setting::setValue('faq_2_answer', '');

        $this->get('/faq')
            ->assertOk()
            ->assertSee('Student help')
            ->assertSee('How do I enroll?')
            ->assertSee('Create an account and choose a course.')
            ->assertDontSee('Incomplete question');
    }

    public function test_only_admin_can_edit_cms_content(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('admin.settings.update'), [
                'about_title' => 'Not allowed',
            ])
            ->assertForbidden();
    }
}
