<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Setting;
use App\Services\Media\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaLibraryController extends Controller
{
    public function index(): View
    {
        $media = MediaAsset::with('uploader')->latest()->paginate(30);
        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,mp4,webm', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated['files'] as $file) {
            $webp = app(ImageOptimizer::class)->toWebp($file);
            if ($webp !== null) {
                $path = 'media/'.now()->format('Y/m').'/'.Str::uuid().'.webp';
                Storage::disk('public')->put($path, $webp);
                $mimeType = 'image/webp';
                $size = strlen($webp);
            } else {
                $path = $file->store('media/'.now()->format('Y/m'), 'public');
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
                $size = $file->getSize();
            }

            MediaAsset::create([
                'uploaded_by' => $request->user()->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $mimeType,
                'size' => $size,
                'alt_text' => $validated['alt_text'] ?? null,
            ]);
        }

        return back()->with('success', 'Media uploaded successfully. JPEG/PNG files are converted to optimized WebP when server support is available.');
    }

    public function destroy(MediaAsset $media): RedirectResponse
    {
        abort_if($media->blogCover()->exists(), 422, 'This file is used as a published or draft article cover.');
        $sections = json_decode((string) Setting::value('website_sections', '[]'), true);
        $draft = json_decode((string) Setting::value('homepage_draft', ''), true);
        $draftSections = is_array($draft) ? ($draft['sections'] ?? []) : [];
        $usedByWebsite = collect(array_merge(
            is_array($sections) ? $sections : [],
            is_array($draftSections) ? $draftSections : [],
        ))->contains(fn ($section) => is_array($section) && (int) ($section['image_id'] ?? 0) === $media->id);
        abort_if($usedByWebsite, 422, 'This file is used by a homepage section or saved draft.');

        $slides = json_decode((string) Setting::value('home_slides', '[]'), true);
        $draftSlides = is_array($draft) ? ($draft['home_slides'] ?? []) : [];
        $usedBySlider = collect(array_merge(
            is_array($slides) ? $slides : [],
            is_array($draftSlides) ? $draftSlides : [],
        ))->contains(fn ($slide) => is_array($slide)
            && ((int) ($slide['image_id'] ?? 0) === $media->id
                || (int) ($slide['mobile_image_id'] ?? 0) === $media->id));
        abort_if($usedBySlider, 422, 'This file is used by a homepage slider slide or saved draft.');

        $usedAsBranding = (int) Setting::value('site_logo_media_id', 0) === $media->id
            || (int) Setting::value('site_favicon_media_id', 0) === $media->id
            || (int) Setting::value('loading_screen_logo_media_id', 0) === $media->id;
        abort_if($usedAsBranding, 422, 'This file is currently used as the website logo, favicon or loading-screen logo.');
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        return back()->with('success', 'Media deleted.');
    }
}
