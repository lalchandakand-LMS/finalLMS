<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\CourseCurriculumItem;
use App\Models\Enrollment;
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
    public function test_category_targeted_flash_sale_is_applied_at_server_calculated_checkout(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->makeCourse(1200);
        $category = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar-checkout', 'is_visible' => true]);
        $post = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Grammar curriculum',
            'slug' => 'grammar-curriculum-checkout',
            'long_description' => 'Grammar content',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $post->categories()->attach($category->id);
        CourseCurriculumItem::create([
            'course_id' => $course->id,
            'course_content_post_id' => $post->id,
            'position' => 1,
        ]);

        $sale = $this->makeSale($course, ['discount_value' => 25]);
        $sale->courses()->detach($course->id);
        $sale->categories()->attach($category->id);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $this->actingAs($student)->post(route('courses.enroll', $course), [
            'payment_method' => 'manual',
            'transaction_reference' => 'CATEGORY-FLASH-SALE-001',
            'amount' => 1,
        ])->assertRedirect(route('courses.show', $course));

        $payment = Payment::where('transaction_reference', 'CATEGORY-FLASH-SALE-001')->firstOrFail();
        $this->assertSame('900.00', $payment->amount);
        $this->assertSame($sale->id, $payment->flash_sale_id);
    }

    public function test_free_flash_sale_enrollments_count_toward_campaign_and_student_limits(): void
    {
        $course = $this->makeCourse(0);
        $sale = $this->makeSale($course, ['max_redemptions' => 1, 'per_student_limit' => 1]);
        $firstStudent = User::factory()->create(['role' => 'student']);
        $secondStudent = User::factory()->create(['role' => 'student']);

        $this->actingAs($firstStudent)->from(route('courses.show', $course))->post(route('courses.enroll', $course))
            ->assertRedirect(route('courses.show', $course));

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $firstStudent->id,
            'course_id' => $course->id,
            'flash_sale_id' => $sale->id,
            'status' => 'active',
        ]);

        $this->assertNull(FlashSale::activeForCourse($course->id, $secondStudent->id));
        $this->assertFalse($sale->fresh()->isAvailableFor($firstStudent->id));
    }

    public function test_flash_sale_priority_and_redemption_fallback_are_deterministic(): void
    {
        $course = $this->makeCourse(1000);
        $student = User::factory()->create(['role' => 'student']);
        $lowerPriority = $this->makeSale($course, [
            'title' => 'Standard offer',
            'priority' => 10,
            'discount_value' => 10,
        ]);
        $higherPriority = $this->makeSale($course, [
            'title' => 'Priority offer',
            'priority' => 20,
            'discount_value' => 30,
            'max_redemptions' => 1,
        ]);

        $this->assertSame($higherPriority->id, FlashSale::activeForCourse($course->id, $student->id)?->id);

        Enrollment::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'course_id' => $course->id,
            'flash_sale_id' => $higherPriority->id,
            'status' => 'active',
        ]);

        $this->assertFalse($higherPriority->fresh()->isAvailableFor($student->id));
        $this->assertSame($lowerPriority->id, FlashSale::activeForCourse($course->id, $student->id)?->id);
    }

    public function test_fixed_price_flash_sale_never_exceeds_the_base_price(): void
    {
        $course = $this->makeCourse(1000);
        $sale = $this->makeSale($course, ['discount_type' => 'fixed_price', 'discount_value' => 1500]);

        $this->assertSame(1000.0, $sale->discountedPrice(1000));
        $this->assertSame(0.0, $sale->discountedPrice(0));
    }

    public function test_flash_sale_can_target_a_category_and_display_its_published_course_curriculum(): void
    {
        $course = $this->makeCourse(1200);
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar', 'is_visible' => true]);
        $post = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Grammar foundations',
            'slug' => 'grammar-foundations',
            'long_description' => 'Grammar lesson',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $post->categories()->attach($category->id);
        CourseCurriculumItem::create([
            'course_id' => $course->id,
            'course_content_post_id' => $post->id,
            'position' => 1,
        ]);

        $sale = $this->makeSale($course);
        $sale->courses()->detach($course->id);
        $sale->categories()->attach($category->id);

        $this->assertSame($sale->id, FlashSale::activeForCourse($course->id)?->id);
        $campaign = FlashSale::activeCampaigns()->firstWhere('id', $sale->id);
        $this->assertNotNull($campaign);
        $this->assertSame([$course->id], $campaign->displayCourses->pluck('id')->all());
    }

    public function test_admin_can_create_a_flash_sale_using_only_a_published_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create(['name' => 'Speaking', 'slug' => 'speaking', 'is_visible' => true]);

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), [
            'title' => 'Speaking category offer',
            'badge' => 'SAVE 15%',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'starts_at' => now()->subMinute()->format('Y-m-d\\TH:i'),
            'ends_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            'status' => 'active',
            'per_student_limit' => 1,
            'priority' => 5,
            'category_ids' => [$category->id],
        ])->assertRedirect();

        $sale = FlashSale::where('title', 'Speaking category offer')->firstOrFail();
        $this->assertTrue($sale->categories->contains($category));
        $this->assertCount(0, $sale->courses);
    }

}
