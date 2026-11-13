<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\WebsitePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function index(): View
    {
        $pages = WebsitePage::with('creator')->latest()->paginate(25);
        return view('admin.website-pages.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.website-pages.form', [
            'page' => new WebsitePage(['template' => 'standard', 'sections' => []]),
            'media' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug(!empty($data['slug']) ? $data['slug'] : $data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;
        WebsitePage::create($data);
        return redirect()->route('admin.website-pages.index')->with('success', 'Website page created.');
    }

    public function edit(WebsitePage $websitePage): View
    {
        return view('admin.website-pages.form', [
            'page' => $websitePage,
            'media' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(),
        ]);
    }

    public function update(Request $request, WebsitePage $websitePage): RedirectResponse
    {
        $data = $this->validated($request, $websitePage);
        $data['slug'] = $this->uniqueSlug(!empty($data['slug']) ? $data['slug'] : $data['title'], $websitePage->id);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? $websitePage->published_at ?? now()) : null;
        $websitePage->update($data);
        return redirect()->route('admin.website-pages.index')->with('success', 'Website page updated.');
    }

    public function destroy(WebsitePage $websitePage): RedirectResponse
    {
        $websitePage->delete();
        return back()->with('success', 'Website page deleted.');
    }

    public function show(string $slug): View
    {
        $page = WebsitePage::published()->where('slug', $slug)->firstOrFail();
        $imageIds = collect($page->sections ?? [])->pluck('image_id')->filter()->unique();
        $media = MediaAsset::query()->whereIn('id', $imageIds)->where('mime_type', 'like', 'image/%')->get()->keyBy('id');
        return view('website-pages.show', compact('page', 'media'));
    }

    private function validated(Request $request, ?WebsitePage $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('website_pages', 'slug')->ignore($page?->id)],
            'menu_label' => ['nullable', 'string', 'max:80'],
            'template' => ['required', Rule::in(['standard', 'wide', 'centered', 'split'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'show_in_menu' => ['nullable', 'boolean'],
            'menu_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'sections' => ['nullable', 'array', 'max:30'],
            'sections.*.type' => ['required', Rule::in(['text', 'image', 'hero', 'cta', 'feature'])],
            'sections.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:5000'],
            'sections.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'))],
            'sections.*.button_label' => ['nullable', 'string', 'max:60'],
            'sections.*.button_url' => ['nullable', 'string', 'max:500'],
            'sections.*.background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.layout' => ['nullable', Rule::in(['standard', 'reversed', 'centered'])],
            'sections.*.enabled' => ['nullable', 'boolean'],
        ]);

        $data['sections'] = collect($data['sections'] ?? [])->map(function (array $section): array {
            $url = trim($section['button_url'] ?? '');
            if ($url !== '' && !preg_match('~^(https?://|/(?!/)|#)~i', $url)) $url = '';
            return [
                'type' => $section['type'],
                'title' => trim($section['title'] ?? ''),
                'body' => trim($section['body'] ?? ''),
                'image_id' => $section['image_id'] ?? null,
                'button_label' => trim($section['button_label'] ?? ''),
                'button_url' => $url,
                'background_color' => $section['background_color'] ?? '#ffffff',
                'text_color' => $section['text_color'] ?? '#18212f',
                'layout' => $section['layout'] ?? 'standard',
                'enabled' => (bool) ($section['enabled'] ?? false),
            ];
        })->values()->all();

        $data['show_in_menu'] = $request->boolean('show_in_menu');
        $data['menu_order'] = (int) ($data['menu_order'] ?? 0);
        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $suffix = 2;
        while (WebsitePage::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
