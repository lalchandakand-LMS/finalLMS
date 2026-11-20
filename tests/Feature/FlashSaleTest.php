<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\CourseCurriculumItem;
use App\Models\Enrollment;
use App\Models\FlashSale;
use App\Models\Payment;
use App\Models\PluginInstallation;
use App\Models\Setting;
use App\Models\StudentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Services\Plugins\PluginContext;
use App\Services\Plugins\PluginHookRegistry;
use App\Services\Plugins\PluginSignatureVerifier;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        config(['plugins.trusted_public_keys' => [
            'flash-sale-test-key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]]);

        $manifest = json_decode(
            file_get_contents(base_path('plugins/aec/flash-sale/1.0.0/plugin.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $packageRoot = base_path('plugins/aec/flash-sale/1.0.0');
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath($packageRoot), FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $realRoot = rtrim((string) realpath($packageRoot), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($realRoot)));
            $hashes[$relative] = hash_file('sha256', $item->getRealPath());
        }
        $checksum = hash('sha256', 'signed flash-sale test package');
        $signature = base64_encode(sodium_crypto_sign_detached(
            PluginSignatureVerifier::payloadForSignature($manifest, $checksum),
            sodium_crypto_sign_secretkey($keyPair),
        ));

        DB::table('plugin_registry')->insert([
            'plugin_key' => 'aec.flash-sale',
            'name' => 'Flash Sale',
            'version' => '1.0.0',
            'type' => 'module',
            'status' => 'active',
            'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'package_sha256' => $checksum,
            'signature_key_id' => 'flash-sale-test-key',
            'signature_verified_at' => now(),
            'package_path' => 'plugins/aec/flash-sale/1.0.0',
            'health_status' => 'healthy',
            'metadata' => json_encode([
                'granted_permissions' => ['flash-sale.manage'],
                'integrity_version' => 1,
                'release_signature' => $signature,
                'signature_record_version' => 1,
                'installed_file_hashes' => $hashes,
            ], JSON_THROW_ON_ERROR),
            'installed_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $installation = PluginInstallation::query()->where('plugin_key', 'aec.flash-sale')->firstOrFail();
        $plugin = require base_path('plugins/aec/flash-sale/1.0.0/src/Plugin.php');
        $plugin->register(new PluginContext(app(), app('router'), $installation, app(PluginHookRegistry::class)));

        foreach ([
            'admin.flash-sales.index',
            'admin.flash-sales.store',
            'admin.flash-sales.update',
            'admin.flash-sales.destroy',
            'flash-sales.public',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Route lookup must resolve dynamically registered plugin route [{$routeName}].");
        }

        $registeredFlashRoutes = array_map(
            fn ($route): string => $route->uri().' => '.($route->getName() ?? 'unnamed'),
            array_filter(
                Route::getRoutes()->getRoutes(),
                fn ($route): bool => str_contains($route->uri(), 'flash-sale')
            )
        );
        $registeredFlashRouteNames = array_map(
            fn ($route): ?string => $route->getName(),
            Route::getRoutes()->getRoutes()
        );
        $this->assertContains('admin.flash-sales.index', $registeredFlashRouteNames, 'Flash Sale admin route was not registered: '.implode(', ', $registeredFlashRoutes));
        $this->assertContains('admin.flash-sales.store', $registeredFlashRouteNames, 'Flash Sale store route was not registered: '.implode(', ', $registeredFlashRoutes));
        $this->assertContains('admin.flash-sales.update', $registeredFlashRouteNames, 'Flash Sale update route was not registered: '.implode(', ', $registeredFlashRoutes));
        $this->assertContains('admin.flash-sales.destroy', $registeredFlashRouteNames, 'Flash Sale destroy route was not registered: '.implode(', ', $registeredFlashRoutes));
        $this->assertContains('flash-sales.public', $registeredFlashRouteNames, 'Flash Sale public route was not registered: '.implode(', ', $registeredFlashRoutes));
        $registeredRoutes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());
        foreach (['admin.flash-sales.index', 'admin.flash-sales.store', 'admin.flash-sales.update', 'admin.flash-sales.destroy'] as $routeName) {
            $route = $registeredRoutes->get($routeName);
            $this->assertNotNull($route, "Missing registered route {$routeName}.");
            $this->assertContains('plugin.active:aec.flash-sale', $route->middleware());
            $this->assertContains('auth', $route->middleware());
            $this->assertContains('verified', $route->middleware());
            $this->assertContains('role:admin', $route->middleware());
            $this->assertContains('plugin.permission:aec.flash-sale,flash-sale.manage', $route->middleware());
        }

        $publicRoute = $registeredRoutes->get('flash-sales.public');
        $this->assertNotNull($publicRoute);
        $this->assertContains('plugin.active:aec.flash-sale', $publicRoute->middleware());
        $this->assertNotContains('auth', $publicRoute->middleware());

    }

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

    public function test_flash_sale_admin_and_public_pages_render_bangla_translations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withSession(['locale' => 'bn'])->actingAs($admin)->get(route('admin.flash-sales.index'))
            ->assertOk()
            ->assertSee('ফ্ল্যাশ সেল ব্যবস্থাপনা')
            ->assertSee('ক্যাম্পেইন তৈরি করুন')
            ->assertSee('ক্যাম্পেইনসমূহ');

        $this->get(route('flash-sales.public'))
            ->assertOk()
            ->assertSee('সীমিত সময়ের অফার')
            ->assertSee('এই মুহূর্তে কোনো সক্রিয় অফার নেই')
            ->assertSee('সব কোর্স দেখুন');
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

    public function test_flash_sale_fails_closed_when_configured_trusted_key_is_empty(): void
    {
        $course = $this->makeCourse(1000);
        $sale = $this->makeSale($course, ['discount_value' => 25]);

        config(['plugins.trusted_public_keys' => ['flash-sale-test-key' => '']]);

        $this->assertFalse(FlashSale::isPluginActive());
        $this->assertNull(FlashSale::activeForCourse($course->id));
        $this->assertCount(0, FlashSale::activeCampaigns());
        $this->assertSame(1000.0, $sale->discountedPrice(1000));
    }

    public function test_deactivating_flash_sale_plugin_disables_routes_and_all_discount_pricing(): void
    {
        $course = $this->makeCourse();
        $this->makeSale($course);

        $this->assertNotNull(FlashSale::activeForCourse($course->id));
        $this->assertNotEmpty(FlashSale::activeCampaigns());

        DB::table('plugin_registry')->where('plugin_key', 'aec.flash-sale')->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        $this->assertNull(FlashSale::activeForCourse($course->id));
        $this->assertCount(0, FlashSale::activeCampaigns());
        $this->get('/flash-sale')->assertNotFound();
    }

    public function test_admin_can_disable_public_flash_sale_page_without_deleting_campaigns(): void
    {
        $course = $this->makeCourse();
        $sale = $this->makeSale($course);

        $this->get(route('flash-sales.public'))->assertOk()->assertSee('November Mega Sale');

        Setting::setValue('flash_sale_page_enabled', '0');
        $this->get(route('flash-sales.public'))->assertNotFound();
        $this->get('/')->assertOk()
            ->assertDontSee(route('flash-sales.public'))
            ->assertDontSee('November Mega Sale');

        $this->assertDatabaseHas('flash_sales', ['id' => $sale->id, 'status' => 'active']);
        $this->assertDatabaseHas('flash_sale_course', ['flash_sale_id' => $sale->id, 'course_id' => $course->id]);
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

    public function test_campaign_with_rejected_payment_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create(['role' => 'student']);
        $course = $this->makeCourse();
        $sale = $this->makeSale($course);

        Payment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'flash_sale_id' => $sale->id,
            'method' => 'manual',
            'amount' => '700.00',
            'currency' => 'BDT',
            'transaction_reference' => 'FLASH-SALE-REJECTED-RETENTION-001',
            'status' => 'rejected',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.flash-sales.index'))
            ->delete(route('admin.flash-sales.destroy', $sale))
            ->assertRedirect(route('admin.flash-sales.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('flash_sales', ['id' => $sale->id]);
        $this->assertDatabaseHas('payments', [
            'transaction_reference' => 'FLASH-SALE-REJECTED-RETENTION-001',
            'flash_sale_id' => $sale->id,
            'status' => 'rejected',
        ]);
    }



    private function mobileToken(User $student): string
    {
        $plain = \Illuminate\Support\Str::random(80);
        $device = StudentDevice::create([
            'user_id' => $student->id,
            'device_hash' => hash('sha256', \Illuminate\Support\Str::random(64)),
            'device_name' => 'Flash Sale checkout test device',
            'status' => 'approved',
            'phone_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $student->apiTokens()->create([
            'student_device_id' => $device->id,
            'name' => 'Flash Sale checkout test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }

    public function test_mobile_cart_checkout_uses_a_locked_flash_sale_reservation_and_does_not_oversell(): void
    {
        $course = $this->makeCourse(1000);
        $sale = $this->makeSale($course, ['discount_value' => 30, 'max_redemptions' => 1]);
        Setting::setValue('payment_methods', json_encode([
            'manual' => ['enabled' => true, 'label' => 'Manual', 'account' => '012345', 'instructions' => 'Send payment'],
        ]));

        $firstStudent = User::factory()->create(['role' => 'student']);
        $firstToken = $this->mobileToken($firstStudent);
        $this->withToken($firstToken)->postJson('/api/v1/my/cart', ['course_id' => $course->id])
            ->assertCreated();
        $this->withToken($firstToken)->postJson('/api/v1/my/cart/checkout/manual', [
            'payment_method' => 'manual',
            'transaction_reference' => 'CART-FLASH-SALE-001',
        ])->assertCreated()->assertJsonPath('data.amount', '700.00');

        $firstPayment = Payment::where('transaction_reference', 'CART-FLASH-SALE-001')->firstOrFail();
        $this->assertSame($sale->id, $firstPayment->flash_sale_id);

        $this->withToken($firstToken)->postJson('/api/v1/my/cart', ['course_id' => $course->id])
            ->assertCreated();
        $this->withToken($firstToken)->postJson('/api/v1/my/cart/checkout/manual', [
            'payment_method' => 'manual',
            'transaction_reference' => 'CART-FLASH-SALE-DUPLICATE',
        ])->assertStatus(409);
        $this->assertDatabaseMissing('payments', ['transaction_reference' => 'CART-FLASH-SALE-DUPLICATE']);

        $secondStudent = User::factory()->create(['role' => 'student']);
        $secondToken = $this->mobileToken($secondStudent);
        $this->withToken($secondToken)->postJson('/api/v1/my/cart', ['course_id' => $course->id])
            ->assertCreated();
        $this->withToken($secondToken)->postJson('/api/v1/my/cart/checkout/manual', [
            'payment_method' => 'manual',
            'transaction_reference' => 'CART-FLASH-SALE-002',
        ])->assertCreated()->assertJsonPath('data.amount', '1000.00');

        $secondPayment = Payment::where('transaction_reference', 'CART-FLASH-SALE-002')->firstOrFail();
        $this->assertNull($secondPayment->flash_sale_id);
        $this->assertSame(1, Payment::where('flash_sale_id', $sale->id)->count());
    }


    public function test_mobile_cart_free_flash_sale_enrollment_is_recorded_against_campaign_limit(): void
    {
        $course = $this->makeCourse(0);
        $sale = $this->makeSale($course, ['max_redemptions' => 1, 'per_student_limit' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $token = $this->mobileToken($student);

        $this->withToken($token)->postJson('/api/v1/my/cart', ['course_id' => $course->id])
            ->assertCreated();
        $this->withToken($token)->postJson('/api/v1/my/cart/checkout/manual', [])
            ->assertCreated()
            ->assertJsonPath('data.status', 'enrolled_free');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'flash_sale_id' => $sale->id,
            'status' => 'active',
        ]);
        $this->assertNull(FlashSale::activeForCourse($course->id, User::factory()->create(['role' => 'student'])->id));
        $this->assertFalse($sale->fresh()->isAvailableFor($student->id));
    }

}
