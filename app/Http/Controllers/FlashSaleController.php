<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\FlashSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FlashSaleController extends Controller
{
    public function index(): View
    {
        $sales = FlashSale::with(['courses','creator'])->withCount(['payments as reserved_orders' => fn ($q) => $q->whereIn('status',['pending','approved'])])->latest()->paginate(20);
        $courses = Course::where('status','published')->orderBy('title')->get();
        $categories = \App\Models\ProductCategory::where('is_active', true)->orderBy('name')->get();
        return view('admin.flash-sales.index', compact('sales','courses','categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $image = $request->file('banner');
        unset($data['course_ids'], $data['category_ids'], $data['banner']);
        if ($image) $data['banner_path'] = $image->store('flash-sales','public');
        $sale = FlashSale::create($data);
        $sale->courses()->sync($request->input('course_ids',[]));
        $sale->categories()->sync($request->input('category_ids',[]));
        return back()->with('success','Flash sale campaign created.');
    }

    public function update(Request $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $this->validated($request);
        $image = $request->file('banner');
        unset($data['course_ids'], $data['category_ids'], $data['banner']);
        if ($image) {
            if ($flashSale->banner_path) Storage::disk('public')->delete($flashSale->banner_path);
            $data['banner_path'] = $image->store('flash-sales','public');
        }
        $flashSale->update($data);
        $flashSale->courses()->sync($request->input('course_ids',[]));
        $flashSale->categories()->sync($request->input('category_ids',[]));
        return back()->with('success','Flash sale campaign updated.');
    }

    public function destroy(FlashSale $flashSale): RedirectResponse
    {
        if ($flashSale->payments()->whereIn('status',['pending','approved'])->exists() || $flashSale->orderItems()->whereHas('order')->exists()) return back()->with('error','This campaign has payment or store order history. Archive it instead.');
        if ($flashSale->banner_path) Storage::disk('public')->delete($flashSale->banner_path);
        $flashSale->delete();
        return back()->with('success','Flash sale campaign deleted.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title'=>['required','string','max:180'],'badge'=>['required','string','max:60'],
            'description'=>['nullable','string','max:2000'],'discount_type'=>['required','in:percentage,fixed_price'],
            'discount_value'=>['required','numeric','gt:0', $request->input('discount_type') === 'percentage' ? 'max:99' : 'max:1000000'],
            'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],
            'status'=>['required','in:draft,active,paused,archived'],
            'max_redemptions'=>['nullable','integer','min:1','max:1000000'],
            'per_student_limit'=>['required','integer','min:1','max:100'],'priority'=>['required','integer','min:0','max:10000'],
            'course_ids'=>['nullable','array'],'course_ids.*'=>['required','integer','distinct','exists:courses,id'],
            'category_ids'=>['nullable','array'],'category_ids.*'=>['required','integer','distinct','exists:product_categories,id'],
            'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);
        if (empty($validated['course_ids']) && empty($validated['category_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['course_ids' => 'Select at least one course or product category.']);
        }
        return $validated;
    }
}
