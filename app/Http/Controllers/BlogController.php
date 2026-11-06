<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $media = MediaAsset::latest()->limit(200)->get();
        return view('admin.news.form', ['post' => new BlogPost(), 'media' => $media]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['author_id'] = $request->user()->id;
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title']);
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? now()) : null;
        BlogPost::create($validated);
        return redirect()->route('admin.news.index')->with('success', 'Article saved.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.news.form', ['post' => $post, 'media' => MediaAsset::latest()->limit(200)->get()]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title'], $post->id);
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? $post->published_at ?? now()) : null;
        $post->update($validated);
        return redirect()->route('admin.news.index')->with('success', 'Article updated.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();
        return back()->with('success', 'Article deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'cover_media_id' => ['nullable', 'exists:media_assets,id'],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);
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
