<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            $path = $file->store('media/'.now()->format('Y/m'), 'public');
            MediaAsset::create([
                'uploaded_by' => $request->user()->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'alt_text' => $validated['alt_text'] ?? null,
            ]);
        }

        return back()->with('success', 'Media uploaded successfully.');
    }

    public function destroy(MediaAsset $media): RedirectResponse
    {
        abort_if($media->blogCover()->exists(), 422, 'This file is used as a published or draft article cover.');
        $sections = json_decode((string) Setting::value('website_sections', '[]'), true);
        $usedByWebsite = collect(is_array($sections) ? $sections : [])->contains(
            fn ($section) => is_array($section) && (int) ($section['image_id'] ?? 0) === $media->id
        );
        abort_if($usedByWebsite, 422, 'This file is used by a homepage section.');
        $slides = json_decode((string) Setting::value('home_slides', '[]'), true);
        $usedBySlider = collect(is_array($slides) ? $slides : [])->contains(
            fn ($slide) => is_array($slide) && (int) ($slide['image_id'] ?? 0) === $media->id
        );
        abort_if($usedBySlider, 422, 'This file is used by a homepage slider slide.');
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        return back()->with('success', 'Media deleted.');
    }
}
