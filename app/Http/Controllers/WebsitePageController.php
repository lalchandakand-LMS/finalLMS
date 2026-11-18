<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\AcademicLevel;
use App\Models\WebsitePage;
use App\Models\WebsitePageRevision;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['academic_level_id' => ['nullable', 'integer', 'exists:academic_levels,id']]);
        $pages = WebsitePage::with(['creator', 'academicLevel'])
            ->when($filters['academic_level_id'] ?? null, fn ($query, $levelId) => $query->where('academic_level_id', $levelId))
            ->latest()->paginate(25)->withQueryString();
        $academicLevels = AcademicLevel::query()->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.website-pages.index', compact('pages', 'academicLevels', 'filters'));
    }

    public function create(Request $request): View
    {
        return view('admin.website-pages.form', [
            'page' => new WebsitePage(['template' => 'standard', 'sections' => [], 'academic_level_id' => $request->integer('academic_level_id') ?: null]),
            'media' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(),
            'academicLevels' => AcademicLevel::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'revisions' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug(!empty($data['slug']) ? $data['slug'] : $data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;
        DB::transaction(function () use ($data, $request): void {
            $page = WebsitePage::create($data);
            $this->recordRevision($page, $request->user()->id);
        }, 3);

        return redirect()->route('admin.website-pages.index')->with('success', 'Website page created.');
    }

    public function edit(WebsitePage $websitePage): View
    {
        return view('admin.website-pages.form', [
            'page' => $websitePage,
            'media' => MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(),
            'academicLevels' => AcademicLevel::query()->where('is_active', true)->orWhere('id', $websitePage->academic_level_id)->orderBy('sort_order')->orderBy('name')->get(),
            'revisions' => $websitePage->revisions()->with('creator')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, WebsitePage $websitePage): RedirectResponse
    {
        $data = $this->validated($request, $websitePage);
        $data['slug'] = $this->uniqueSlug(!empty($data['slug']) ? $data['slug'] : $data['title'], $websitePage->id);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? $websitePage->published_at ?? now()) : null;
        DB::transaction(function () use ($websitePage, $data, $request): void {
            $page = WebsitePage::query()->whereKey($websitePage->id)->lockForUpdate()->firstOrFail();
            $page->update($data);
            $this->recordRevision($page, $request->user()->id);
        }, 3);

        return redirect()->route('admin.website-pages.index')->with('success', 'Website page updated.');
    }

    public function duplicate(Request $request, WebsitePage $websitePage): RedirectResponse
    {
        $copy = $websitePage->replicate(['slug', 'created_at', 'updated_at']);
        $copy->title = $websitePage->title.' (Copy)';
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->menu_label = null;
        $copy->show_in_menu = false;
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->created_by = $request->user()->id;
        DB::transaction(function () use ($copy, $request): void {
            $copy->save();
            $this->recordRevision($copy, $request->user()->id);
        }, 3);
        return redirect()->route('admin.website-pages.edit', $copy)->with('success', 'Page duplicated as a draft.');
    }

    public function restoreRevision(
        Request $request,
        WebsitePage $websitePage,
        WebsitePageRevision $revision,
    ): RedirectResponse {
        $restoredVersion = null;

        DB::transaction(function () use ($request, $websitePage, $revision, &$restoredVersion): void {
            $page = WebsitePage::query()->whereKey($websitePage->id)->lockForUpdate()->firstOrFail();
            $target = WebsitePageRevision::query()
                ->whereKey($revision->id)
                ->where('website_page_id', $page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = $target->snapshot;
            $required = ['title', 'slug', 'template', 'sections', 'status'];
            if (! is_array($snapshot)
                || collect($required)->contains(fn (string $key): bool => ! array_key_exists($key, $snapshot))
                || ! is_string($snapshot['title']) || ! is_string($snapshot['slug'])
                || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $snapshot['slug'])
                || ! in_array($snapshot['template'], ['standard', 'wide', 'centered', 'split'], true)
                || ! in_array($snapshot['status'], ['draft', 'published'], true)
                || ! is_array($snapshot['sections'])) {
                throw new \RuntimeException('The selected page revision is invalid and cannot be restored.');
            }

            $academicLevelId = $snapshot['academic_level_id'] ?? null;
            if ($academicLevelId !== null && ! AcademicLevel::query()->whereKey($academicLevelId)->exists()) {
                throw ValidationException::withMessages([
                    'revision' => 'This revision references an academic level that no longer exists.',
                ]);
            }

            $slugInUse = WebsitePage::query()
                ->where('slug', $snapshot['slug'])
                ->whereKeyNot($page->id)
                ->exists();
            if ($slugInUse) {
                throw ValidationException::withMessages([
                    'revision' => 'This revision cannot be restored because its URL slug is now used by another page.',
                ]);
            }

            $page->fill([
                'title' => $snapshot['title'],
                'slug' => $snapshot['slug'],
                'menu_label' => $snapshot['menu_label'] ?? null,
                'template' => $snapshot['template'],
                'sections' => $snapshot['sections'],
                'seo_title' => $snapshot['seo_title'] ?? null,
                'seo_description' => $snapshot['seo_description'] ?? null,
                'show_in_menu' => (bool) ($snapshot['show_in_menu'] ?? false),
                'menu_order' => (int) ($snapshot['menu_order'] ?? 0),
                'status' => $snapshot['status'],
                'published_at' => $snapshot['published_at'] ?? null,
                'academic_level_id' => $snapshot['academic_level_id'] ?? null,
            ]);
            $page->save();
            $this->recordRevision($page, $request->user()->id);
            $restoredVersion = $target->version;

            ActivityLog::query()->create([
                'user_id' => $request->user()->id,
                'event' => 'website_page.revision_restored',
                'description' => 'Restored website page '.$page->slug.' from revision '.$restoredVersion,
                'method' => $request->method(),
                'route' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ]);
        }, 3);

        return redirect()->route('admin.website-pages.edit', $websitePage)
            ->with('success', 'Page revision restored. The restored state was saved as a new revision.');
    }

    private function recordRevision(WebsitePage $page, int $userId): WebsitePageRevision
    {
        $nextVersion = (int) WebsitePageRevision::query()
            ->where('website_page_id', $page->id)
            ->max('version') + 1;

        return WebsitePageRevision::query()->create([
            'website_page_id' => $page->id,
            'version' => $nextVersion,
            'snapshot' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'menu_label' => $page->menu_label,
                'template' => $page->template,
                'sections' => $page->sections ?? [],
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'show_in_menu' => (bool) $page->show_in_menu,
                'menu_order' => (int) $page->menu_order,
                'status' => $page->status,
                'published_at' => $page->published_at?->toISOString(),
                'academic_level_id' => $page->academic_level_id,
            ],
            'created_by' => $userId,
        ]);
    }

    public function destroy(WebsitePage $websitePage): RedirectResponse
    {
        $websitePage->delete();
        return back()->with('success', 'Website page deleted.');
    }

    public function preview(WebsitePage $websitePage): View
    {
        return $this->renderPage($websitePage, true);
    }

    public function show(string $slug): View
    {
        $page = WebsitePage::published()->where('slug', $slug)->firstOrFail();
        return $this->renderPage($page, false);
    }

    private function renderPage(WebsitePage $page, bool $isPreview): View
    {
        $imageIds = collect($page->sections ?? [])->pluck('image_id')->filter()->unique();
        $media = MediaAsset::query()->whereIn('id', $imageIds)->where('mime_type', 'like', 'image/%')->get()->keyBy('id');
        return view('website-pages.show', compact('page', 'media', 'isPreview'));
    }

    private function validated(Request $request, ?WebsitePage $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'academic_level_id' => ['nullable', 'integer', Rule::exists('academic_levels', 'id')->where(fn ($query) => $query->where(function ($levels) use ($page): void {
                $levels->where('is_active', true);
                if ($page?->academic_level_id) {
                    $levels->orWhere('id', $page->academic_level_id);
                }
            }))],
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
