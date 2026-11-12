<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with(['course', 'categories'])->latest()->paginate(20),
            'categories' => ProductCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'courses' => Course::where('status', 'published')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['name']);
        $image = $request->file('image');
        $pdf = $request->file('download_file');
        $preview = $request->file('preview_file');
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids'], $data['image'], $data['download_file'], $data['preview_file'], $data['tags']);

        $newPaths = [];
        try {
            if ($image) {
                $data['image_path'] = $image->store('products', 'public');
                $newPaths[] = ['disk' => 'public', 'path' => $data['image_path']];
            }
            if ($data['type'] === 'ebook') {
                $data['is_downloadable'] = true;
                $data['download_path'] = $pdf->store('private/products', 'local');
                $newPaths[] = ['disk' => 'local', 'path' => $data['download_path']];
                if ($preview) {
                    $data['preview_path'] = $preview->store('ebook-previews', 'public');
                    $newPaths[] = ['disk' => 'public', 'path' => $data['preview_path']];
                }
            } else {
                $data['is_downloadable'] = false;
                $data['download_path'] = null;
                $data['preview_path'] = null;
            }
            $data['tags'] = $this->parseTags($request->input('tags'));
            if ($data['status'] === 'published') {
                $data['published_at'] = now();
            }

            DB::transaction(function () use ($data, $categoryIds): void {
                $product = Product::create($data);
                $product->categories()->sync($categoryIds);
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            throw $exception;
        }

        return back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $image = $request->file('image');
        $pdf = $request->file('download_file');
        $preview = $request->file('preview_file');
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids'], $data['image'], $data['download_file'], $data['preview_file'], $data['tags']);
        $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        $data['tags'] = $this->parseTags($request->input('tags'));

        $newPaths = [];
        $obsoletePaths = [];
        try {
            if ($image) {
                $data['image_path'] = $image->store('products', 'public');
                $newPaths[] = ['disk' => 'public', 'path' => $data['image_path']];
                if ($product->image_path) $obsoletePaths[] = ['disk' => 'public', 'path' => $product->image_path];
            }

            if ($data['type'] === 'ebook') {
                $data['is_downloadable'] = true;
                if ($pdf) {
                    $data['download_path'] = $pdf->store('private/products', 'local');
                    $newPaths[] = ['disk' => 'local', 'path' => $data['download_path']];
                    if ($product->download_path) $obsoletePaths[] = ['disk' => 'local', 'path' => $product->download_path];
                }
                if ($preview) {
                    $data['preview_path'] = $preview->store('ebook-previews', 'public');
                    $newPaths[] = ['disk' => 'public', 'path' => $data['preview_path']];
                    if ($product->preview_path) $obsoletePaths[] = ['disk' => 'public', 'path' => $product->preview_path];
                }
            } else {
                $data['is_downloadable'] = false;
                $data['download_path'] = null;
                $data['preview_path'] = null;
                if ($product->download_path) $obsoletePaths[] = ['disk' => 'local', 'path' => $product->download_path];
                if ($product->preview_path) $obsoletePaths[] = ['disk' => 'public', 'path' => $product->preview_path];
            }

            if ($data['status'] === 'published' && ! $product->published_at) {
                $data['published_at'] = now();
            } elseif ($data['status'] !== 'published') {
                $data['published_at'] = null;
            }

            DB::transaction(function () use ($product, $data, $categoryIds): void {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                $locked->update($data);
                $locked->categories()->sync($categoryIds);
            });
        } catch (Throwable $exception) {
            foreach ($newPaths as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            throw $exception;
        }

        foreach ($obsoletePaths as $file) {
            if (! in_array($file['path'], array_column($newPaths, 'path'), true)) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists()) {
            $product->update(['status' => 'archived']);
            return back()->with('success', 'Product has order history and was archived instead of deleted.');
        }
        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        if ($product->preview_path) Storage::disk('public')->delete($product->preview_path);
        if ($product->download_path) Storage::disk('local')->delete($product->download_path);
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $data['slug'] = $this->uniqueCategorySlug($data['name']);
        ProductCategory::create($data);
        return back()->with('success', 'Product category created.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['slug'] = $this->uniqueCategorySlug($data['name'], $category->id);
        $category->update($data);
        return back()->with('success', 'Product category updated.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        $category->products()->detach();
        $category->delete();
        return back()->with('success', 'Category deleted; products were preserved.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $hasOrderHistory = $product && $product->orderItems()->exists();
        $data = $request->validate([
            'type' => ['required', Rule::in($hasOrderHistory ? [$product->type] : ['course', 'ebook'])],
            'course_id' => ['nullable', 'required_if:type,course', Rule::exists('courses', 'id')->where(fn ($query) => $query->where('status', 'published'))],
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:30000'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'tags' => ['nullable', 'string', 'max:1500'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'sale_price' => ['nullable', 'numeric', 'gte:0', 'lt:regular_price'],
            'currency' => ['required', 'in:BDT'],
            'status' => ['required', 'in:draft,published,archived'],
            'featured' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array', 'max:100'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'download_file' => [Rule::prohibitedIf($hasOrderHistory), $request->input('type') === 'ebook' && (! $product || ! $product->download_path) ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:51200'],
            'preview_file' => [$request->input('type') === 'ebook' ? 'nullable' : 'prohibited', 'file', 'mimes:pdf', 'max:10240'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'download_expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['type'] === 'course') {
            $data['download_limit'] = null;
            $data['download_expires_at'] = null;
        } else {
            $data['course_id'] = null;
        }
        $data['featured'] = $request->boolean('featured');
        return $data;
    }

    private function parseTags(?string $tags): array
    {
        return collect(explode(',', (string) $tags))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique(fn ($tag) => Str::lower($tag))
            ->take(30)
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }

    private function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (ProductCategory::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
