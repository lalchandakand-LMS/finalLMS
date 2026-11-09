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
use Tests\TestCase;

class StoreCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_checkout_and_admin_verification_grants_course_access(): void
    {
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
            'payment_method'=>'manual','payment_reference'=>'ORDER-REF-001','coupon_code'=>'welcome10',
        ])->assertRedirect(route('store.orders'));

        $order = Order::where('payment_reference','ORDER-REF-001')->firstOrFail();
        $this->assertSame('pending',$order->status);
        $this->assertSame('250.00',$order->discount_total);
        $this->assertSame('2250.00',$order->total);
        $this->assertDatabaseHas('order_items',['order_id'=>$order->id,'product_id'=>$product->id]);

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
}
