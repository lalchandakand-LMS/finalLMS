<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English',
            'slug' => 'spoken-english',
            'description' => 'Practical speaking course.',
            'price' => 2500,
            'status' => 'published',
        ]);
    }

    public function test_admin_can_open_product_management_and_create_a_course_product_with_multiple_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->makeCourse();
        $one = ProductCategory::create(['name' => 'English', 'slug' => 'english']);
        $two = ProductCategory::create(['name' => 'Speaking', 'slug' => 'speaking']);

        $this->actingAs($admin)->get(route('admin.products.index'))
            ->assertOk()->assertSee('Product Management')->assertSee('Create product');

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'type' => 'course',
            'course_id' => $course->id,
            'name' => 'Spoken English Premium',
            'sku' => 'SPEAK-001',
            'short_description' => 'Speak confidently.',
            'description' => 'A complete course.',
            'regular_price' => '3000.00',
            'sale_price' => '2500.00',
            'currency' => 'BDT',
            'status' => 'published',
            'category_ids' => [$one->id, $two->id],
        ])->assertRedirect();

        $product = Product::where('sku', 'SPEAK-001')->firstOrFail();
        $this->assertSame('2500.00', $product->currentPrice());
        $this->assertCount(2, $product->categories);
    }

    public function test_non_admin_cannot_access_or_create_products(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.products.store'), [])->assertForbidden();
    }

    public function test_category_deletion_detaches_products_without_deleting_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->makeCourse();
        $category = ProductCategory::create(['name' => 'English', 'slug' => 'english']);
        $product = Product::create([
            'type' => 'course',
            'course_id' => $course->id,
            'name' => 'Course product',
            'slug' => 'course-product',
            'regular_price' => 1000,
            'currency' => 'BDT',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $product->categories()->attach($category);

        $this->actingAs($admin)->delete(route('admin.product-categories.destroy', $category))->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Course product']);
        $this->assertDatabaseMissing('product_category_product', ['product_id' => $product->id, 'product_category_id' => $category->id]);
    }
    public function test_switching_an_unpurchased_ebook_to_a_course_removes_the_retired_private_pdf_after_update(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $course = $this->makeCourse();
        Storage::disk('local')->put('private/products/retired.pdf', 'private ebook');

        $product = Product::create([
            'type' => 'ebook',
            'name' => 'Old Workbook',
            'slug' => 'old-workbook',
            'regular_price' => 500,
            'currency' => 'BDT',
            'is_downloadable' => true,
            'download_path' => 'private/products/retired.pdf',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'type' => 'course',
            'course_id' => $course->id,
            'name' => 'Spoken English Course',
            'regular_price' => '1200.00',
            'currency' => 'BDT',
            'status' => 'draft',
        ])->assertRedirect();

        $product->refresh();
        $this->assertSame('course', $product->type);
        $this->assertNull($product->download_path);
        Storage::disk('local')->assertMissing('private/products/retired.pdf');
    }

}
