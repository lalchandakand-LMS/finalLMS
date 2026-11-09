<?php

namespace App\Http\Controllers;

use App\Models\Course;
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
            'products' => Product::with(['course','categories'])->latest()->paginate(20),
            'categories' => ProductCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['name']);
        $image = $request->file('image');
        $pdf = $request->file('download_file');
        unset($data['category_ids'], $data['image'], $data['download_file']);

        if ($image) $data['image_path'] = $image->store('products', 'public');
        if ($data['type'] === 'ebook') {
            $data['is_downloadable'] = true;
            $data['download_path'] = $pdf->store('private/products');
        } else {
            $data['is_downloadable'] = false;
            $data['download_path'] = null;
        }
        if ($data['status'] === 'published') $data['published_at'] = now();

        $product = Product::create($data);
        $product->categories()->sync($request->input('category_ids', []));

        return back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $image = $request->file('image');
        $pdf = $request->file('download_file');
        unset($data['category_ids'], $data['image'], $data['download_file']);
        $data['slug'] = $this->uniqueSlug($data['name'], $product->id);

        if ($image) {
            $newPath = $image->store('products', 'public');
            if ($product->image_path) Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = $newPath;
        }
        if ($data['type'] === 'ebook') {
            $data['is_downloadable'] = true;
            if ($pdf) {
                $newPath = $pdf->store('private/products');
                if ($product->download_path) Storage::disk('local')->delete($product->download_path);
                $data['download_path'] = $newPath;
            }
        } else {
            $data['is_downloadable'] = false;
            $data['download_path'] = null;
        }
        if ($data['status'] === 'published' && !$product->published_at) $data['published_at'] = now();
        if ($data['status'] !== 'published') $data['published_at'] = null;

        $product->update($data);
        $product->categories()->sync($request->input('category_ids', []));

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists()) {
            $product->update(['status' => 'archived']);
            return back()->with('success', 'Product has order history and was archived instead of deleted.');
        }
        if ($product->image_path) Storage::disk('public')->delete($product->image_path);
        if ($product->download_path) Storage::disk('local')->delete($product->download_path);
        $product->delete();

        return back()->with('success', 'Product deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'description' => ['nullable','string','max:2000'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
        ]);
        $data['slug'] = $this->uniqueCategorySlug($data['name']);
        ProductCategory::create($data);
        return back()->with('success', 'Product category created.');
    }

    public function updateCategory(Request $request, ProductCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'description' => ['nullable','string','max:2000'],
            'sort_order' => ['nullable','integer','min:0','max:100000'],
            'is_active' => ['required','boolean'],
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
        $rules = [
            'type' => ['required','in:course,ebook'],
            'course_id' => ['nullable','required_if:type,course','exists:courses,id'],
            'name' => ['required','string','max:180'],
            'sku' => ['nullable','string','max:80','unique:products,sku'.($product ? ','.$product->id : '')],
            'short_description' => ['nullable','string','max:500'],
            'description' => ['nullable','string','max:30000'],
            'regular_price' => ['required','numeric','min:0','max:999999999'],
            'sale_price' => ['nullable','numeric','gte:0','lt:regular_price'],
            'currency' => ['required','in:BDT'],
            'status' => ['required','in:draft,published,archived'],
            'featured' => ['nullable','boolean'],
            'category_ids' => ['nullable','array'],
            'category_ids.*' => ['integer','distinct','exists:product_categories,id'],
            'image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'download_file' => [$request->input('type') === 'ebook' && !$product ? 'required' : 'nullable','file','mimes:pdf','max:51200'],
            'download_limit' => ['nullable','integer','min:1','max:1000'],
        ];
        $data = $request->validate($rules);
        if ($data['type'] === 'course') {
            $data['download_limit'] = null;
        } else {
            $data['course_id'] = null;
        }
        $data['featured'] = $request->boolean('featured');
        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id','!=',$ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }

    private function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;
        while (ProductCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id','!=',$ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }
        return $slug;
    }
}
