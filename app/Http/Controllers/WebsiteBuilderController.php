<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsiteBuilderController extends Controller
{
    public function index(): View
    {
        $sections = json_decode((string) Setting::value('website_sections', '[]'), true);
        $sections = is_array($sections) ? $sections : [];
        $media = MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        $settings = Setting::query()->pluck('value', 'key')->toArray();
        $draft = json_decode((string) Setting::value('homepage_draft', ''), true);
        $draft = is_array($draft) ? $draft : null;
        if ($draft !== null) {
            $settings = array_merge($settings, $draft);
            $sections = is_array($draft['sections'] ?? null) ? $draft['sections'] : $sections;
        }
        $slides = is_array($draft['home_slides'] ?? null) ? $draft['home_slides'] : json_decode((string) ($settings['home_slides'] ?? '[]'), true);
        $slides = is_array($slides) ? $slides : [];
        $footerLinks = is_array($draft['footer_links'] ?? null) ? $draft['footer_links'] : json_decode((string) ($settings['footer_links'] ?? '[]'), true);
        $footerLinks = is_array($footerLinks) ? $footerLinks : [];
        $headerLinks = is_array($draft['header_links'] ?? null) ? $draft['header_links'] : json_decode((string) ($settings['header_links'] ?? '[]'), true);
        $headerLinks = is_array($headerLinks) ? $headerLinks : [];
        $revisions = json_decode((string) Setting::value('homepage_revisions', '[]'), true);
        $revisions = is_array($revisions) ? $revisions : [];
        $customBlocks = json_decode((string) Setting::value('homepage_custom_blocks', '[]'), true);
        $customBlocks = is_array($customBlocks) ? $customBlocks : [];
        $templates = self::homepageTemplates();
        $activeTemplate = (string) ($settings['homepage_template'] ?? 'academy-modern');
        if (!array_key_exists($activeTemplate, $templates)) {
            $activeTemplate = 'academy-modern';
        }

        return view('admin.website-builder.index', compact('sections', 'media', 'settings', 'slides', 'footerLinks', 'headerLinks', 'templates', 'activeTemplate', 'draft', 'revisions', 'customBlocks'));
    }

    /**
     * Store a validated-size draft without changing the public homepage.
     */
    public function saveDraft(Request $request): RedirectResponse
    {
        $allowed = [
            'homepage_template', 'site_name', 'site_logo_media_id', 'site_favicon_media_id',
            'site_primary_color', 'site_background_color', 'site_background_style', 'site_font_family', 'site_heading_scale', 'site_body_scale', 'home_title', 'home_subtitle', 'home_featured_title',
            'home_slides', 'home_slider_autoplay', 'home_slider_interval', 'home_slider_arrows', 'home_slider_dots', 'home_slider_pause_hover', 'home_slider_height', 'home_slider_mobile_height', 'home_slider_overlay', 'footer_title', 'footer_text', 'footer_links', 'header_links', 'sections',
        ];
        $draft = $request->only($allowed);
        abort_if(strlen(json_encode($draft, JSON_THROW_ON_ERROR)) > 65536, 422, 'Homepage draft is too large.');

        validator($draft, [
            'homepage_template' => ['nullable', 'string', Rule::in(array_keys(self::homepageTemplates()))],
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_logo_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_favicon_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'site_background_style' => ['nullable', Rule::in(['solid', 'soft-gradient', 'warm-gradient'])],
            'site_font_family' => ['nullable', Rule::in(['system', 'inter', 'nunito', 'poppins', 'georgia'])],
            'site_heading_scale' => ['nullable', Rule::in(['0.9', '1', '1.1', '1.2'])],
            'site_body_scale' => ['nullable', Rule::in(['0.9', '1', '1.05', '1.1'])],
            'site_primary_color' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) || strlen($value) !== 7 || $value[0] !== '#' || !ctype_xdigit(substr($value, 1))) {
                    $fail('The '.$attribute.' must be a six-digit hex color.');
                }
            }],
            'home_title' => ['nullable', 'string', 'max:255'],
            'home_subtitle' => ['nullable', 'string', 'max:500'],
            'home_featured_title' => ['nullable', 'string', 'max:255'],
            'home_slides' => ['nullable', 'array', 'max:8'],
            'home_slider_autoplay' => ['nullable', 'boolean'],
            'home_slider_interval' => ['nullable', 'integer', 'min:3000', 'max:12000'],
            'home_slider_arrows' => ['nullable', 'boolean'],
            'home_slider_dots' => ['nullable', 'boolean'],
            'home_slider_pause_hover' => ['nullable', 'boolean'],
            'home_slider_height' => ['nullable', 'integer', 'min:320', 'max:720'],
            'home_slider_mobile_height' => ['nullable', 'integer', 'min:220', 'max:520'],
            'home_slider_overlay' => ['nullable', 'integer', 'min:20', 'max:90'],
            'home_slides.*.enabled' => ['nullable', 'boolean'],
            'home_slides.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'home_slides.*.title' => ['nullable', 'string', 'max:180'],
            'home_slides.*.subtitle' => ['nullable', 'string', 'max:500'],
            'home_slides.*.button_label' => ['nullable', 'string', 'max:60'],
            'home_slides.*.button_url' => ['nullable', 'string', 'max:500'],
            'footer_title' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'footer_links' => ['nullable', 'array', 'max:20'],
            'footer_links.*.label' => ['nullable', 'string', 'max:60'],
            'footer_links.*.url' => ['nullable', 'string', 'max:500'],
            'header_links' => ['nullable', 'array', 'max:20'],
            'header_links.*.label' => ['nullable', 'string', 'max:60'],
            'header_links.*.url' => ['nullable', 'string', 'max:500'],
            'sections' => ['nullable', 'array', 'max:12'],
            'sections.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:3000'],
            'sections.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'sections.*.button_label' => ['nullable', 'string', 'max:60'],
            'sections.*.button_url' => ['nullable', 'string', 'max:500'],
            'sections.*.background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.spacing' => ['nullable', Rule::in(['compact', 'normal', 'airy'])],
            'sections.*.layout' => ['nullable', Rule::in(['standard', 'reversed', 'centered'])],
            'sections.*.enabled' => ['nullable', 'boolean'],
        ])->validate();

        Setting::setValue('homepage_draft', json_encode($draft, JSON_THROW_ON_ERROR));
        Setting::setValue('homepage_draft_updated_at', now()->toIso8601String());
        Setting::setValue('homepage_draft_updated_by', (string) $request->user()->id);

        return redirect()->route('admin.website-builder.index')->with('success', 'Draft saved. The public homepage has not changed.');
    }


    /**
     * Save a reusable, admin-authored homepage section block.
     */
    public function saveCustomBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'spacing' => ['nullable', Rule::in(['compact', 'normal', 'airy'])],
            'layout' => ['nullable', Rule::in(['standard', 'reversed', 'centered'])],
        ]);

        $url = trim($validated['button_url'] ?? '');
        if ($url !== '' && !preg_match('~^(https?://|/(?!/)|#)~i', $url)) {
            $url = '';
        }

        $blocks = json_decode((string) Setting::value('homepage_custom_blocks', '[]'), true);
        $blocks = is_array($blocks) ? $blocks : [];
        $blocks[] = [
            'id' => (string) Str::uuid(),
            'name' => trim($validated['name']),
            'title' => trim($validated['title']),
            'body' => trim($validated['body'] ?? ''),
            'button_label' => trim($validated['button_label'] ?? ''),
            'button_url' => $url,
            'background_color' => $validated['background_color'] ?? '#ffffff',
            'text_color' => $validated['text_color'] ?? '#18212f',
            'spacing' => $validated['spacing'] ?? 'normal',
            'layout' => $validated['layout'] ?? 'standard',
        ];

        Setting::setValue('homepage_custom_blocks', json_encode(array_slice($blocks, -30), JSON_THROW_ON_ERROR));

        return redirect()->route('admin.website-builder.index')->with('success', 'Reusable homepage block saved to your custom library.');
    }

    public function deleteCustomBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate(['block_id' => ['required', 'string', 'max:80']]);
        $blocks = json_decode((string) Setting::value('homepage_custom_blocks', '[]'), true);
        $blocks = is_array($blocks) ? $blocks : [];
        $blocks = array_values(array_filter($blocks, fn ($block) => ($block['id'] ?? null) !== $validated['block_id']));
        Setting::setValue('homepage_custom_blocks', json_encode($blocks, JSON_THROW_ON_ERROR));

        return redirect()->route('admin.website-builder.index')->with('success', 'Custom block deleted.');
    }

    public function previewDraft(): View
    {
        $draft = json_decode((string) Setting::value('homepage_draft', ''), true);
        abort_unless(is_array($draft), 404, 'No saved homepage draft is available.');

        $imageIds = collect($draft['home_slides'] ?? [])->pluck('image_id')
            ->merge(collect($draft['sections'] ?? [])->pluck('image_id'))
            ->filter()->unique()->values();
        $media = MediaAsset::query()->whereIn('id', $imageIds)->where('mime_type', 'like', 'image/%')->get()->keyBy('id');

        $isSafeUrl = fn ($url): bool => is_string($url) && (bool) preg_match('~^(https?://|/(?!/)|#)~i', trim($url));
        $draft['header_links'] = collect($draft['header_links'] ?? [])->filter(fn ($link) => is_array($link) && $isSafeUrl($link['url'] ?? null))->values()->all();
        $draft['footer_links'] = collect($draft['footer_links'] ?? [])->filter(fn ($link) => is_array($link) && $isSafeUrl($link['url'] ?? null))->values()->all();
        $draft['home_slides'] = collect($draft['home_slides'] ?? [])->map(function ($slide) use ($isSafeUrl) {
            if (is_array($slide) && !$isSafeUrl($slide['button_url'] ?? '')) {
                $slide['button_url'] = '';
            }
            return $slide;
        })->all();
        $draft['sections'] = collect($draft['sections'] ?? [])->map(function ($section) use ($isSafeUrl) {
            if (is_array($section) && !$isSafeUrl($section['button_url'] ?? '')) {
                $section['button_url'] = '';
            }
            return $section;
        })->all();

        return view('admin.website-builder.preview', compact('draft', 'media'));
    }

    public function publishDraft(Request $request): RedirectResponse
    {
        $draft = json_decode((string) Setting::value('homepage_draft', ''), true);
        abort_unless(is_array($draft), 404, 'No saved homepage draft is available.');

        $request->replace($draft);

        return $this->update($request);
    }

    public function rollback(Request $request): RedirectResponse
    {
        $validated = $request->validate(['revision_id' => ['required', 'string', 'max:80']]);
        $revisions = json_decode((string) Setting::value('homepage_revisions', '[]'), true);
        $revisions = is_array($revisions) ? $revisions : [];
        $targetIndex = collect($revisions)->search(fn ($revision) => ($revision['id'] ?? null) === $validated['revision_id']);
        abort_if($targetIndex === false, 404, 'Homepage revision not found.');

        $keys = self::homepageSettingKeys();
        $current = [];
        foreach ($keys as $key) {
            $current[$key] = Setting::value($key, '');
        }
        $revisions[] = [
            'id' => (string) Str::uuid(),
            'created_at' => now()->toIso8601String(),
            'by' => $request->user()->id,
            'settings' => $current,
            'label' => 'Before rollback',
        ];

        $target = $revisions[$targetIndex]['settings'] ?? [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $target)) {
                Setting::setValue($key, $target[$key] ?? '');
            }
        }
        Setting::setValue('homepage_revisions', json_encode(array_slice($revisions, -20), JSON_THROW_ON_ERROR));
        Setting::setValue('homepage_draft', '');
        Setting::setValue('homepage_draft_updated_at', '');
        Setting::setValue('homepage_draft_updated_by', '');

        return redirect()->route('admin.website-builder.index')->with('success', 'Homepage restored from the selected revision.');
    }

    /** @return list<string> */
    private static function homepageSettingKeys(): array
    {
        return [
            'homepage_template', 'site_name', 'site_logo_media_id', 'site_favicon_media_id',
            'site_primary_color', 'site_background_color', 'site_background_style', 'site_font_family', 'site_heading_scale', 'site_body_scale', 'home_title', 'home_subtitle', 'home_featured_title',
            'home_slides', 'home_slider_autoplay', 'home_slider_interval', 'home_slider_arrows', 'home_slider_dots', 'home_slider_pause_hover', 'home_slider_height', 'home_slider_mobile_height', 'home_slider_overlay', 'footer_title', 'footer_text', 'footer_links', 'header_links', 'website_sections',
        ];
    }

    private function recordHomepageRevision(): void
    {
        $keys = self::homepageSettingKeys();
        $snapshot = [];
        foreach ($keys as $key) {
            $snapshot[$key] = Setting::value($key, '');
        }
        $revisions = json_decode((string) Setting::value('homepage_revisions', '[]'), true);
        $revisions = is_array($revisions) ? $revisions : [];
        $revisions[] = [
            'id' => (string) Str::uuid(),
            'created_at' => now()->toIso8601String(),
            'by' => auth()->id(),
            'settings' => $snapshot,
            'label' => 'Published homepage',
        ];
        Setting::setValue('homepage_revisions', json_encode(array_slice($revisions, -20), JSON_THROW_ON_ERROR));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'homepage_template' => ['sometimes', 'required', Rule::in(array_keys(self::homepageTemplates()))],
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_logo_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_favicon_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'site_background_style' => ['nullable', Rule::in(['solid', 'soft-gradient', 'warm-gradient'])],
            'site_font_family' => ['nullable', Rule::in(['system', 'inter', 'nunito', 'poppins', 'georgia'])],
            'site_heading_scale' => ['nullable', Rule::in(['0.9', '1', '1.1', '1.2'])],
            'site_body_scale' => ['nullable', Rule::in(['0.9', '1', '1.05', '1.1'])],
            'site_primary_color' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) || strlen($value) !== 7 || $value[0] !== '#' || !ctype_xdigit(substr($value, 1))) {
                    $fail('The '.$attribute.' must be a six-digit hex color.');
                }
            }],
            'home_title' => ['nullable', 'string', 'max:255'],
            'home_subtitle' => ['nullable', 'string', 'max:500'],
            'home_featured_title' => ['nullable', 'string', 'max:255'],
            'home_slides' => ['nullable', 'array', 'max:8'],
            'home_slider_autoplay' => ['nullable', 'boolean'],
            'home_slider_interval' => ['nullable', 'integer', 'min:3000', 'max:12000'],
            'home_slider_arrows' => ['nullable', 'boolean'],
            'home_slider_dots' => ['nullable', 'boolean'],
            'home_slider_pause_hover' => ['nullable', 'boolean'],
            'home_slider_height' => ['nullable', 'integer', 'min:320', 'max:720'],
            'home_slider_mobile_height' => ['nullable', 'integer', 'min:220', 'max:520'],
            'home_slider_overlay' => ['nullable', 'integer', 'min:20', 'max:90'],
            'home_slides.*.enabled' => ['nullable', 'boolean'],
            'home_slides.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'home_slides.*.title' => ['nullable', 'string', 'max:180'],
            'home_slides.*.subtitle' => ['nullable', 'string', 'max:500'],
            'home_slides.*.button_label' => ['nullable', 'string', 'max:60'],
            'home_slides.*.button_url' => ['nullable', 'string', 'max:500'],
            'footer_title' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'footer_links' => ['nullable', 'array', 'max:20'],
            'footer_links.*.label' => ['nullable', 'string', 'max:60'],
            'footer_links.*.url' => ['nullable', 'string', 'max:500'],
            'header_links' => ['nullable', 'array', 'max:20'],
            'header_links.*.label' => ['nullable', 'string', 'max:60'],
            'header_links.*.url' => ['nullable', 'string', 'max:500'],
            'sections' => ['nullable', 'array', 'max:20'],
            'sections.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:3000'],
            'sections.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'sections.*.button_label' => ['nullable', 'string', 'max:60'],
            'sections.*.button_url' => ['nullable', 'string', 'max:500'],
            'sections.*.background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.text_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.spacing' => ['nullable', Rule::in(['compact', 'normal', 'airy'])],
            'sections.*.layout' => ['nullable', Rule::in(['standard', 'reversed', 'centered'])],
            'sections.*.enabled' => ['nullable', 'boolean'],
        ]);

        $this->recordHomepageRevision();

        $sections = collect($validated['sections'] ?? [])->map(function (array $section): array {
            return [
                'title' => trim($section['title'] ?? ''),
                'body' => trim($section['body'] ?? ''),
                'image_id' => $section['image_id'] ?? null,
                'button_label' => trim($section['button_label'] ?? ''),
                'button_url' => preg_match('~^(https?://|/(?!/)|#)~i', trim($section['button_url'] ?? ''))
                    ? trim($section['button_url'])
                    : '',
                'background_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $section['background_color'] ?? '') ? $section['background_color'] : '#ffffff',
                'text_color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $section['text_color'] ?? '') ? $section['text_color'] : '#18212f',
                'spacing' => in_array($section['spacing'] ?? 'normal', ['compact', 'normal', 'airy'], true) ? ($section['spacing'] ?? 'normal') : 'normal',
                'layout' => in_array($section['layout'] ?? 'standard', ['standard', 'reversed', 'centered'], true) ? ($section['layout'] ?? 'standard') : 'standard',
                'enabled' => (bool) ($section['enabled'] ?? false),
            ];
        })->values()->all();

        $slides = collect($validated['home_slides'] ?? [])->take(8)->map(function (array $slide): ?array {
            $image = !empty($slide['image_id'])
                ? MediaAsset::query()->whereKey($slide['image_id'])->where('mime_type', 'like', 'image/%')->first()
                : null;
            if (empty($slide['enabled']) || !$image) {
                return null;
            }

            $buttonUrl = trim($slide['button_url'] ?? '');
            if ($buttonUrl !== '' && !preg_match('~^(https?://|/(?!/)|#)~i', $buttonUrl)) {
                $buttonUrl = '';
            }

            return [
                'image_id' => $image->id,
                'title' => trim($slide['title'] ?? ''),
                'subtitle' => trim($slide['subtitle'] ?? ''),
                'button_label' => trim($slide['button_label'] ?? ''),
                'button_url' => $buttonUrl,
                'enabled' => true,
            ];
        })->filter()->values()->all();

        $footerLinks = collect($validated['footer_links'] ?? [])->map(function (array $link): ?array {
            $label = trim($link['label'] ?? '');
            $url = trim($link['url'] ?? '');
            if ($label === '' || !preg_match('~^(https?://|/(?!/)|#)~i', $url)) {
                return null;
            }
            return ['label' => $label, 'url' => $url];
        })->filter()->take(20)->values()->all();

        $headerLinks = collect($validated['header_links'] ?? [])->map(function (array $link): ?array {
            $label = trim($link['label'] ?? '');
            $url = trim($link['url'] ?? '');
            if ($label === '' || !preg_match('~^(https?://|/(?!/)|#)~i', $url)) {
                return null;
            }
            return ['label' => $label, 'url' => $url];
        })->filter()->take(20)->values()->all();

        foreach (['homepage_template', 'site_name', 'site_logo_media_id', 'site_favicon_media_id', 'site_primary_color', 'site_background_color', 'site_background_style', 'site_font_family', 'site_heading_scale', 'site_body_scale', 'home_title', 'home_subtitle', 'home_featured_title', 'home_slider_autoplay', 'home_slider_interval', 'home_slider_arrows', 'home_slider_dots', 'home_slider_pause_hover', 'home_slider_height', 'home_slider_mobile_height', 'home_slider_overlay', 'footer_title', 'footer_text'] as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::setValue($key, $validated[$key] ?? '');
            }
        }

        if (array_key_exists('sections', $validated)) {
            Setting::setValue('website_sections', json_encode($sections, JSON_THROW_ON_ERROR));
        }
        if (array_key_exists('home_slides', $validated)) {
            Setting::setValue('home_slides', json_encode($slides, JSON_THROW_ON_ERROR));
        }
        if (array_key_exists('footer_links', $validated)) {
            Setting::setValue('footer_links', json_encode($footerLinks, JSON_THROW_ON_ERROR));
        }
        if (array_key_exists('header_links', $validated)) {
            Setting::setValue('header_links', json_encode($headerLinks, JSON_THROW_ON_ERROR));
        }

        Setting::setValue('homepage_draft', '');
        Setting::setValue('homepage_draft_updated_at', '');
        Setting::setValue('homepage_draft_updated_by', '');

        return redirect()->route('admin.website-builder.index')->with('success', 'Website identity, homepage slider and layout saved.');
    }

    /** @return array<string, array{label: string, description: string}> */
    public static function homepageTemplates(): array
    {
        return [
            'academy-modern' => ['label' => 'Academy Modern', 'description' => 'Balanced academy layout with a clear learning-first hero.'],
            'bold-academy' => ['label' => 'Bold Academy', 'description' => 'Large typography and high-contrast calls to action.'],
            'minimal-learning' => ['label' => 'Minimal Learning', 'description' => 'Quiet whitespace and reduced visual decoration.'],
            'editorial' => ['label' => 'Editorial', 'description' => 'Typography-led layout for articles and learning stories.'],
            'gradient-studio' => ['label' => 'Gradient Studio', 'description' => 'Color-rich gradient surfaces and rounded cards.'],
            'corporate-training' => ['label' => 'Corporate Training', 'description' => 'Structured, professional training presentation.'],
            'playful-classroom' => ['label' => 'Playful Classroom', 'description' => 'Friendly shapes and approachable course cards.'],
            'midnight-campus' => ['label' => 'Midnight Campus', 'description' => 'Dark campus-inspired visual treatment.'],
            'split-screen' => ['label' => 'Split Screen', 'description' => 'Strong two-column hero and clear content separation.'],
            'classic-institute' => ['label' => 'Classic Institute', 'description' => 'Traditional academic styling and restrained accents.'],
            'glass-campus' => ['label' => 'Glass Campus', 'description' => 'Soft translucent panels and layered surfaces.'],
            'vibrant-skills' => ['label' => 'Vibrant Skills', 'description' => 'Energetic accent colors for skills and bootcamps.'],
            'focus-learning' => ['label' => 'Focus Learning', 'description' => 'Simple focused layout emphasizing course discovery.'],
            'elegant-academy' => ['label' => 'Elegant Academy', 'description' => 'Refined typography and subtle borders.'],
            'community-campus' => ['label' => 'Community Campus', 'description' => 'Warm community-oriented learning presentation.'],
        ];
    }
}
