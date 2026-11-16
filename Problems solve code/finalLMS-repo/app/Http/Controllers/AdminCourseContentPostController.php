<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCourseContentPostController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'category' => ['nullable', 'integer', 'min:1'],
        ]);

        $posts = CourseContentPost::query()->with(['author', 'thumbnail', 'categories'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(fn ($builder) => $builder
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('short_description', 'like', '%'.$term.'%'));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, int $category) => $query->whereHas('categories', fn ($builder) => $builder->whereKey($category)))
            ->latest()->paginate(20)->withQueryString();

        $categories = CourseCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.course-content-posts.index', compact('posts', 'categories'));
    }

    public function create(): View
    {
        return $this->form(new CourseContentPost(['status' => 'draft', 'tags' => []]));
    }

    public function edit(CourseContentPost $courseContentPost): View
    {
        return $this->form($courseContentPost);
    }

    private function form(CourseContentPost $post): View
    {
        $categories = CourseCategory::orderBy('sort_order')->orderBy('name')->get();
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(300)->get();

        return view('admin.course-content-posts.form', compact('post', 'categories', 'media'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;

        $post = DB::transaction(function () use ($data, $categoryIds): CourseContentPost {
            $post = CourseContentPost::create($data);
            $post->categories()->sync($categoryIds);

            return $post;
        });

        return redirect()->route('admin.course-content-posts.index')->with('success', __('Course content post created.'));
    }

    public function update(Request $request, CourseContentPost $courseContentPost): RedirectResponse
    {
        $data = $this->validated($request);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title'], $courseContentPost->id);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? $courseContentPost->published_at ?? now()) : null;

        DB::transaction(function () use ($courseContentPost, $data, $categoryIds): void {
            $post = CourseContentPost::query()->lockForUpdate()->findOrFail($courseContentPost->id);
            $post->update($data);
            $post->categories()->sync($categoryIds);
        });

        return redirect()->route('admin.course-content-posts.index')->with('success', __('Course content post updated.'));
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['required', 'integer', 'distinct', Rule::exists('course_content_posts', 'id')],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        DB::transaction(function () use ($data): void {
            $posts = CourseContentPost::query()
                ->whereIn('id', $data['post_ids'])
                ->lockForUpdate()
                ->get();

            abort_if($posts->count() !== count($data['post_ids']), 404);

            foreach ($posts as $post) {
                $publishedAt = match ($data['status']) {
                    'published' => $post->published_at ?? now(),
                    'draft' => null,
                    default => $post->published_at,
                };

                $post->update([
                    'status' => $data['status'],
                    'published_at' => $publishedAt,
                ]);
            }
        });

        return back()->with('success', __('Selected content posts updated.'));
    }

    public function destroy(CourseContentPost $courseContentPost): RedirectResponse
    {
        abort_if($courseContentPost->courses()->exists(), 422, __('Remove this post from every course curriculum before deleting it.'));
        $courseContentPost->delete();

        return back()->with('success', __('Course content post deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('course_content_posts', 'slug')->ignore($request->route('courseContentPost')?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['required', 'string', 'max:100000'],
            'video_url' => ['nullable', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && !CourseContentPost::supportsVideoUrl((string) $value)) {
                    $fail(__('Use a valid HTTPS YouTube or Vimeo video URL.'));
                }
            }],
            'thumbnail_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('course_categories', 'id')],
        ]);

        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->map(fn ($tag) => mb_substr($tag, 0, 40))
            ->unique(fn ($tag) => mb_strtolower($tag))
            ->take(20)->values()->all();

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'content';
        $slug = $base;
        $suffix = 2;

        while (CourseContentPost::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
