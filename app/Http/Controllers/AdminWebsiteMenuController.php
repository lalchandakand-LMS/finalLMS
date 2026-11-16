<?php

namespace App\Http\Controllers;

use App\Models\WebsiteMenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class AdminWebsiteMenuController extends Controller
{
    public function index(): View
    {
        $items = WebsiteMenuItem::query()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $parents = WebsiteMenuItem::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.website-menu.index', compact('items', 'parents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertParentIsTopLevel($data['parent_id'] ?? null);
        WebsiteMenuItem::create($data);

        return redirect()->route('admin.website-menu.index')->with('success', 'Menu item created.');
    }

    public function update(Request $request, WebsiteMenuItem $websiteMenuItem): RedirectResponse
    {
        $data = $this->validated($request);
        if (($data['parent_id'] ?? null) == $websiteMenuItem->id) {
            throw ValidationException::withMessages(['parent_id' => 'A menu item cannot be its own parent.']);
        }
        $this->assertParentIsTopLevel($data['parent_id'] ?? null);
        $websiteMenuItem->update($data);

        return redirect()->route('admin.website-menu.index')->with('success', 'Menu item updated.');
    }

    public function destroy(WebsiteMenuItem $websiteMenuItem): RedirectResponse
    {
        DB::transaction(function () use ($websiteMenuItem): void {
            // Promote children to top-level instead of deleting links unexpectedly.
            $websiteMenuItem->children()->update(['parent_id' => null]);
            $websiteMenuItem->delete();
        });

        return redirect()->route('admin.website-menu.index')->with('success', 'Menu item deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['required', 'integer', 'distinct', Rule::exists('website_menu_items', 'id')],
        ]);

        $ids = array_map('intval', $data['items']);
        $existing = WebsiteMenuItem::query()->whereIn('id', $ids)->count();
        if ($existing !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'The menu order contains invalid items.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                WebsiteMenuItem::query()->whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        return redirect()->route('admin.website-menu.index')->with('success', 'Menu order saved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                $url = trim((string) $value);
                $validPath = str_starts_with($url, '/') && !str_starts_with($url, '//')
                    && !preg_match('/[\r\n]/', $url);
                $parts = parse_url($url);
                $validHttps = is_array($parts) && ($parts['scheme'] ?? null) === 'https'
                    && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
                if (!$validPath && !$validHttps) {
                    $fail('Use a site-relative path or a complete HTTPS URL.');
                }
            }],
            'parent_id' => ['nullable', 'integer', Rule::exists('website_menu_items', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'target_blank' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['target_blank'] = $request->boolean('target_blank');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function assertParentIsTopLevel(?int $parentId): void
    {
        if ($parentId && WebsiteMenuItem::query()->whereKey($parentId)->whereNotNull('parent_id')->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Submenus can only be one level deep.']);
        }
    }
}
