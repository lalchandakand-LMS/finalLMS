<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteBuilderController extends Controller
{
    public function index(): View
    {
        $sections = json_decode((string) Setting::value('website_sections', '[]'), true);
        $sections = is_array($sections) ? $sections : [];
        $media = MediaAsset::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();

        return view('admin.website-builder.index', compact('sections', 'media'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['nullable', 'array', 'max:12'],
            'sections.*.title' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:3000'],
            'sections.*.image_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'sections.*.button_label' => ['nullable', 'string', 'max:60'],
            'sections.*.button_url' => ['nullable', 'url', 'max:500'],
            'sections.*.enabled' => ['nullable', 'boolean'],
        ]);

        $sections = collect($validated['sections'] ?? [])->map(function (array $section): array {
            return [
                'title' => trim($section['title'] ?? ''),
                'body' => trim($section['body'] ?? ''),
                'image_id' => $section['image_id'] ?? null,
                'button_label' => trim($section['button_label'] ?? ''),
                'button_url' => trim($section['button_url'] ?? ''),
                'enabled' => (bool) ($section['enabled'] ?? false),
            ];
        })->values()->all();

        Setting::setValue('website_sections', json_encode($sections, JSON_THROW_ON_ERROR));

        return redirect()->route('admin.website-builder.index')->with('success', 'Website sections saved.');
    }
}
