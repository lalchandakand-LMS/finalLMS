<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WebsiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_render_safe_homepage_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'sections' => [
                [
                    'title' => 'Our learning approach',
                    'body' => '<script>alert(1)</script>Learn with confidence.',
                    'button_label' => 'Browse courses',
                    'button_url' => 'https://example.com/courses',
                    'enabled' => '1',
                ],
            ],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'website_sections']);
        Auth::logout();
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Learn with confidence.');
    }

    public function test_admin_can_save_branding_homepage_content_and_slider_from_builder(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $logo = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/logo.png',
            'original_name' => 'logo.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);
        $slide = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/hero.png',
            'original_name' => 'hero.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'site_name' => 'Alom English Center',
            'site_logo_media_id' => $logo->id,
            'site_favicon_media_id' => $logo->id,
            'site_primary_color' => '#123456',
            'home_title' => 'Learn English confidently',
            'home_subtitle' => 'Courses for every learner.',
            'home_featured_title' => 'Featured courses',
            'footer_title' => 'Alom Learning',
            'footer_text' => 'Learn with confidence.',
            'footer_links' => [
                ['label' => 'Courses', 'url' => '/courses'],
                ['label' => 'Unsafe link', 'url' => 'javascript:alert(1)'],
            ],
            'header_links' => [
                ['label' => 'Admissions', 'url' => '/admissions'],
                ['label' => 'Unsafe menu', 'url' => 'javascript:alert(1)'],
            ],
            'home_slides' => [[
                'enabled' => '1', 'image_id' => $slide->id, 'title' => 'New batch',
                'subtitle' => 'Admission is open', 'button_label' => 'Apply now', 'button_url' => '/courses',
            ]],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Alom English Center']);
        $this->assertDatabaseHas('settings', ['key' => 'site_logo_media_id', 'value' => (string) $logo->id]);
        $this->assertDatabaseHas('settings', ['key' => 'home_title', 'value' => 'Learn English confidently']);
        $savedSlides = json_decode((string) Setting::value('home_slides'), true);
        $this->assertSame($slide->id, $savedSlides[0]['image_id']);
        $savedFooterLinks = json_decode((string) Setting::value('footer_links'), true);
        $this->assertSame([['label' => 'Courses', 'url' => '/courses']], $savedFooterLinks);
        $this->assertDatabaseHas('settings', ['key' => 'footer_title', 'value' => 'Alom Learning']);
        $savedHeaderLinks = json_decode((string) Setting::value('header_links'), true);
        $this->assertSame([['label' => 'Admissions', 'url' => '/admissions']], $savedHeaderLinks);
        Auth::logout();
        $this->get('/')->assertOk()->assertSee('Alom Learning')->assertSee('Learn with confidence.')->assertSee('Courses')->assertSee('Admissions')->assertDontSee('Unsafe menu');
    }

    public function test_homepage_builder_exposes_editable_ctas_statistics_and_reason_heading(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'home_cta_primary' => 'Explore classes',
            'home_cta_secondary' => 'Student login',
            'home_reason_title' => 'Why choose Alom',
            'home_metric_1_value' => '2500+',
            'home_metric_1_label' => 'Students',
            'home_metric_2_value' => '35+',
            'home_metric_2_label' => 'Teachers',
            'home_metric_3_value' => '98%',
            'home_metric_3_label' => 'Success rate',
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'home_cta_primary', 'value' => 'Explore classes']);
        $this->assertDatabaseHas('settings', ['key' => 'home_metric_1_value', 'value' => '2500+']);
        $this->assertDatabaseHas('settings', ['key' => 'home_reason_title', 'value' => 'Why choose Alom']);

        Auth::logout();
        $this->get('/')->assertOk()
            ->assertSee('Explore classes')
            ->assertSee('Why choose Alom')
            ->assertSee('2500+')
            ->assertSee('Success rate');
    }

    public function test_homepage_only_displays_published_courses(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Published course', 'status' => 'published']);
        Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'Private draft course', 'status' => 'draft']);

        $this->get('/')->assertOk()
            ->assertSee('Published course')
            ->assertDontSee('Private draft course');
    }

    public function test_non_admin_cannot_access_website_builder(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.website-builder.index'))->assertForbidden();
    }

    public function test_branding_media_must_be_an_image_asset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $document = MediaAsset::create([
            'uploaded_by' => $admin->id, 'disk' => 'public', 'path' => 'media/handbook.pdf',
            'original_name' => 'handbook.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
        ]);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'site_logo_media_id' => $document->id,
        ])->assertSessionHasErrors('site_logo_media_id');
    }

    public function test_homepage_section_image_must_be_an_image_asset(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $document = MediaAsset::create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => 'media/handbook.pdf',
            'original_name' => 'handbook.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'sections' => [['title' => 'Study materials', 'image_id' => $document->id]],
        ])->assertSessionHasErrors('sections.0.image_id');
    }

}
