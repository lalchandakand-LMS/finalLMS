<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsitePage;
use App\Models\WebsitePageRevision;
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

    public function test_admin_can_restore_a_custom_page_revision_without_losing_the_current_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.website-pages.store'), [
            'title' => 'First page version',
            'slug' => 'first-page-version',
            'template' => 'standard',
            'status' => 'published',
            'show_in_menu' => '1',
            'sections' => [['type' => 'text', 'title' => 'Original content', 'body' => 'Original body', 'enabled' => '1']],
        ])->assertRedirect(route('admin.website-pages.index'));

        $page = WebsitePage::where('slug', 'first-page-version')->firstOrFail();
        $firstRevision = $page->revisions()->firstOrFail();
        $this->assertSame(1, $firstRevision->version);
        $this->assertSame('First page version', $firstRevision->snapshot['title']);
        $this->get(route('admin.website-pages.edit', $page))
            ->assertOk()
            ->assertSee('Revision history')
            ->assertSee('Version 1')
            ->assertSee('Restore this version');

        $this->put(route('admin.website-pages.update', $page), [
            'title' => 'Second page version',
            'slug' => 'second-page-version',
            'template' => 'wide',
            'status' => 'draft',
            'show_in_menu' => '0',
            'sections' => [['type' => 'hero', 'title' => 'Changed content', 'body' => 'Changed body', 'enabled' => '1']],
        ])->assertRedirect(route('admin.website-pages.index'));

        $page->refresh();
        $secondRevision = $page->revisions()->firstOrFail();
        $this->assertSame(2, $secondRevision->version);
        $this->assertSame('Second page version', $secondRevision->snapshot['title']);

        $this->post(route('admin.website-pages.revisions.restore', [$page, $firstRevision]))
            ->assertRedirect(route('admin.website-pages.edit', $page))
            ->assertSessionHas('success');

        $page->refresh();
        $this->assertSame('First page version', $page->title);
        $this->assertSame('first-page-version', $page->slug);
        $this->assertSame('published', $page->status);
        $this->assertSame('Original content', $page->sections[0]['title']);
        $this->assertSame(3, $page->revisions()->count());
        $this->assertSame(3, $page->revisions()->firstOrFail()->version);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'website_page.revision_restored',
            'user_id' => $admin->id,
        ]);
    }

    public function test_page_revision_restore_rejects_slug_collision_without_changing_the_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.website-pages.store'), [
            'title' => 'Original slug',
            'slug' => 'revision-slug-collision',
            'template' => 'standard',
            'status' => 'draft',
            'sections' => [['type' => 'text', 'title' => 'Original', 'enabled' => '1']],
        ])->assertRedirect();

        $page = WebsitePage::where('slug', 'revision-slug-collision')->firstOrFail();
        $revision = $page->revisions()->firstOrFail();

        $this->put(route('admin.website-pages.update', $page), [
            'title' => 'Renamed page',
            'slug' => 'renamed-page',
            'template' => 'standard',
            'status' => 'draft',
            'sections' => [['type' => 'text', 'title' => 'Renamed', 'enabled' => '1']],
        ])->assertRedirect();

        WebsitePage::create([
            'title' => 'Claimed slug',
            'slug' => 'revision-slug-collision',
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->post(route('admin.website-pages.revisions.restore', [$page, $revision]))
            ->assertSessionHasErrors('revision');

        $this->assertDatabaseHas('website_pages', [
            'id' => $page->id,
            'title' => 'Renamed page',
            'slug' => 'renamed-page',
        ]);
        $this->assertSame(2, $page->fresh()->revisions()->count());
    }

    public function test_page_revision_cannot_be_restored_to_a_different_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = WebsitePage::create([
            'title' => 'First page',
            'slug' => 'first-revision-owner',
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $second = WebsitePage::create([
            'title' => 'Second page',
            'slug' => 'second-revision-owner',
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $revision = WebsitePageRevision::create([
            'website_page_id' => $first->id,
            'version' => 1,
            'snapshot' => [
                'title' => 'First page',
                'slug' => 'first-revision-owner',
                'template' => 'standard',
                'sections' => [],
                'status' => 'draft',
            ],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.website-pages.revisions.restore', [$second, $revision]))
            ->assertNotFound();

        $this->assertSame('Second page', $second->fresh()->title);
    }


    public function test_page_revision_migration_refuses_to_drop_saved_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $page = WebsitePage::create([
            'title' => 'Retained history page',
            'slug' => 'retained-history-page',
            'template' => 'standard',
            'sections' => [],
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        WebsitePageRevision::create([
            'website_page_id' => $page->id,
            'version' => 1,
            'snapshot' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'template' => $page->template,
                'sections' => [],
                'status' => $page->status,
            ],
            'created_by' => $admin->id,
        ]);

        $migration = require database_path('migrations/2026_11_18_120000_create_website_page_revisions_table.php');

        try {
            $migration->down();
            $this->fail('Migration rollback must not erase saved page revision history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('preserve custom page revision history', $exception->getMessage());
        }

        $this->assertDatabaseHas('website_page_revisions', [
            'website_page_id' => $page->id,
            'version' => 1,
        ]);
    }


}
