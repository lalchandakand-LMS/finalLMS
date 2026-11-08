<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteBuilderController extends Controller
{
    public function index(): View
    {
        $sections = json_decode((string) Setting::value('website_sections', '[]'), true);
        $sections = is_array($sections) ? $sections : [];
        $media = MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        $settings = Setting::query()->pluck('value', 'key')->toArray();
        $slides = json_decode((string) ($settings['home_slides'] ?? '[]'), true);
        $slides = is_array($slides) ? $slides : [];
        $footerLinks = json_decode((string) ($settings['footer_links'] ?? '[]'), true);
        $footerLinks = is_array($footerLinks) ? $footerLinks : [];
        $headerLinks = json_decode((string) ($settings['header_links'] ?? '[]'), true);
        $headerLinks = is_array($headerLinks) ? $headerLinks : [];
        $templates = self::homepageTemplates();
        $activeTemplate = (string) ($settings['homepage_template'] ?? 'academy-modern');
        if (!array_key_exists($activeTemplate, $templates)) {
            $activeTemplate = 'academy-modern';
        }

        return view('admin.website-builder.index', compact('sections', 'media', 'settings', 'slides', 'footerLinks', 'headerLinks', 'templates', 'activeTemplate'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'homepage_template' => ['sometimes', 'required', Rule::in(array_keys(self::homepageTemplates()))],
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_logo_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_favicon_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'site_primary_color' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_string($value) || strlen($value) !== 7 || $value[0] !== '#' || !ctype_xdigit(substr($value, 1))) {
                    $fail('The '.$attribute.' must be a six-digit hex color.');
                }
            }],
            'home_title' => ['nullable', 'string', 'max:255'],
            'home_subtitle' => ['nullable', 'string', 'max:500'],
            'home_featured_title' => ['nullable', 'string', 'max:255'],
            'home_slides' => ['nullable', 'array', 'max:3'],
            'home_slides.*.enabled' => ['nullable', 'boolean'],
            'home_slides.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'home_slides.*.title' => ['nullable', 'string', 'max:180'],
            'home_slides.*.subtitle' => ['nullable', 'string', 'max:500'],
            'home_slides.*.button_label' => ['nullable', 'string', 'max:60'],
            'home_slides.*.button_url' => ['nullable', 'string', 'max:500'],
            'footer_title' => ['nullable', 'string', 'max:120'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'footer_links' => ['nullable', 'array', 'max:5'],
            'footer_links.*.label' => ['nullable', 'string', 'max:60'],
            'footer_links.*.url' => ['nullable', 'string', 'max:500'],
            'header_links' => ['nullable', 'array', 'max:6'],
            'header_links.*.label' => ['nullable', 'string', 'max:60'],
            'header_links.*.url' => ['nullable', 'string', 'max:500'],
            'sections' => ['nullable', 'array', 'max:12'],
            'sections.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:3000'],
            'sections.*.image_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'sections.*.button_label' => ['nullable', 'string', 'max:60'],
            'sections.*.button_url' => ['nullable', 'string', 'max:500'],
            'sections.*.enabled' => ['nullable', 'boolean'],
        ]);

        $sections = collect($validated['sections'] ?? [])->map(function (array $section): array {
            return [
                'title' => trim($section['title'] ?? ''),
                'body' => trim($section['body'] ?? ''),
                'image_id' => $section['image_id'] ?? null,
                'button_label' => trim($section['button_label'] ?? ''),
                'button_url' => preg_match('~^(https?://|/(?!/)|#)~i', trim($section['button_url'] ?? ''))
                    ? trim($section['button_url'])
                    : '',
                'enabled' => (bool) ($section['enabled'] ?? false),
            ];
        })->values()->all();

        $slides = collect($validated['home_slides'] ?? [])->take(3)->map(function (array $slide): ?array {
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
        })->filter()->take(5)->values()->all();

        $headerLinks = collect($validated['header_links'] ?? [])->map(function (array $link): ?array {
            $label = trim($link['label'] ?? '');
            $url = trim($link['url'] ?? '');
            if ($label === '' || !preg_match('~^(https?://|/(?!/)|#)~i', $url)) {
                return null;
            }
            return ['label' => $label, 'url' => $url];
        })->filter()->take(6)->values()->all();

        foreach (['homepage_template', 'site_name', 'site_logo_media_id', 'site_favicon_media_id', 'site_primary_color', 'home_title', 'home_subtitle', 'home_featured_title', 'footer_title', 'footer_text'] as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::setValue($key, $validated[$key] ?? '');
            }
        }

        Setting::setValue('website_sections', json_encode($sections, JSON_THROW_ON_ERROR));
        Setting::setValue('home_slides', json_encode($slides, JSON_THROW_ON_ERROR));
        Setting::setValue('footer_links', json_encode($footerLinks, JSON_THROW_ON_ERROR));
        Setting::setValue('header_links', json_encode($headerLinks, JSON_THROW_ON_ERROR));

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
