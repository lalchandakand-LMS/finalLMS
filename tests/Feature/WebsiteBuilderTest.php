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
                    'button_url' => '/courses',
                    'background_color' => '#f0f4ff',
                    'text_color' => '#112233',
                    'spacing' => 'airy',
                    'layout' => 'centered',
                    'enabled' => '1',
                ],
            ],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'website_sections']);
        Auth::logout();
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('Learn with confidence.')
            ->assertSee('background-color:#f0f4ff', false)
            ->assertSee('color:#112233', false)
            ->assertSee('padding:40px', false)
            ->assertSee('grid-template-columns:1fr;text-align:center', false);
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


    public function test_admin_can_select_one_of_fifteen_homepage_presets_without_changing_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('home_title', 'Existing academy headline');
        Setting::setValue('website_sections', json_encode([['title' => 'Existing section', 'enabled' => true]]));

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'homepage_template' => 'midnight-campus',
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertDatabaseHas('settings', ['key' => 'homepage_template', 'value' => 'midnight-campus']);
        $this->assertSame('Existing academy headline', Setting::value('home_title'));
        $this->assertSame('Existing section', json_decode((string) Setting::value('website_sections'), true)[0]['title']);

        Auth::logout();
        $this->get('/')->assertOk()->assertSee('homepage-template--midnight-campus');
    }

    public function test_homepage_builder_rejects_unknown_template_keys(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'homepage_template' => 'untrusted-template',
        ])->assertSessionHasErrors('homepage_template');
    }

    public function test_homepage_builder_offers_exactly_fifteen_design_presets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.website-builder.index'))
            ->assertOk()
            ->assertSee('15 designs')
            ->assertSee('Academy Modern')
            ->assertSee('Community Campus')
            ->assertSee('Reusable section block library')
            ->assertSee('Call to action');

        $this->assertCount(15, \App\Http\Controllers\WebsiteBuilderController::homepageTemplates());
    }

    public function test_admin_can_customize_global_typography_and_background_theme(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'homepage_template' => 'academy-modern',
            'site_primary_color' => '#123456',
            'site_background_color' => '#fef3c7',
            'site_background_style' => 'warm-gradient',
            'site_font_family' => 'poppins',
            'site_heading_scale' => '1.2',
            'site_body_scale' => '1.05',
        ])->assertRedirect(route('admin.website-builder.index'));
        $this->assertSame('#fef3c7', Setting::value('site_background_color'));
        $this->assertSame('warm-gradient', Setting::value('site_background_style'));
        $this->assertSame('poppins', Setting::value('site_font_family'));
        $this->assertSame('1.2', Setting::value('site_heading_scale'));
        $this->assertSame('1.05', Setting::value('site_body_scale'));
        auth()->logout();
        $this->get(route('landing'))->assertOk()->assertSee('--site-page-background', false)->assertSee('Poppins, Arial, sans-serif', false);
    }

    public function test_invalid_global_theme_options_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'site_background_color' => 'red; background:url(javascript:alert(1))',
            'site_background_style' => 'url-javascript',
            'site_font_family' => 'expression',
            'site_heading_scale' => '99',
            'site_body_scale' => '-5',
        ])->assertSessionHasErrors(['site_background_color', 'site_background_style', 'site_font_family', 'site_heading_scale', 'site_body_scale']);
    }

    public function test_admin_can_save_preview_and_publish_a_homepage_draft(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('home_title', 'Public headline');

        $this->actingAs($admin)->post(route('admin.website-builder.draft'), [
            'homepage_template' => 'midnight-campus',
            'home_title' => 'Draft headline',
            'home_subtitle' => 'Draft-only copy',
            'header_links' => [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertSame('Public headline', Setting::value('home_title'));
        $this->get(route('admin.website-builder.index'))->assertOk()->assertSee('Draft headline');
        $this->get(route('admin.website-builder.preview'))
            ->assertOk()
            ->assertSee('Draft headline')
            ->assertSee('Unpublished preview')
            ->assertSee('data-preview-size="mobile"', false)
            ->assertSee('data-preview-size="tablet"', false)
            ->assertDontSee('javascript:alert(1)');

        $this->post(route('admin.website-builder.publish-draft'))
            ->assertRedirect(route('admin.website-builder.index'));

        $this->assertSame('Draft headline', Setting::value('home_title'));
        $this->assertSame('midnight-campus', Setting::value('homepage_template'));
        $this->assertDatabaseHas('settings', ['key' => 'homepage_revisions']);
    }

    public function test_admin_can_restore_a_previous_homepage_revision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Setting::setValue('home_title', 'Version one');

        $this->actingAs($admin)->post(route('admin.website-builder.update'), [
            'home_title' => 'Version two',
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->post(route('admin.website-builder.update'), [
            'home_title' => 'Version three',
        ])->assertRedirect(route('admin.website-builder.index'));

        $revisions = json_decode((string) Setting::value('homepage_revisions'), true);
        $revisionToRestore = $revisions[count($revisions) - 1];

        $this->post(route('admin.website-builder.rollback'), [
            'revision_id' => $revisionToRestore['id'],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertSame('Version two', Setting::value('home_title'));
    }

    public function test_non_admin_cannot_save_or_preview_homepage_drafts(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('admin.website-builder.draft'), ['home_title' => 'No access'])
            ->assertForbidden();

        $this->get(route('admin.website-builder.preview'))->assertForbidden();
    }


    public function test_admin_can_save_insert_and_delete_custom_homepage_blocks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-builder.custom-blocks.store'), [
            'name' => 'Blue CTA',
            'title' => 'Join our next batch',
            'body' => 'Practical English lessons.',
            'button_label' => 'View courses',
            'button_url' => '/courses',
            'background_color' => '#123456',
            'text_color' => '#ffffff',
            'spacing' => 'airy',
            'layout' => 'centered',
        ])->assertRedirect(route('admin.website-builder.index'));

        $blocks = json_decode((string) Setting::value('homepage_custom_blocks'), true);
        $this->assertCount(1, $blocks);
        $this->assertSame('Blue CTA', $blocks[0]['name']);
        $this->assertSame('/courses', $blocks[0]['button_url']);

        $this->actingAs($admin)->get(route('admin.website-builder.index'))
            ->assertOk()
            ->assertSee('My saved custom blocks')
            ->assertSee('Blue CTA')
            ->assertSee('data-insert-custom-block');

        $this->actingAs($admin)->delete(route('admin.website-builder.custom-blocks.destroy'), [
            'block_id' => $blocks[0]['id'],
        ])->assertRedirect(route('admin.website-builder.index'));

        $this->assertSame([], json_decode((string) Setting::value('homepage_custom_blocks'), true));
    }

    public function test_non_admin_cannot_manage_custom_homepage_blocks(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->post(route('admin.website-builder.custom-blocks.store'), [
            'name' => 'No access', 'title' => 'No access',
        ])->assertForbidden();
    }

}
