<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with('categories')->latest()->paginate(20),
            'categories' => ProductCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:30000'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'sale_price' => ['nullable', 'numeric', 'gte:0', 'lt:regular_price'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'download_expires_at' => ['nullable', 'date', 'after:now'],
            'status' => ['required', 'in:draft,published,archived'],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'download_file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $pdfPath = $request->file('download_file')->store('private/ebooks', 'local');
        $imagePath = $request->file('image')?->store('ebooks', 'public');

        try {
            $product = Product::create([
                'type' => 'ebook',
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'regular_price' => $data['regular_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'currency' => 'BDT',
                'is_downloadable' => true,
                'download_path' => $pdfPath,
                'download_limit' => $data['download_limit'] ?? null,
                'download_expires_at' => $data['download_expires_at'] ?? null,
                'image_path' => $imagePath,
                'status' => $data['status'],
                'published_at' => $data['status'] === 'published' ? now() : null,
                'created_by' => $request->user()->id,
            ]);
            $product->categories()->sync($data['category_ids'] ?? []);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($pdfPath);
            if ($imagePath) Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        return back()->with('success', 'eBook created. Its PDF is stored privately.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->type === 'ebook', 404);
        $hasOrders = $product->orderItems()->exists();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:30000'],
            'regular_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'sale_price' => ['nullable', 'numeric', 'gte:0', 'lt:regular_price'],
            'download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'download_expires_at' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published,archived'],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'download_file' => [$hasOrders ? 'prohibited' : 'nullable', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $newPdf = $request->file('download_file')?->store('private/ebooks', 'local');
        $newImage = $request->file('image')?->store('ebooks', 'public');
        $oldPdf = $product->download_path;
        $oldImage = $product->image_path;

        $product->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $product->id),
            'short_description' => $data['short_description'] ?? null,
            'description' => $data['description'] ?? null,
            'regular_price' => $data['regular_price'],
            'sale_price' => $data['sale_price'] ?? null,
            'download_limit' => $data['download_limit'] ?? null,
            'download_expires_at' => $data['download_expires_at'] ?? null,
            'download_path' => $newPdf ?: $oldPdf,
            'image_path' => $newImage ?: $oldImage,
            'status' => $data['status'],
            'published_at' => $data['status'] === 'published' ? ($product->published_at ?? now()) : null,
        ]);
        $product->categories()->sync($data['category_ids'] ?? []);

        if ($newPdf && $oldPdf) Storage::disk('local')->delete($oldPdf);
        if ($newImage && $oldImage) Storage::disk('public')->delete($oldImage);

        return back()->with('success', 'eBook updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless($product->type === 'ebook', 404);
        if ($product->orderItems()->exists()) {
            $product->update(['status' => 'archived', 'published_at' => null]);
            return back()->with('success', 'eBook archived because it has purchase history.');
        }

        if ($product->download_path) Storage::disk('local')->delete($product->download_path);
        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        $product->delete();

        return back()->with('success', 'eBook deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        ProductCategory::create([
            ...$data,
            'slug' => $this->uniqueCategorySlug($data['name']),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        return back()->with('success', 'eBook category created.');
    }

    public function destroyCategory(ProductCategory $category): RedirectResponse
    {
        $category->products()->detach();
        $category->delete();
        return back()->with('success', 'Category deleted; eBooks were preserved.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'ebook';
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
