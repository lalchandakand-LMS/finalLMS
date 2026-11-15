<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FlashSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentCartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $items = DB::table('student_cart_items')
            ->join('courses', 'courses.id', '=', 'student_cart_items.course_id')
            ->leftJoin('batches', 'batches.id', '=', 'student_cart_items.batch_id')
            ->where('student_cart_items.user_id', $student->id)
            ->where('courses.status', 'published')
            ->orderByDesc('student_cart_items.created_at')
            ->select([
                'student_cart_items.id', 'student_cart_items.course_id',
                'student_cart_items.batch_id', 'student_cart_items.created_at',
                'courses.title', 'courses.slug', 'courses.price',
                'batches.title as batch_title', 'batches.fee as batch_fee',
            ])->get();

        $data = $items->map(function ($item) use ($student): array {
            $base = (float) ($item->batch_id && (float) $item->batch_fee > 0
                ? $item->batch_fee : $item->price);
            $sale = FlashSale::activeForCourse((int) $item->course_id, $student->id);
            $total = $sale ? $sale->discountedPrice($base) : $base;

            return [
                'id' => $item->id,
                'course' => [
                    'id' => $item->course_id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                ],
                'batch' => $item->batch_id ? [
                    'id' => $item->batch_id,
                    'title' => $item->batch_title,
                ] : null,
                'pricing' => [
                    'currency' => 'BDT',
                    'base_amount' => number_format($base, 2, '.', ''),
                    'discount_amount' => number_format(max(0, $base - $total), 2, '.', ''),
                    'total' => number_format($total, 2, '.', ''),
                    'flash_sale_applied' => $sale !== null,
                ],
                'created_at' => $item->created_at,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'items_count' => $data->count(),
                'subtotal' => number_format((float) $data->sum(fn (array $item) => (float) $item['pricing']['base_amount']), 2, '.', ''),
                'discount_total' => number_format((float) $data->sum(fn (array $item) => (float) $item['pricing']['discount_amount']), 2, '.', ''),
                'total' => number_format((float) $data->sum(fn (array $item) => (float) $item['pricing']['total']), 2, '.', ''),
                'currency' => 'BDT',
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->where(fn ($query) => $query->where('status', 'published'))],
            'batch_id' => [Course::query()->whereKey($request->input('course_id'))->whereHas('batches', fn ($query) => $query->where('status', 'open'))->exists() ? 'required' : 'nullable', 'integer', Rule::exists('batches', 'id')->where(fn ($query) => $query->where('course_id', $request->input('course_id'))->where('status', 'open'))],
        ]);

        $course = Course::query()->whereKey($validated['course_id'])->where('status', 'published')->firstOrFail();
        abort_if(Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->whereIn('status', ['active', 'completed'])->exists(), 409, 'You are already enrolled in this course.');

        $batchId = $validated['batch_id'] ?? null;
        if ($batchId) {
            $batch = Batch::query()->whereKey($batchId)->where('course_id', $course->id)->where('status', 'open')->first();
            abort_unless($batch, 422, 'Choose an open batch for this course.');
        }

        DB::table('student_cart_items')->updateOrInsert(
            ['user_id' => $student->id, 'course_id' => $course->id],
            ['batch_id' => $batchId, 'created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['message' => 'Course added to your cart.', 'data' => ['course_id' => $course->id]], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless($course->status === 'published', 404);

        $validated = $request->validate([
            'batch_id' => [$course->batches()->where('status', 'open')->exists() ? 'required' : 'nullable', 'integer', Rule::exists('batches', 'id')->where(fn ($query) => $query->where('course_id', $course->id)->where('status', 'open'))],
        ]);

        $updated = DB::table('student_cart_items')
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->update(['batch_id' => $validated['batch_id'] ?? null, 'updated_at' => now()]);

        abort_unless($updated, 404, 'This course is not in your cart.');

        return response()->json(['message' => 'Cart item updated.']);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        DB::table('student_cart_items')
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->delete();

        return response()->json(['message' => 'Cart item removed.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        DB::table('student_cart_items')->where('user_id', $student->id)->delete();

        return response()->json(['message' => 'Cart cleared.']);
    }
}
