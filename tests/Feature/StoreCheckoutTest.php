<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StoreCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_checkout_and_admin_verification_grants_course_access(): void
    {
        Storage::fake('local');
        $student = User::factory()->create(['role'=>'student']);
        $admin = User::factory()->create(['role'=>'admin']);
        $teacher = User::factory()->create(['role'=>'teacher']);
        $course = Course::create([
            'teacher_id'=>$teacher->id,'title'=>'Spoken English','slug'=>'spoken-english',
            'description'=>'Learn speaking','price'=>2500,'status'=>'published',
        ]);
        $product = Product::create([
            'type'=>'course','course_id'=>$course->id,'name'=>'Spoken English Course',
            'slug'=>'spoken-english-product','regular_price'=>2500,'currency'=>'BDT',
            'status'=>'published','published_at'=>now(),'created_by'=>$admin->id,
        ]);
        $coupon = Coupon::create(['code'=>'WELCOME10','discount_type'=>'percentage','discount_value'=>10,'minimum_order'=>1000,'per_user_limit'=>1,'is_active'=>true]);
        Setting::setValue('payment_methods', json_encode([
            'manual'=>['enabled'=>true,'label'=>'Manual','account'=>'012345'],
        ]));

        $this->actingAs($student)->post(route('store.cart.add',$product))->assertRedirect();
        $this->actingAs($student)->post(route('store.checkout'), [
            'payment_method'=>'manual','payment_reference'=>'ORDER-REF-001','payment_sender'=>'01700000000','payment_proof'=>UploadedFile::fake()->create('receipt.pdf',20,'application/pdf'),'coupon_code'=>'welcome10',
        ])->assertRedirect(route('store.orders'));

        $order = Order::where('payment_reference','ORDER-REF-001')->firstOrFail();
        $this->assertSame('pending',$order->status);
        $this->assertSame('01700000000',$order->payment_sender);
        Storage::disk('local')->assertExists($order->payment_proof_path);
        $this->assertSame('250.00',$order->discount_total);
        $this->assertSame('2250.00',$order->total);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'product_id'=>$product->id]);

        $this->actingAs($admin)->get(route('admin.orders.proof',$order))->assertOk();
        $this->actingAs($admin)->post(route('admin.orders.approve',$order))->assertRedirect();
        $this->assertDatabaseHas('orders',['id'=>$order->id,'status'=>'completed']);
        $this->assertDatabaseHas('enrollments',['user_id'=>$student->id,'course_id'=>$course->id,'status'=>'active']);
        $this->assertDatabaseHas('coupons',['id'=>$coupon->id,'usage_count'=>1]);
        $this->assertDatabaseHas('coupon_redemptions',['coupon_id'=>$coupon->id,'order_id'=>$order->id,'user_id'=>$student->id]);
    }

    public function test_ebook_checkout_creates_a_private_download_permission_after_admin_approval(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/products/guide.pdf','private pdf bytes');
        $student = User::factory()->create(['role'=>'student']);
        $admin = User::factory()->create(['role'=>'admin']);
        $product = Product::create([
            'type'=>'ebook','name'=>'Grammar Guide','slug'=>'grammar-guide',
            'regular_price'=>350,'currency'=>'BDT','status'=>'published','published_at'=>now(),
            'is_virtual'=>true,'is_downloadable'=>true,'download_path'=>'private/products/guide.pdf',
            'download_limit'=>3,'created_by'=>$admin->id,
        ]);
        Setting::setValue('payment_methods', json_encode([
            'manual'=>['enabled'=>true,'label'=>'Manual'],
        ]));

        $this->actingAs($student)->post(route('store.cart.add',$product));
        $this->actingAs($student)->post(route('store.checkout'), [
            'payment_method'=>'manual','payment_reference'=>'EBOOK-REF-001',
        ])->assertRedirect(route('store.orders'));

        $order = Order::where('payment_reference','EBOOK-REF-001')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.orders.approve',$order))->assertRedirect();
        $permission = DownloadPermission::where('user_id',$student->id)->firstOrFail();
        $this->assertSame(3,$permission->downloads_remaining);

        $this->actingAs($student)->get(route('store.download',$permission))->assertOk();
        $this->assertDatabaseHas('download_permissions',['id'=>$permission->id,'downloads_remaining'=>2,'download_count'=>1]);
    }

    public function test_coupon_restrictions_discount_only_matching_product_categories(): void
    {
        $student = User::factory()->create(['role'=>'student']);
        $admin = User::factory()->create(['role'=>'admin']);
        $category = \App\Models\ProductCategory::create(['name'=>'Exam eBooks','slug'=>'exam-ebooks']);
        $eligible = Product::create([
            'type'=>'ebook','name'=>'IELTS Guide','slug'=>'ielts-guide','regular_price'=>1000,
            'currency'=>'BDT','status'=>'published','published_at'=>now(),'is_downloadable'=>true,
            'download_path'=>'private/products/ielts.pdf','created_by'=>$admin->id,
        ]);
        $excluded = Product::create([
            'type'=>'ebook','name'=>'Grammar Guide','slug'=>'grammar-guide-other','regular_price'=>500,
            'currency'=>'BDT','status'=>'published','published_at'=>now(),'is_downloadable'=>true,
            'download_path'=>'private/products/grammar-other.pdf','created_by'=>$admin->id,
        ]);
        $eligible->categories()->attach($category);
        $coupon = Coupon::create([
            'code'=>'IELTS10','discount_type'=>'percentage','discount_value'=>10,
            'minimum_order'=>0,'per_user_limit'=>1,'is_active'=>true,
        ]);
        $coupon->categories()->attach($category);
        Setting::setValue('payment_methods', json_encode([
            'manual'=>['enabled'=>true,'label'=>'Manual'],
        ]));

        $this->actingAs($student)->post(route('store.cart.add',$eligible))->assertRedirect();
        $this->actingAs($student)->post(route('store.cart.add',$excluded))->assertRedirect();
        $this->actingAs($student)->post(route('store.checkout'), [
            'payment_method'=>'manual','payment_reference'=>'CATEGORY-COUPON-001','coupon_code'=>'IELTS10',
        ])->assertRedirect(route('store.orders'));

        $order = Order::where('payment_reference','CATEGORY-COUPON-001')->firstOrFail();
        $this->assertSame('100.00',$order->discount_total);
        $this->assertSame('1400.00',$order->total);
    }


    public function test_store_order_snapshots_flash_sale_and_reserves_campaign_limit(): void
    {
        $admin = User::factory()->create(['role'=>'admin']);
        $category = \App\Models\ProductCategory::create(['name'=>'Limited Offer Books','slug'=>'limited-offer-books']);
        $product = Product::create([
            'type'=>'ebook','name'=>'Limited English Guide','slug'=>'limited-english-guide',
            'regular_price'=>1000,'currency'=>'BDT','status'=>'published','published_at'=>now(),
            'is_downloadable'=>true,'download_path'=>'private/products/limited.pdf','created_by'=>$admin->id,
        ]);
        $product->categories()->attach($category);
        $sale = \App\Models\FlashSale::create([
            'title'=>'One Order Only','badge'=>'50% OFF','discount_type'=>'percentage','discount_value'=>50,
            'starts_at'=>now()->subMinute(),'ends_at'=>now()->addDay(),'status'=>'active',
            'max_redemptions'=>1,'per_student_limit'=>1,'priority'=>50,'created_by'=>$admin->id,
        ]);
        $sale->categories()->attach($category);
        Setting::setValue('payment_methods', json_encode(['manual'=>['enabled'=>true,'label'=>'Manual']]));

        $firstStudent = User::factory()->create(['role'=>'student']);
        $this->actingAs($firstStudent)->post(route('store.cart.add',$product));
        $this->actingAs($firstStudent)->post(route('store.checkout'), [
            'payment_method'=>'manual','payment_reference'=>'FLASH-STORE-001',
        ])->assertRedirect(route('store.orders'));
        $firstOrder = Order::where('payment_reference','FLASH-STORE-001')->firstOrFail();
        $this->assertDatabaseHas('order_items', [
            'order_id'=>$firstOrder->id,'flash_sale_id'=>$sale->id,'discount_total'=>'500.00','line_total'=>'500.00',
        ]);

        $secondStudent = User::factory()->create(['role'=>'student']);
        $this->actingAs($secondStudent)->post(route('store.cart.add',$product));
        $this->actingAs($secondStudent)->post(route('store.checkout'), [
            'payment_method'=>'manual','payment_reference'=>'FLASH-STORE-002',
        ])->assertRedirect(route('store.orders'));
        $secondOrder = Order::where('payment_reference','FLASH-STORE-002')->firstOrFail();
        $this->assertSame('1000.00',$secondOrder->total);
        $this->assertDatabaseHas('order_items', [
            'order_id'=>$secondOrder->id,'flash_sale_id'=>null,'line_total'=>'1000.00',
        ]);
    }

}
