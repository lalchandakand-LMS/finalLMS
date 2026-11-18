<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsitePageBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_publish_and_render_a_custom_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-pages.store'), [
            'title' => 'Our English Programs',
            'slug' => 'our-english-programs',
            'menu_label' => 'Programs',
            'template' => 'wide',
            'status' => 'published',
            'show_in_menu' => '1',
            'menu_order' => '2',
            'seo_title' => 'English Programs | Alom',
            'seo_description' => 'Explore our English programs.',
            'sections' => [[
                'type' => 'hero',
                'title' => 'Learn with confidence',
                'body' => '<script>alert(1)</script>Build practical English skills.',
                'button_label' => 'Browse courses',
                'button_url' => '/courses',
                'background_color' => '#f0f4ff',
                'text_color' => '#112233',
                'layout' => 'centered',
                'enabled' => '1',
            ]],
        ])->assertRedirect(route('admin.website-pages.index'));

        $this->assertDatabaseHas('website_pages', [
            'slug' => 'our-english-programs',
            'status' => 'published',
            'show_in_menu' => 1,
        ]);

        auth()->logout();
        $this->get(route('landing'))->assertOk()->assertSee('Programs')->assertSee('/pages/our-english-programs');

        $this->get(route('website-pages.show', 'our-english-programs'))
            ->assertOk()
            ->assertSee('Learn with confidence')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Build practical English skills.')
            ->assertSee('Browse courses')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_draft_custom_pages_are_not_publicly_accessible_or_in_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        WebsitePage::create([
            'title' => 'Private Draft',
            'slug' => 'private-draft',
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'show_in_menu' => true,
            'created_by' => $admin->id,
        ]);

        auth()->logout();
        $this->get(route('website-pages.show', 'private-draft'))->assertNotFound();
        $this->get(route('landing'))->assertOk()->assertDontSee('Private Draft');
    }

    public function test_custom_page_management_is_admin_only(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.website-pages.index'))->assertForbidden();
        $this->post(route('admin.website-pages.store'), [
            'title' => 'Unauthorized',
            'template' => 'standard',
            'status' => 'published',
        ])->assertForbidden();
    }

    public function test_page_slug_is_generated_from_title_when_blank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.website-pages.store'), [
            'title' => 'My New Page',
            'template' => 'standard',
            'status' => 'draft',
        ])->assertRedirect(route('admin.website-pages.index'));

        $this->assertDatabaseHas('website_pages', ['slug' => 'my-new-page']);
    }
    public function test_admin_can_edit_duplicate_and_delete_custom_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = WebsitePage::create([
            'title' => 'Original page',
            'slug' => 'original-page',
            'template' => 'standard',
            'sections' => [['type' => 'text', 'title' => 'Original', 'enabled' => true]],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.website-pages.update', $page), [
            'title' => 'Updated page',
            'slug' => 'updated-page',
            'template' => 'centered',
            'status' => 'published',
            'show_in_menu' => '1',
            'sections' => [['type' => 'text', 'title' => 'Updated content', 'enabled' => '1']],
        ])->assertRedirect(route('admin.website-pages.index'));

        $page->refresh();
        $this->assertSame('Updated page', $page->title);
        $this->assertSame('published', $page->status);

        $this->post(route('admin.website-pages.duplicate', $page))
            ->assertRedirect();
        $copy = WebsitePage::where('slug', 'updated-page-copy')->first();
        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status);
        $this->assertFalse($copy->show_in_menu);

        $this->delete(route('admin.website-pages.destroy', $page))
            ->assertRedirect();
        $this->assertDatabaseMissing('website_pages', ['id' => $page->id]);
    }

    public function test_page_section_editor_exposes_drag_reordering_and_accessible_move_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.website-pages.create'))
            ->assertOk()
            ->assertSee('draggable="true"', false)
            ->assertSee('data-drag-handle', false)
            ->assertSee('aria-label="Move section up"', false)
            ->assertSee('aria-label="Move section down"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('page-section-status', false);
    }


    public function test_admin_can_preview_custom_pages_at_desktop_tablet_and_mobile_widths(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = WebsitePage::create([
            'title' => 'Responsive preview page',
            'slug' => 'responsive-preview-page',
            'template' => 'standard',
            'sections' => [['type' => 'text', 'title' => 'Preview content', 'enabled' => true]],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.website-pages.preview', ['websitePage' => $page->id, 'device' => 'mobile']))
            ->assertOk()
            ->assertSee('Responsive page preview')
            ->assertSee('Mobile')
            ->assertSee('Tablet')
            ->assertSee('Desktop')
            ->assertSee('frame=1', false)
            ->assertSee('width:390px', false);

        $this->get(route('admin.website-pages.preview', ['websitePage' => $page->id, 'device' => 'tablet', 'frame' => 1]))
            ->assertOk()
            ->assertSee('Preview content');

        $this->get(route('admin.website-pages.preview', ['websitePage' => $page->id, 'device' => 'phone']))
            ->assertStatus(422);
    }

}
