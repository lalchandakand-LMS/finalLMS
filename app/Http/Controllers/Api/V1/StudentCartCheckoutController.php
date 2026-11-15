<?php

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Batch;
use App\\Models\\Course;
use App\\Models\\CourseCombo;
use App\\Models\\Coupon;
use App\\Models\\CouponRedemption;
use App\\Models\\Enrollment;
use App\\Models\\FlashSale;
use App\\Models\\Payment;
use App\\Models\\PaymentComboCourse;
use App\\Models\\Setting;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Storage;
use Illuminate\\Support\\Str;
use Illuminate\\Validation\\Rule;

class StudentCartCheckoutController extends Controller
{
    public function applyCoupon(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        $validated = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $items = $this->cartItems((int) $student->id);
        abort_if($items->isEmpty(), 422, 'Your cart is empty.');

        $pricing = $this->priceItems($items, (int) $student->id);
        $coupon = Coupon::query()->available()->where('code', Str::upper(trim($validated['code'])))->with('courses:id')->first();
        abort_unless($coupon, 422, 'This coupon is invalid, inactive or expired.');
        $this->assertCouponAvailable($coupon, $student->id, $pricing['subtotal'], $items, $pricing['has_flash_sale']);

        $eligibleIds = $coupon->courses->pluck('id')->all();
        $eligibleSubtotal = $coupon->courses->isEmpty()
            ? $pricing['subtotal']
            : (float) $items->whereIn('course_id', $eligibleIds)->sum(fn ($item) => $pricing['line_totals'][$item->course_id] ?? 0);
        $discount = $this->couponDiscount($coupon, $eligibleSubtotal);

        return response()->json([
            'data' => [
                'code' => $coupon->code,
                'name' => $coupon->name,
                'currency' => 'BDT',
                'subtotal' => number_format($pricing['subtotal'], 2, '.', ''),
                'eligible_subtotal' => number_format($eligibleSubtotal, 2, '.', ''),
                'discount' => number_format($discount, 2, '.', ''),
                'total' => number_format(max(0, $pricing['subtotal'] - $discount), 2, '.', ''),
                'stacking' => 'not_allowed_with_flash_sale',
            ],
        ]);
    }

    public function submitManual(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        $methods = collect(Setting::paymentMethods())
            ->filter(fn (array $method) => (bool) ($method['enabled'] ?? false))
            ->keys()->all();

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', Rule::in($methods)],
            'transaction_reference' => ['nullable', 'string', 'max:120', Rule::requiredIf(fn () => filled($request->input('payment_method'))), Rule::unique('payments', 'transaction_reference')],
            'sender_account' => ['nullable', 'string', 'max:60'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        try {
            $result = DB::transaction(function () use ($student, $validated, $methods, $request, &$proofPath): array {
                $cart = DB::table('student_cart_items')->where('user_id', $student->id)->orderBy('id')->lockForUpdate()->get();
                abort_if($cart->isEmpty(), 422, 'Your cart is empty.');

                $courseIds = $cart->pluck('course_id')->map(fn ($id) => (int) $id)->all();
                $courses = Course::query()->whereIn('id', $courseIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                abort_if($courses->count() !== count($courseIds) || $courses->contains(fn (Course $course) => $course->status !== 'published'), 409, 'A course in your cart is no longer available.');

                $batchIds = $cart->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->all();
                $batches = Batch::query()->whereIn('id', $batchIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $items = collect();
                foreach ($cart as $row) {
                    $course = $courses->get((int) $row->course_id);
                    $batch = $row->batch_id ? $batches->get((int) $row->batch_id) : null;
                    abort_if($row->batch_id && (! $batch || (int) $batch->course_id !== (int) $course->id || $batch->status !== 'open'), 409, 'A selected batch is no longer available.');
                    abort_if(Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->whereIn('status', ['active', 'completed'])->exists(), 409, 'You are already enrolled in a course in this cart.');
                    if ($batch) {
                        abort_if(! $this->hasAvailableSeat($batch), 409, 'A selected batch has no seats remaining.');
                    }
                    $items->push((object) ['course_id' => $course->id, 'course' => $course, 'batch' => $batch]);
                }

                $pricing = $this->priceItems($items, (int) $student->id);
                $coupon = null;
                $discount = 0.0;
                if (filled($validated['coupon_code'] ?? null)) {
                    abort_if($pricing['has_flash_sale'], 422, 'Coupon codes cannot be combined with a Flash Sale.');
                    $coupon = Coupon::query()->available()->where('code', Str::upper(trim($validated['coupon_code'])))->lockForUpdate()->first();
                    abort_unless($coupon, 422, 'This coupon is invalid, inactive or expired.');
                    $this->assertCouponAvailable($coupon, $student->id, $pricing['subtotal'], $items, false);
                    $eligibleIds = $coupon->courses()->pluck('courses.id')->all();
                    $eligibleSubtotal = empty($eligibleIds) ? $pricing['subtotal'] : (float) $items->whereIn('course_id', $eligibleIds)->sum(fn ($item) => $pricing['line_totals'][$item->course_id] ?? 0);
                    abort_if($eligibleSubtotal <= 0, 422, 'This coupon does not apply to any course in your cart.');
                    $discount = $this->couponDiscount($coupon, $eligibleSubtotal);
                }

                $total = max(0, round($pricing['subtotal'] - $discount, 2));
                if ($total <= 0) {
                    foreach ($items as $item) {
                        Enrollment::firstOrCreate(
                            ['user_id' => $student->id, 'course_id' => $item->course_id],
                            ['batch_id' => $item->batch?->id, 'status' => 'active']
                        );
                    }
                    if ($coupon) {
                        CouponRedemption::create([
                            'coupon_id' => $coupon->id, 'user_id' => $student->id,
                            'discount_amount' => number_format($discount, 2, '.', ''),
                            'status' => 'redeemed', 'redeemed_at' => now(),
                        ]);
                    }
                    DB::table('student_cart_items')->where('user_id', $student->id)->delete();

                    return ['status' => 'enrolled_free', 'course_count' => $items->count()];
                }

                abort_unless(in_array($validated['payment_method'] ?? null, $methods, true)
                    && filled($validated['transaction_reference'] ?? null), 422,
                    'Choose an enabled payment method and provide its transaction reference.');

                $proofPath = $request->file('proof')?->store('payment-proofs', 'local');
                $reference = trim($validated['transaction_reference']);
                if ($items->count() === 1) {
                    $item = $items->first();
                    $sale = FlashSale::activeForCourse($item->course_id, $student->id);
                    $payment = Payment::create([
                        'user_id' => $student->id, 'course_id' => $item->course_id,
                        'batch_id' => $item->batch?->id, 'flash_sale_id' => $sale?->id,
                        'method' => $validated['payment_method'], 'amount' => number_format($total, 2, '.', ''),
                        'currency' => 'BDT', 'sender_account' => $validated['sender_account'] ?? null,
                        'transaction_reference' => $reference, 'proof_path' => $proofPath, 'status' => 'pending',
                    ]);
                } else {
                    $combo = CourseCombo::create([
                        'title' => 'Cart checkout '.$student->id.' '.now()->format('YmdHis'),
                        'slug' => 'cart-checkout-'.Str::lower(Str::random(24)),
                        'description' => 'System-generated student cart checkout. Do not edit.',
                        'price' => number_format($total, 2, '.', ''),
                        'status' => 'archived',
                    ]);
                    $combo->courses()->sync($items->pluck('course_id')->all());
                    $payment = Payment::create([
                        'user_id' => $student->id, 'course_id' => $items->first()->course_id,
                        'course_combo_id' => $combo->id,
                        'method' => $validated['payment_method'], 'amount' => number_format($total, 2, '.', ''),
                        'currency' => 'BDT', 'sender_account' => $validated['sender_account'] ?? null,
                        'transaction_reference' => $reference, 'proof_path' => $proofPath, 'status' => 'pending',
                    ]);
                    foreach ($items->values() as $position => $item) {
                        PaymentComboCourse::create([
                            'payment_id' => $payment->id, 'course_id' => $item->course_id,
                            'batch_id' => $item->batch?->id, 'course_title' => $item->course->title,
                            'position' => $position + 1,
                        ]);
                    }
                }

                if ($coupon) {
                    CouponRedemption::create([
                        'coupon_id' => $coupon->id, 'user_id' => $student->id,
                        'payment_id' => $payment->id, 'discount_amount' => number_format($discount, 2, '.', ''),
                        'status' => 'reserved',
                    ]);
                }

                DB::table('student_cart_items')->where('user_id', $student->id)->delete();

                return [
                    'status' => 'submitted', 'payment_id' => $payment->id,
                    'amount' => $payment->amount, 'currency' => 'BDT',
                    'course_count' => $items->count(), 'payment_status' => 'pending',
                ];
            });
        } catch (\\Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }

        return match ($result['status']) {
            'enrolled_free' => response()->json(['message' => 'Cart enrollment completed.', 'data' => $result], 201),
            default => response()->json(['message' => 'Cart payment submitted and is awaiting academy verification.', 'data' => $result], 201),
        };
    }

    private function cartItems(int $userId)
    {
        return DB::table('student_cart_items')
            ->join('courses', 'courses.id', '=', 'student_cart_items.course_id')
            ->leftJoin('batches', 'batches.id', '=', 'student_cart_items.batch_id')
            ->where('student_cart_items.user_id', $userId)
            ->where('courses.status', 'published')
            ->orderBy('student_cart_items.id')
            ->get([
                'student_cart_items.course_id', 'student_cart_items.batch_id',
                'courses.price', 'batches.fee as batch_fee',
            ]);
    }

    private function priceItems($items, int $studentId): array
    {
        $subtotal = 0.0;
        $lineTotals = [];
        $hasSale = false;
        foreach ($items as $item) {
            $base = $item->batch && (float) $item->batch->fee > 0
                ? (float) $item->batch->fee
                : (float) $item->course->price;
            if (! isset($item->course)) {
                $base = $item->batch_id && (float) ($item->batch_fee ?? 0) > 0
                    ? (float) $item->batch_fee : (float) $item->price;
                $sale = FlashSale::activeForCourse((int) $item->course_id, $studentId);
                $amount = $sale ? $sale->discountedPrice($base) : $base;
            } else {
                $sale = FlashSale::activeForCourse((int) $item->course_id, $studentId);
                $amount = $sale ? $sale->discountedPrice($base) : $base;
            }
            $hasSale = $hasSale || $sale !== null;
            $subtotal += $amount;
            $lineTotals[(int) $item->course_id] = $amount;
        }

        return ['subtotal' => round($subtotal, 2), 'line_totals' => $lineTotals, 'has_flash_sale' => $hasSale];
    }

    private function assertCouponAvailable(Coupon $coupon, int $userId, float $subtotal, $items, bool $hasFlashSale): void
    {
        abort_if($hasFlashSale, 422, 'Coupon codes cannot be combined with a Flash Sale.');
        abort_if($subtotal < (float) $coupon->minimum_subtotal, 422, 'Your cart does not meet the coupon minimum subtotal.');
        $reserved = fn ($query) => $query->whereIn('status', ['reserved', 'redeemed']);
        if ($coupon->usage_limit !== null) {
            abort_if($coupon->redemptions()->where($reserved)->count() >= $coupon->usage_limit, 409, 'This coupon has reached its usage limit.');
        }
        abort_if($coupon->redemptions()->where('user_id', $userId)->where($reserved)->count() >= $coupon->per_user_limit, 409, 'You have reached this coupon usage limit.');
        $eligibleIds = $coupon->courses()->pluck('courses.id')->all();
        if ($eligibleIds !== []) {
            abort_if($items->whereIn('course_id', $eligibleIds)->isEmpty(), 422, 'This coupon does not apply to your cart.');
        }
    }

    private function couponDiscount(Coupon $coupon, float $eligibleSubtotal): float
    {
        $discount = $coupon->discount_type === 'percentage'
            ? $eligibleSubtotal * ((float) $coupon->discount_value / 100)
            : (float) $coupon->discount_value;
        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }

        return round(min($eligibleSubtotal, max(0, $discount)), 2);
    }

    private function hasAvailableSeat(Batch $batch): bool
    {
        $occupied = Enrollment::query()->where('batch_id', $batch->id)->whereIn('status', ['active', 'completed'])->count();
        $pending = Payment::query()->where('batch_id', $batch->id)->where('status', 'pending')->count();

        return $occupied + $pending < $batch->capacity;
    }
}
