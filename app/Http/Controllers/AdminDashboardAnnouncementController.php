<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\DashboardAnnouncement;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDashboardAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = DashboardAnnouncement::with(['course', 'post', 'image'])
            ->orderByDesc('priority')->latest()->paginate(20);

        return view('admin.dashboard-announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        $announcement = new DashboardAnnouncement([
            'content_type' => 'custom', 'audience' => 'all', 'priority' => 10,
        ]);

        return $this->form($announcement);
    }

    public function edit(DashboardAnnouncement $dashboardAnnouncement): View
    {
        return $this->form($dashboardAnnouncement);
    }

    private function form(DashboardAnnouncement $announcement): View
    {
        $courses = Course::where('status', 'published')->orderBy('title')->get();
        $posts = BlogPost::where('status', 'published')->orderByDesc('published_at')->get();
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(300)->get();

        return view('admin.dashboard-announcements.form', compact('announcement', 'courses', 'posts', 'media'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        DashboardAnnouncement::create($data);

        return redirect()->route('admin.dashboard-announcements.index')->with('success', __('Dashboard announcement created.'));
    }

    public function update(Request $request, DashboardAnnouncement $dashboardAnnouncement): RedirectResponse
    {
        $dashboardAnnouncement->update($this->validated($request));

        return redirect()->route('admin.dashboard-announcements.index')->with('success', __('Dashboard announcement updated.'));
    }

    public function toggle(DashboardAnnouncement $dashboardAnnouncement): RedirectResponse
    {
        $dashboardAnnouncement->update(['is_active' => !$dashboardAnnouncement->is_active]);

        return back()->with('success', __('Announcement status updated.'));
    }

    public function destroy(DashboardAnnouncement $dashboardAnnouncement): RedirectResponse
    {
        $dashboardAnnouncement->delete();

        return back()->with('success', __('Dashboard announcement deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'content_type' => ['required', Rule::in(['custom', 'course', 'post'])],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')->where(fn ($q) => $q->where('status', 'published'))],
            'blog_post_id' => ['nullable', 'integer', Rule::exists('blog_posts', 'id')->where(fn ($q) => $q->where('status', 'published'))],
            'image_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($q) => $q->where('mime_type', 'like', 'image/%'))],
            'button_label' => ['nullable', 'string', 'max:60'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', Rule::in(['all', 'student', 'teacher', 'guardian'])],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if ($data['content_type'] === 'course' && empty($data['course_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['course_id' => __('Choose a published course.')]);
        }
        if ($data['content_type'] === 'post' && empty($data['blog_post_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['blog_post_id' => __('Choose a published post.')]);
        }

        if ($data['content_type'] === 'course') {
            $course = Course::findOrFail($data['course_id']);
            $data['title'] = $data['title'] ?: $course->title;
            $data['button_url'] = route('courses.show', $course);
            $data['button_label'] = $data['button_label'] ?: __('View course');
        } elseif ($data['content_type'] === 'post') {
            $post = BlogPost::findOrFail($data['blog_post_id']);
            $data['button_url'] = route('news.show', $post->slug);
            $data['button_label'] = $data['button_label'] ?: __('Read post');
        } else {
            $url = trim((string) ($data['button_url'] ?? ''));
            if ($url !== '' && !Str::startsWith($url, '/') && !preg_match('/^https:\/\//i', $url)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['button_url' => __('Use a site-relative path or secure HTTPS URL.')]);
            }
            if (Str::startsWith($url, '//')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['button_url' => __('Protocol-relative URLs are not allowed.')]);
            }
            $data['button_url'] = $url !== '' ? $url : null;
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['priority'] = (int) ($data['priority'] ?? 0);

        return $data;
    }
}
