<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\VisitorPopup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminVisitorPopupController extends Controller
{
    public function index(): View
    {
        $popups = VisitorPopup::with('image')->orderByDesc('priority')->latest()->paginate(20);
        return view('admin.visitor-popups.index', compact('popups'));
    }

    public function create(): View
    {
        $popup = new VisitorPopup(['popup_type' => 'offer', 'audience' => 'all', 'frequency' => 'once_per_session', 'delay_seconds' => 1]);
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        return view('admin.visitor-popups.form', compact('popup', 'media'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        VisitorPopup::create($data);
        return redirect()->route('admin.visitor-popups.index')->with('success', __('Popup campaign created.'));
    }

    public function edit(VisitorPopup $visitorPopup): View
    {
        $popup = $visitorPopup;
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        return view('admin.visitor-popups.form', compact('popup', 'media'));
    }

    public function update(Request $request, VisitorPopup $visitorPopup): RedirectResponse
    {
        $visitorPopup->update($this->validated($request));
        return redirect()->route('admin.visitor-popups.index')->with('success', __('Popup campaign updated.'));
    }

    public function destroy(VisitorPopup $visitorPopup): RedirectResponse
    {
        $visitorPopup->delete();
        return back()->with('success', __('Popup campaign deleted.'));
    }

    public function toggle(VisitorPopup $visitorPopup): RedirectResponse
    {
        $visitorPopup->update(['is_active' => !$visitorPopup->is_active]);
        return back()->with('success', __('Popup status updated.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'popup_type' => ['required', Rule::in(['offer', 'banner', 'post', 'ad', 'registration'])],
            'image_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', Rule::in(['all', 'guests', 'members'])],
            'frequency' => ['required', Rule::in(['every_page', 'once_per_session', 'once_per_day', 'once_per_browser'])],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $url = trim((string) ($data['button_url'] ?? ''));
        if ($url !== '' && !Str::startsWith($url, '/') && !preg_match('/^https:\/\//i', $url)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'button_url' => __('Use a site-relative path or a secure HTTPS URL.'),
            ]);
        }
        if (Str::startsWith($url, '//')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'button_url' => __('Protocol-relative URLs are not allowed.'),
            ]);
        }

        $data['button_url'] = $url !== '' ? $url : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['priority'] = (int) ($data['priority'] ?? 0);
        return $data;
    }
}
