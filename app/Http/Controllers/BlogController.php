<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\CourseCategory;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::with(['author', 'cover'])->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->latest('published_at')->paginate(12);
        return view('news.index', compact('posts'));
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        $post->load(['author', 'cover']);
        return view('news.show', compact('post'));
    }

    public function adminIndex(): View
    {
        $posts = BlogPost::with('author')->latest()->paginate(25);
        return view('admin.news.index', compact('posts'));
    }

    public function create(): View
    {
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        return view('admin.news.form', ['post' => new BlogPost(), 'media' => $media, 'categories' => CourseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['author_id'] = $request->user()->id;
        $validated['slug'] = $this->uniqueSlug(($validated['slug'] ?? '') ?: $validated['title']);
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);
        $validated['category'] = CourseCategory::whereIn('id', $categoryIds)->orderBy('sort_order')->value('name');
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? now()) : null;
        $post = BlogPost::create($validated);
        $post->categories()->sync($categoryIds);
        return redirect()->route('admin.news.index')->with('success', 'Article saved.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.news.form', ['post' => $post->load('categories'), 'media' => MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(200)->get(), 'categories' => CourseCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title'], $post->id);
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);
        $validated['category'] = CourseCategory::whereIn('id', $categoryIds)->orderBy('sort_order')->value('name');
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? $post->published_at ?? now()) : null;
        $post->update($validated);
        $post->categories()->sync($categoryIds);
        return redirect()->route('admin.news.index')->with('success', 'Article updated.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        if ($post->courseSections()->exists()) {
            $post->update(['status' => 'archived', 'published_at' => null]);
            return back()->with('success', 'Content post was archived because it is attached to a course curriculum.');
        }

        $post->categories()->detach();
        $post->delete();
        return back()->with('success', 'Article deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('course_categories', 'id')->where('is_active', true)],
            'tags' => ['nullable', 'string', 'max:2000'],
            'video_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\/(?:www\.)?(?:youtube\.com|youtu\.be|youtube-nocookie\.com|vimeo\.com)(?:\/|$)/i'],
            'body' => ['required', 'string', 'max:100000'],
            'cover_media_id' => ['nullable', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'status' => ['required', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))->map(fn ($tag) => trim($tag))->filter()->unique()->take(30)->values()->all();
        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'article';
        $slug = $base;
        $suffix = 2;
        while (BlogPost::where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
