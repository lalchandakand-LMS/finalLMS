<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::query()->where('type', 'ebook')->with('categories')->latest()->paginate(20),
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
            DB::transaction(function () use ($data, $pdfPath, $imagePath, $request): void {
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
            });
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
            'download_expires_at' => ['nullable', 'date', 'after:now'],
            'status' => ['required', 'in:draft,published,archived'],
            'category_ids' => ['nullable', 'array', 'max:30'],
            'category_ids.*' => ['integer', 'distinct', 'exists:product_categories,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'download_file' => [$hasOrders ? 'prohibited' : 'nullable', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $newPdf = $request->file('download_file')?->store('private/ebooks', 'local');
        $newImage = $request->file('image')?->store('ebooks', 'public');
        $oldPaths = ['pdf' => null, 'image' => null];

        try {
            DB::transaction(function () use ($product, $data, $newPdf, $newImage, &$oldPaths): void {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                abort_unless($locked->type === 'ebook', 404);
                abort_if($newPdf && $locked->orderItems()->exists(), 422, 'The PDF cannot be replaced after an order exists.');

                $oldPaths['pdf'] = $locked->download_path;
                $oldPaths['image'] = $locked->image_path;
                $locked->update([
                    'name' => $data['name'],
                    'slug' => $this->uniqueSlug($data['name'], $locked->id),
                    'short_description' => $data['short_description'] ?? null,
                    'description' => $data['description'] ?? null,
                    'regular_price' => $data['regular_price'],
                    'sale_price' => $data['sale_price'] ?? null,
                    'download_limit' => $data['download_limit'] ?? null,
                    'download_expires_at' => $data['download_expires_at'] ?? null,
                    'download_path' => $newPdf ?: $locked->download_path,
                    'image_path' => $newImage ?: $locked->image_path,
                    'status' => $data['status'],
                    'published_at' => $data['status'] === 'published' ? ($locked->published_at ?? now()) : null,
                ]);
                $locked->categories()->sync($data['category_ids'] ?? []);
            });
        } catch (\Throwable $exception) {
            if ($newPdf) Storage::disk('local')->delete($newPdf);
            if ($newImage) Storage::disk('public')->delete($newImage);
            throw $exception;
        }

        if ($newPdf && $oldPaths['pdf']) Storage::disk('local')->delete($oldPaths['pdf']);
        if ($newImage && $oldPaths['image']) Storage::disk('public')->delete($oldPaths['image']);

        return back()->with('success', 'eBook updated.');
    }

    public function storePreviewPage(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->type === 'ebook', 404);
        $data = $request->validate([
            'page_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:180'],
        ]);

        $path = $request->file('page_image')->store('private/ebook-previews/'.$product->id, 'local');
        try {
            DB::transaction(function () use ($product, $request, $data, $path): void {
                $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
                abort_unless($locked->type === 'ebook', 404);
                $usedPages = $locked->previewPages()->pluck('page_number')->map(fn ($number) => (int) $number)->all();
                abort_if(count($usedPages) >= 10, 422, 'An eBook can have at most 10 preview pages.');

                $pageNumber = 1;
                while (in_array($pageNumber, $usedPages, true)) {
                    $pageNumber++;
                }

                $locked->previewPages()->create([
                    'page_number' => $pageNumber,
                    'file_path' => $path,
                    'alt_text' => $data['alt_text'] ?? null,
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Private eBook preview page uploaded.');
    }

    public function destroyPreviewPage(Product $product, \App\Models\EbookPreviewPage $previewPage): RedirectResponse
    {
        abort_unless($product->type === 'ebook' && (int) $previewPage->product_id === (int) $product->id, 404);

        $path = DB::transaction(function () use ($product, $previewPage): string {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $page = $locked->previewPages()->lockForUpdate()->findOrFail($previewPage->id);
            $path = $page->file_path;
            $page->delete();

            return $path;
        });

        Storage::disk('local')->delete($path);

        return back()->with('success', 'Private eBook preview page removed.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless($product->type === 'ebook', 404);

        $result = DB::transaction(function () use ($product): array {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($locked->orderItems()->exists()) {
                $locked->update(['status' => 'archived', 'published_at' => null]);
                return ['archived' => true, 'pdf' => null, 'image' => null];
            }

            $paths = ['archived' => false, 'pdf' => $locked->download_path, 'image' => $locked->image_path];
            $locked->categories()->detach();
            $locked->delete();

            return $paths;
        });

        if ($result['archived']) {
            return back()->with('success', 'eBook archived because it has purchase history.');
        }
        if ($result['pdf']) Storage::disk('local')->delete($result['pdf']);
        if ($result['image']) Storage::disk('public')->delete($result['image']);

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
