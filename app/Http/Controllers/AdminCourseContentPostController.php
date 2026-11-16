<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\MediaAsset;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'academic_level' => ['nullable', 'integer', 'min:1'],
            'access_type' => ['nullable', Rule::in(['free', 'paid', 'course_enrollment'])],
        ]);

        $posts = CourseContentPost::query()->with(['author', 'thumbnail', 'categories', 'academicLevel', 'product'])
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(fn ($builder) => $builder
                    ->where('title', 'like', '%'.$term.'%')
                    ->orWhere('short_description', 'like', '%'.$term.'%'));
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn ($query, int $category) => $query->whereHas('categories', fn ($builder) => $builder->whereKey($category)))
            ->when($filters['academic_level'] ?? null, fn ($query, int $level) => $query->where('academic_level_id', $level))
            ->when($filters['access_type'] ?? null, fn ($query, string $type) => $query->where('access_type', $type))
            ->latest()->paginate(20)->withQueryString();

        $categories = CourseCategory::orderBy('name')->get(['id', 'name']);
        $levels = AcademicLevel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('admin.course-content-posts.index', compact('posts', 'categories', 'levels'));
    }

    public function create(): View
    {
        return $this->form(new CourseContentPost(['status' => 'draft', 'tags' => [], 'access_type' => 'course_enrollment']));
    }

    public function edit(CourseContentPost $courseContentPost): View
    {
        return $this->form($courseContentPost);
    }

    private function form(CourseContentPost $post): View
    {
        $post->loadMissing('product.previewPages');
        $categories = CourseCategory::orderBy('sort_order')->orderBy('name')->get();
        $media = MediaAsset::where('mime_type', 'like', 'image/%')->latest()->limit(300)->get();
        $levels = AcademicLevel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.course-content-posts.form', compact('post', 'categories', 'media', 'levels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids'], $data['thumbnail'], $data['content_file'], $data['remove_thumbnail'], $data['remove_content_file']);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title']);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now()) : null;

        $thumbnailPath = $request->file('thumbnail')?->store('course-content/thumbnails', 'public');
        $filePath = $request->file('content_file')?->store('private/course-content', 'local');
        $data['thumbnail_path'] = $thumbnailPath;
        $data['content_file_path'] = $filePath;
        $data['content_file_name'] = $request->file('content_file')?->getClientOriginalName();
        $data['content_file_mime'] = $request->file('content_file')?->getMimeType();
        $data['content_file_size'] = $request->file('content_file')?->getSize();

        try {
            DB::transaction(function () use (&$data, $categoryIds): void {
                $post = CourseContentPost::create($data);
                $post->categories()->sync($categoryIds);
                $this->syncPaidProduct($post);
            });
        } catch (\Throwable $exception) {
            if ($thumbnailPath) Storage::disk('public')->delete($thumbnailPath);
            if ($filePath) Storage::disk('local')->delete($filePath);
            throw $exception;
        }

        return redirect()->route('admin.course-content-posts.index')->with('success', __('Course content post created.'));
    }

    public function update(Request $request, CourseContentPost $courseContentPost): RedirectResponse
    {
        $data = $this->validated($request, $courseContentPost);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids'], $data['thumbnail'], $data['content_file'], $data['remove_thumbnail'], $data['remove_content_file']);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['title'], $courseContentPost->id);
        $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? $courseContentPost->published_at ?? now()) : null;

        $newThumbnail = $request->file('thumbnail')?->store('course-content/thumbnails', 'public');
        $newFile = $request->file('content_file')?->store('private/course-content', 'local');
        $oldThumbnail = $courseContentPost->thumbnail_path;
        $oldFile = $courseContentPost->content_file_path;
        $removeThumbnail = $request->boolean('remove_thumbnail') && ! $newThumbnail;
        $switchToLibrary = ! $newThumbnail && filled($request->input('thumbnail_media_id'));
        $removeFile = $request->boolean('remove_content_file') && ! $newFile;

        try {
            DB::transaction(function () use ($courseContentPost, &$data, $categoryIds, $newThumbnail, $newFile, $removeThumbnail, $removeFile): void {
                $post = CourseContentPost::query()->lockForUpdate()->findOrFail($courseContentPost->id);
                if ($newFile && $post->product_id && Product::find($post->product_id)?->orderItems()->exists()) {
                    abort(422, __('A paid PDF cannot be replaced after a purchase has been made.'));
                }

                if ($newThumbnail) {
                    $data['thumbnail_path'] = $newThumbnail;
                    $data['thumbnail_media_id'] = null;
                } elseif ($removeThumbnail || ! empty($data['thumbnail_media_id'])) {
                    $data['thumbnail_path'] = null;
                    if ($removeThumbnail) $data['thumbnail_media_id'] = null;
                }

                if ($newFile) {
                    $data['content_file_path'] = $newFile;
                    $data['content_file_name'] = request()->file('content_file')->getClientOriginalName();
                    $data['content_file_mime'] = request()->file('content_file')->getMimeType();
                    $data['content_file_size'] = request()->file('content_file')->getSize();
                } elseif ($removeFile) {
                    if ($post->product_id && Product::find($post->product_id)?->orderItems()->exists()) {
                        abort(422, __('A purchased PDF cannot be removed.'));
                    }
                    $data['content_file_path'] = null;
                    $data['content_file_name'] = null;
                    $data['content_file_mime'] = null;
                    $data['content_file_size'] = null;
                }

                $post->update($data);
                $post->categories()->sync($categoryIds);
                $this->syncPaidProduct($post);
            });
        } catch (\Throwable $exception) {
            if ($newThumbnail) Storage::disk('public')->delete($newThumbnail);
            if ($newFile) Storage::disk('local')->delete($newFile);
            throw $exception;
        }

        if (($newThumbnail || $removeThumbnail || $switchToLibrary) && $oldThumbnail) Storage::disk('public')->delete($oldThumbnail);
        if (($newFile || $removeFile) && $oldFile && ! ($courseContentPost->product?->orderItems()->exists())) {
            Storage::disk('local')->delete($oldFile);
        }

        return redirect()->route('admin.course-content-posts.index')->with('success', __('Course content post updated.'));
    }

    private function syncPaidProduct(CourseContentPost $post): void
    {
        if ($post->access_type !== 'paid') {
            if ($post->product_id) {
                $product = Product::find($post->product_id);
                if ($product) {
                    $product->update(['status' => 'archived']);
                }
            }
            return;
        }

        abort_unless($post->content_file_path && strtolower((string) $post->content_file_mime) === 'application/pdf',
            422, __('Paid content requires a PDF lesson file.'));
        abort_unless((float) $post->regular_price > 0, 422, __('Paid content must have a price greater than zero.'));

        $product = $post->product_id ? Product::find($post->product_id) : null;
        $product ??= new Product();
        if ($product->exists && $product->orderItems()->exists()
            && $product->download_path !== $post->content_file_path) {
            abort(422, __('The PDF cannot be replaced after a purchase has been made.'));
        }

        $imagePath = $post->thumbnail_path;
        if (! $imagePath && $post->thumbnail_media_id) {
            $media = MediaAsset::find($post->thumbnail_media_id);
            if ($media?->disk === 'public') {
                $imagePath = $media->path;
            }
        }

        $product->fill([
            'type' => 'ebook',
            'name' => $post->title,
            'slug' => $this->uniqueProductSlug($post->slug, $product->id),
            'short_description' => $post->short_description,
            'description' => $post->long_description,
            'image_path' => $imagePath,
            'regular_price' => $post->regular_price,
            'sale_price' => $post->sale_price,
            'currency' => 'BDT',
            'is_downloadable' => true,
            'download_path' => $post->content_file_path,
            'status' => $post->status,
            'published_at' => $post->published_at,
            'created_by' => $post->author_id,
        ]);
        $product->save();

        if (! $post->product_id) {
            $post->forceFill(['product_id' => $product->id])->save();
        }
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['required', 'integer', 'distinct', Rule::exists('course_content_posts', 'id')],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        DB::transaction(function () use ($data): void {
            $posts = CourseContentPost::query()->whereIn('id', $data['post_ids'])->lockForUpdate()->get();
            abort_if($posts->count() !== count($data['post_ids']), 404);
            foreach ($posts as $post) {
                $publishedAt = match ($data['status']) {
                    'published' => $post->published_at ?? now(),
                    'draft' => null,
                    default => $post->published_at,
                };
                $post->update(['status' => $data['status'], 'published_at' => $publishedAt]);
                if ($post->product_id && ($product = Product::find($post->product_id))) {
                    $product->update(['status' => $data['status'], 'published_at' => $publishedAt]);
                }
            }
        });

        return back()->with('success', __('Selected content posts updated.'));
    }

    public function destroy(CourseContentPost $courseContentPost): RedirectResponse
    {
        abort_if($courseContentPost->courses()->exists(), 422, __('Remove this post from every course curriculum before deleting it.'));
        DB::transaction(function () use ($courseContentPost): void {
            if ($courseContentPost->product_id && ($product = Product::find($courseContentPost->product_id))) {
                $product->update(['status' => 'archived']);
            }
            $courseContentPost->delete();
        });
        if ($courseContentPost->thumbnail_path) Storage::disk('public')->delete($courseContentPost->thumbnail_path);
        if ($courseContentPost->content_file_path && ! ($courseContentPost->product?->orderItems()->exists())) {
            Storage::disk('local')->delete($courseContentPost->content_file_path);
        }

        return back()->with('success', __('Course content post deleted.'));
    }

    private function validated(Request $request, ?CourseContentPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('course_content_posts', 'slug')->ignore($post?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['required', 'string', 'max:100000'],
            'video_url' => ['nullable', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && !CourseContentPost::supportsVideoUrl((string) $value)) {
                    $fail(__('Use a valid HTTPS YouTube or Vimeo video URL.'));
                }
            }],
            'thumbnail_media_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')->where(fn ($query) => $query->where('mime_type', 'like', 'image/%'))],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'content_file' => ['nullable', 'file', 'mimes:pdf,mp4,mp3,jpg,jpeg,png,webp,zip', 'max:102400'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'remove_content_file' => ['nullable', 'boolean'],
            'access_type' => ['nullable', Rule::in(['free', 'paid', 'course_enrollment'])],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sale_price' => ['nullable', 'numeric', 'gte:0', 'lt:regular_price'],
            'academic_level_id' => ['nullable', 'integer', Rule::exists('academic_levels', 'id')],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('course_categories', 'id')],
        ]);

        $data['access_type'] ??= $post?->access_type ?? 'course_enrollment';

        if (($data['access_type'] ?? null) !== 'paid') {
            $data['regular_price'] = null;
            $data['sale_price'] = null;
        }

        $data['tags'] = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))->filter()
            ->map(fn ($tag) => mb_substr($tag, 0, 40))
            ->unique(fn ($tag) => mb_strtolower($tag))->take(20)->values()->all();

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

    private function uniqueProductSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'content';
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
