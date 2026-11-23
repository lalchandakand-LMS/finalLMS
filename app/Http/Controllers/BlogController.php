<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\MediaAsset;
use App\Models\PageView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:120', Rule::exists('blog_categories', 'slug')]]);
        $posts = BlogPost::with(['author', 'cover', 'categories', 'tags'])->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query->whereHas('categories', fn ($categories) => $categories->where('slug', $slug)))
            ->latest('published_at')->paginate(12)->withQueryString();
        $categories = BlogCategory::query()->withCount(['posts' => fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())])->orderBy('name')->get();
        return view('news.index', compact('posts', 'categories', 'filters'));
    }

    public function show(Request $request, BlogPost $post): View
    {
        abort_unless($post->status === 'published' && $post->published_at && $post->published_at->lte(now()), 404);
        PageView::record($request, 'post', 'blog_post', $post->id);
        $post->views_count = PageView::where('view_type', 'post')->where('viewable_id', $post->id)->count();
        $comments = $post->comments()->with('author')->latest()->paginate(10, ['*'], 'comments_page');
        $post->load(['author', 'cover', 'categories', 'tags']);
        return view('news.show', compact('post', 'comments'));
    }

    public function adminIndex(): View
    {
        $posts = BlogPost::with(['author', 'categories', 'tags'])->latest()->paginate(25);
        return view('admin.news.index', compact('posts'));
    }

    public function create(): View
    {
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(200)->get();
        return view('admin.news.form', ['post' => new BlogPost(), 'media' => $media]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['author_id'] = $request->user()->id;
        $validated['slug'] = $this->uniqueSlug(($validated['slug'] ?? '') ?: $validated['title']);
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? now()) : null;
        $categoryNames = $validated['categories_text'] ?? '';
        $tagNames = $validated['tags_text'] ?? '';
        unset($validated['categories_text'], $validated['tags_text']);
        DB::transaction(function () use ($validated, $categoryNames, $tagNames): void {
            $post = BlogPost::create($validated);
            $this->syncTaxonomy($post, $categoryNames, $tagNames);
        }, 3);
        return redirect()->route('admin.news.index')->with('success', 'Article saved.');
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.news.form', ['post' => $post->load(['categories', 'tags']), 'media' => MediaAsset::latest()->limit(200)->get()]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?: $validated['title'], $post->id);
        $validated['published_at'] = $validated['status'] === 'published' ? ($validated['published_at'] ?? $post->published_at ?? now()) : null;
        $categoryNames = $validated['categories_text'] ?? '';
        $tagNames = $validated['tags_text'] ?? '';
        unset($validated['categories_text'], $validated['tags_text']);
        DB::transaction(function () use ($post, $validated, $categoryNames, $tagNames): void {
            $post->update($validated);
            $this->syncTaxonomy($post, $categoryNames, $tagNames);
        }, 3);
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
            'cover_media_id' => ['nullable', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'categories_text' => ['nullable', 'string', 'max:1000'],
            'tags_text' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function syncTaxonomy(BlogPost $post, string $categoryInput, string $tagInput): void
    {
        $categoryIds = collect(explode(',', $categoryInput))->map(fn ($name) => trim($name))->filter()->unique(fn ($name) => Str::lower($name))->take(20)
            ->map(fn ($name) => BlogCategory::query()->firstOrCreate(['slug' => Str::slug($name) ?: Str::random(8)], ['name' => Str::limit($name, 100, ''), 'description' => null])->id)->all();
        $tagIds = collect(explode(',', $tagInput))->map(fn ($name) => trim($name))->filter()->unique(fn ($name) => Str::lower($name))->take(30)
            ->map(fn ($name) => BlogTag::query()->firstOrCreate(['slug' => Str::slug($name) ?: Str::random(8)], ['name' => Str::limit($name, 80, '')])->id)->all();
        $post->categories()->sync($categoryIds);
        $post->tags()->sync($tagIds);
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
