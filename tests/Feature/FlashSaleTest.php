<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\FlashSale;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(float $price = 1000): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English',
            'slug' => 'spoken-english',
            'description' => 'Complete spoken English course.',
            'price' => $price,
            'status' => 'published',
        ]);
    }

    private function makeSale(Course $course, array $overrides = []): FlashSale
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = FlashSale::create(array_merge([
            'title' => 'November Mega Sale',
            'badge' => 'LIMITED OFFER',
            'description' => 'Special course price for a limited time.',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'max_redemptions' => null,
            'per_student_limit' => 1,
            'priority' => 10,
            'created_by' => $admin->id,
        ], $overrides));
        $sale->courses()->attach($course->id);
        return $sale;
    }

    public function test_admin_can_create_and_schedule_a_flash_sale_for_selected_courses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->makeCourse();

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), [
            'title' => 'Weekend offer',
            'badge' => 'SAVE 20%',
            'description' => 'Weekend only.',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'status' => 'active',
            'max_redemptions' => 30,
            'per_student_limit' => 1,
            'priority' => 5,
            'course_ids' => [$course->id],
        ])->assertRedirect();

        $sale = FlashSale::where('title', 'Weekend offer')->firstOrFail();
        $this->assertDatabaseHas('flash_sale_course', ['flash_sale_id' => $sale->id, 'course_id' => $course->id]);
    }

    public function test_non_admin_cannot_manage_flash_sales(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.flash-sales.index'))->assertForbidden();
    }

    public function test_homepage_displays_only_current_active_flash_sale_courses(): void
    {
        $course = $this->makeCourse();
        $sale = $this->makeSale($course);
        $this->get('/')->assertOk()
            ->assertSee('Flash Sale')
            ->assertSee('November Mega Sale')
            ->assertSee('৳750.00');

        $sale->update(['status' => 'paused']);
        $this->get('/')->assertOk()->assertDontSee('November Mega Sale');
    }

    public function test_manual_payment_uses_server_calculated_flash_sale_price_and_snapshots_campaign(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->makeCourse(1000);
        $sale = $this->makeSale($course, ['discount_value' => 30]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $this->actingAs($student)->post(route('courses.enroll', $course), [
            'payment_method' => 'manual',
            'transaction_reference' => 'FLASH-SALE-ORDER-001',
            'amount' => 1,
        ])->assertRedirect(route('courses.show', $course));

        $payment = Payment::where('transaction_reference', 'FLASH-SALE-ORDER-001')->firstOrFail();
        $this->assertSame('700.00', $payment->amount);
        $this->assertSame($sale->id, $payment->flash_sale_id);
    }

    public function test_expired_or_paused_campaign_never_changes_checkout_price(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->makeCourse(1000);
        $this->makeSale($course, ['status' => 'paused']);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $this->actingAs($student)->post(route('courses.enroll', $course), [
            'payment_method' => 'manual',
            'transaction_reference' => 'FLASH-SALE-ORDER-002',
        ])->assertRedirect();

        $payment = Payment::where('transaction_reference', 'FLASH-SALE-ORDER-002')->firstOrFail();
        $this->assertSame('1000.00', $payment->amount);
        $this->assertNull($payment->flash_sale_id);
    }
    public function test_category_targeted_flash_sale_applies_to_linked_course_product(): void
    {
        $course = $this->makeCourse(1200);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\ProductCategory::create(['name' => 'Spoken English', 'slug' => 'spoken-english-products']);
        $product = \App\Models\Product::create([
            'type' => 'course',
            'course_id' => $course->id,
            'name' => 'Spoken English Product',
            'slug' => 'spoken-english-product',
            'regular_price' => 1200,
            'currency' => 'BDT',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
        ]);
        $product->categories()->attach($category);
        $sale = FlashSale::create([
            'title' => 'Category offer',
            'badge' => 'SAVE 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'per_student_limit' => 1,
            'priority' => 10,
            'created_by' => $admin->id,
        ]);
        $sale->categories()->attach($category);

        $this->assertSame($sale->id, FlashSale::activeForCourse($course->id)?->id);
        $this->assertSame(1080.0, $sale->discountedPrice(1200));
    }


    public function test_category_targeted_flash_sale_changes_the_store_product_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\ProductCategory::create(['name' => 'eBooks', 'slug' => 'ebooks-sale']);
        $product = \App\Models\Product::create([
            'type' => 'ebook',
            'name' => 'English Grammar eBook',
            'slug' => 'english-grammar-ebook',
            'regular_price' => 500,
            'currency' => 'BDT',
            'status' => 'published',
            'published_at' => now(),
            'is_virtual' => true,
            'is_downloadable' => true,
            'download_path' => 'private/products/grammar.pdf',
            'created_by' => $admin->id,
        ]);
        $product->categories()->attach($category);
        $sale = FlashSale::create([
            'title' => 'eBook Category Sale',
            'badge' => 'SAVE 20%',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
            'per_student_limit' => 1,
            'priority' => 20,
            'created_by' => $admin->id,
        ]);
        $sale->categories()->attach($category);

        $this->assertSame('400.00', $product->currentPrice());
        $this->assertSame($sale->id, \App\Models\FlashSale::activeForProduct($product)?->id);
    }

}
