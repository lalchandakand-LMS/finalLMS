<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\ProductCategory;
use Illuminate\Validation\Rule;
use App\Models\FlashSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FlashSaleController extends Controller
{
    public function index(): View
    {
        $sales = FlashSale::with(['courses', 'categories', 'productCategories', 'creator'])->withCount([
            'payments as reserved_orders' => fn ($query) => $query->whereIn('status', ['pending', 'approved']),
            'enrollments as free_redemptions' => fn ($query) => $query->whereIn('status', ['active', 'completed']),
            'orderItems as store_order_items' => fn ($query) => $query->whereHas('order', fn ($orders) => $orders->whereIn('status', ['pending', 'completed'])),
        ])->latest()->paginate(20);
        $courses = Course::where('status', 'published')->orderBy('title')->get();
        $categories = CourseCategory::orderBy('sort_order')->orderBy('name')->get();
        $productCategories = ProductCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        return view('admin.flash-sales.index', compact('sales', 'courses', 'categories', 'productCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $image = $request->file('banner');
        $courseIds = $data['course_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        $productCategoryIds = $data['product_category_ids'] ?? [];
        unset($data['course_ids'], $data['category_ids'], $data['product_category_ids'], $data['banner']);
        if ($image) $data['banner_path'] = $image->store('flash-sales','public');
        $sale = FlashSale::create($data);
        $sale->courses()->sync($courseIds);
        $sale->categories()->sync($categoryIds);
        $sale->productCategories()->sync($productCategoryIds);
        return back()->with('success','Flash sale campaign created.');
    }

    public function update(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $this->validated($request);
        $image = $request->file('banner');
        $courseIds = $data['course_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        $productCategoryIds = $data['product_category_ids'] ?? [];
        unset($data['course_ids'], $data['category_ids'], $data['product_category_ids'], $data['banner']);
        if ($image) {
            if ($flashSale->banner_path) Storage::disk('public')->delete($flashSale->banner_path);
            $data['banner_path'] = $image->store('flash-sales','public');
        }
        $flashSale->update($data);
        $flashSale->courses()->sync($courseIds);
        $flashSale->categories()->sync($categoryIds);
        $flashSale->productCategories()->sync($productCategoryIds);
        return back()->with('success','Flash sale campaign updated.');
    }

    public function destroy(FlashSale $flashSale): RedirectResponse
    {
        if ($flashSale->payments()->whereIn('status', ['pending', 'approved'])->exists() || $flashSale->enrollments()->exists() || $flashSale->orderItems()->whereHas('order')->exists()) return back()->with('error', 'This campaign has redemption history. Archive it instead.');
        if ($flashSale->banner_path) Storage::disk('public')->delete($flashSale->banner_path);
        $flashSale->delete();
        return back()->with('success','Flash sale campaign deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'=>['required','string','max:180'],'badge'=>['required','string','max:60'],
            'description'=>['nullable','string','max:2000'],'discount_type'=>['required','in:percentage,fixed_price'],
            'discount_value'=>['required','numeric','gt:0', $request->input('discount_type') === 'percentage' ? 'max:99' : 'max:1000000'],
            'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],
            'status'=>['required','in:draft,active,paused,archived'],
            'max_redemptions'=>['nullable','integer','min:1','max:1000000'],
            'per_student_limit'=>['required','integer','min:1','max:100'],'priority'=>['required','integer','min:0','max:10000'],
            'course_ids' => ['nullable', 'array', 'required_without_all:category_ids,product_category_ids', 'min:1', 'max:500'],
            'course_ids.*' => ['required', 'integer', 'distinct', Rule::exists('courses', 'id')->where(fn ($query) => $query->where('status', 'published'))],
            'category_ids' => ['nullable', 'array', 'required_without_all:course_ids,product_category_ids', 'min:1', 'max:200'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:course_categories,id'],
            'product_category_ids' => ['nullable', 'array', 'required_without_all:course_ids,category_ids', 'min:1', 'max:200'],
            'product_category_ids.*' => ['required', 'integer', 'distinct', 'exists:product_categories,id'],
            'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);
    }
}
