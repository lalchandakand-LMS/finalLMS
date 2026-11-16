<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteMenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebsiteMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_public_menu(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.website-menu.index'))->assertForbidden();
    }

    public function test_admin_can_create_update_hide_and_delete_menu_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.website-menu.store'), [
            'label' => 'About us', 'url' => '/about', 'is_active' => '1',
        ])->assertRedirect(route('admin.website-menu.index'));

        $item = WebsiteMenuItem::query()->firstOrFail();
        $this->actingAs($admin)->put(route('admin.website-menu.update', $item), [
            'label' => 'Our story', 'url' => 'https://example.org/story', 'is_active' => '0',
        ])->assertRedirect(route('admin.website-menu.index'));
        $this->assertDatabaseHas('website_menu_items', ['id' => $item->id, 'label' => 'Our story', 'is_active' => false]);

        $this->actingAs($admin)->delete(route('admin.website-menu.destroy', $item))
            ->assertRedirect(route('admin.website-menu.index'));
        $this->assertDatabaseMissing('website_menu_items', ['id' => $item->id]);
    }

    public function test_menu_rejects_unsafe_urls_and_nested_submenus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->from(route('admin.website-menu.index'))
            ->post(route('admin.website-menu.store'), ['label' => 'Unsafe', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');

        $parent = WebsiteMenuItem::query()->create(['label' => 'Parent', 'url' => '/parent']);
        $child = WebsiteMenuItem::query()->create(['label' => 'Child', 'url' => '/child', 'parent_id' => $parent->id]);
        $this->actingAs($admin)->from(route('admin.website-menu.index'))
            ->post(route('admin.website-menu.store'), ['label' => 'Grandchild', 'url' => '/grandchild', 'parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_admin_can_reorder_menu_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = WebsiteMenuItem::query()->create(['label' => 'First', 'url' => '/first']);
        $second = WebsiteMenuItem::query()->create(['label' => 'Second', 'url' => '/second']);

        $this->actingAs($admin)->post(route('admin.website-menu.reorder'), [
            'items' => [$second->id, $first->id],
        ])->assertRedirect(route('admin.website-menu.index'));

        $this->assertDatabaseHas('website_menu_items', ['id' => $second->id, 'sort_order' => 10]);
        $this->assertDatabaseHas('website_menu_items', ['id' => $first->id, 'sort_order' => 20]);
    }
}
