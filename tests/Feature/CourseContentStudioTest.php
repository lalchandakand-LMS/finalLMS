<?php

namespace Tests\Feature;

use App\Models\AcademicLevel;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseContentPost;
use App\Models\CourseCurriculumItem;
use App\Models\DownloadPermission;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseContentStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_course_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.course-categories.store'), [
            'name' => 'Spoken English',
            'description' => 'Speaking and pronunciation lessons.',
            'sort_order' => 5,
            'is_visible' => '1',
        ])->assertRedirect(route('admin.course-categories.index'));

        $category = CourseCategory::firstOrFail();
        $this->assertSame('spoken-english', $category->slug);
        $this->assertTrue($category->is_visible);

        $this->actingAs($admin)->put(route('admin.course-categories.update', $category), [
            'name' => 'English Speaking',
            'description' => 'Updated category.',
            'sort_order' => 2,
        ])->assertRedirect(route('admin.course-categories.index'));

        $this->assertDatabaseHas('course_categories', [
            'id' => $category->id,
            'name' => 'English Speaking',
            'is_visible' => 0,
        ]);

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category->fresh()))
            ->assertRedirect();
        $this->assertDatabaseMissing('course_categories', ['id' => $category->id]);
    }

    public function test_category_cannot_be_deleted_while_content_posts_use_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar']);
        $post = $this->makePost($admin, $category, 'Grammar foundations');

        $this->actingAs($admin)->delete(route('admin.course-categories.destroy', $category))
            ->assertStatus(422);

        $this->assertDatabaseHas('course_categories', ['id' => $category->id]);
        $this->assertTrue($post->fresh()->categories->contains($category->id));
    }

    public function test_admin_can_create_content_posts_with_multiple_categories_tags_and_safe_video_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $grammar = CourseCategory::create(['name' => 'Grammar', 'slug' => 'grammar']);
        $speaking = CourseCategory::create(['name' => 'Speaking', 'slug' => 'speaking']);

        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'Present Perfect',
            'short_description' => 'A practical tense lesson.',
            'long_description' => 'Learn how to use the present perfect tense.',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'tags' => 'grammar, speaking, grammar',
            'status' => 'published',
            'category_ids' => [$grammar->id, $speaking->id],
        ])->assertRedirect(route('admin.course-content-posts.index'));

        $post = CourseContentPost::firstOrFail();
        $this->assertSame('present-perfect', $post->slug);
        $this->assertSame(['grammar', 'speaking'], $post->tags);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $post->video_embed_url);
        $this->assertSame(2, $post->categories()->count());
        $this->assertNotNull($post->published_at);
    }

    public function test_content_post_rejects_unsupported_video_hosts_and_http_urls(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->from(route('admin.course-content-posts.create'))
            ->post(route('admin.course-content-posts.store'), [
                'title' => 'Unsafe embed',
                'long_description' => 'Test content',
                'video_url' => 'https://example.com/video/123',
                'status' => 'draft',
            ])->assertSessionHasErrors('video_url');

        $this->actingAs($admin)->from(route('admin.course-content-posts.create'))
            ->post(route('admin.course-content-posts.store'), [
                'title' => 'Insecure embed',
                'long_description' => 'Test content',
                'video_url' => 'http://youtu.be/dQw4w9WgXcQ',
                'status' => 'draft',
            ])->assertSessionHasErrors('video_url');

        $this->assertDatabaseCount('course_content_posts', 0);
    }

    public function test_admin_can_assemble_and_reorder_published_posts_in_course_curriculum(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $first = $this->makePost($admin, null, 'First lesson');
        $second = $this->makePost($admin, null, 'Second lesson');

        $this->actingAs($admin)->put(route('admin.course-builder.update', $course), [
            'post_ids' => [$first->id, $second->id],
            'section_titles' => [$first->id => 'Module A', $second->id => 'Module A'],
            'positions' => [$first->id => 20, $second->id => 10],
            'previews' => [$first->id => '1'],
            'durations' => [$first->id => 15, $second->id => 25],
        ])->assertRedirect(route('admin.course-builder.edit', $course));

        $this->assertSame(
            [$second->id, $first->id],
            $course->curriculumItems()->orderBy('position')->pluck('course_content_post_id')->all()
        );
        $this->assertDatabaseHas('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $first->id,
            'section_title' => 'Module A',
            'is_preview' => 1,
            'duration_minutes' => 15,
        ]);

        $this->actingAs($admin)->put(route('admin.course-builder.update', $course), [
            'post_ids' => [$second->id],
            'positions' => [$second->id => 5],
        ])->assertRedirect();

        $this->assertDatabaseMissing('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $first->id,
        ]);
        $this->assertSame(1, $course->curriculumItems()->count());
    }

    public function test_preview_content_is_public_but_locked_curriculum_requires_enrollment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $preview = $this->makePost($admin, null, 'Free preview');
        $locked = $this->makePost($admin, null, 'Members only lesson');

        CourseCurriculumItem::create([
            'course_id' => $course->id, 'course_content_post_id' => $preview->id,
            'position' => 10, 'is_preview' => true,
        ]);
        CourseCurriculumItem::create([
            'course_id' => $course->id, 'course_content_post_id' => $locked->id,
            'position' => 20, 'is_preview' => false,
        ]);

        $this->get(route('courses.content.show', [$course, $preview]))->assertOk()->assertSee('Free preview');
        $this->get(route('courses.content.show', [$course, $locked]))
            ->assertRedirect(route('login', ['continue' => route('courses.show', $course, false)]));

        $this->get(route('login', ['continue' => route('courses.show', $course, false)]))
            ->assertOk()
            ->assertSessionHas('url.intended', route('courses.show', $course, false));

        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'status' => 'active']);
        $this->actingAs($student)->get(route('courses.content.show', [$course, $locked]))
            ->assertOk()->assertSee('Members only lesson');
    }

    public function test_non_admin_cannot_access_content_studio_or_course_builder(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($student)->get(route('admin.course-categories.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.course-content-posts.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.course-builder.edit', $course))->assertForbidden();
    }

    public function test_course_builder_rejects_scheduled_posts_until_their_publish_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $scheduled = CourseContentPost::create([
            'author_id' => $admin->id,
            'title' => 'Scheduled lesson',
            'slug' => 'scheduled-lesson',
            'long_description' => 'This lesson is not live yet.',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.course-builder.edit', $course))
            ->put(route('admin.course-builder.update', $course), [
                'post_ids' => [$scheduled->id],
                'positions' => [$scheduled->id => 10],
            ])
            ->assertRedirect(route('admin.course-builder.edit', $course))
            ->assertSessionHasErrors('post_ids.0');

        $this->assertDatabaseMissing('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $scheduled->id,
        ]);
    }

    public function test_content_studio_uploads_thumbnail_and_private_pdf_and_creates_paid_store_product(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $level = AcademicLevel::create(['name' => 'Class 9', 'slug' => 'class-9', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'Class 9 Physics PDF',
            'long_description' => 'Download the full notes after purchase.',
            'access_type' => 'paid',
            'regular_price' => '500.00',
            'sale_price' => '350.00',
            'academic_level_id' => $level->id,
            'status' => 'published',
            'thumbnail' => UploadedFile::fake()->create('physics-cover.jpg', 30, 'image/jpeg'),
            'content_file' => UploadedFile::fake()->create('physics-notes.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('admin.course-content-posts.index'));

        $post = CourseContentPost::where('slug', 'class-9-physics-pdf')->firstOrFail();
        $product = Product::findOrFail($post->product_id);
        $this->assertSame('paid', $post->access_type);
        $this->assertSame($level->id, $post->academic_level_id);
        $this->assertSame('350.00', $product->sale_price);
        $this->assertSame('published', $product->status);
        Storage::disk('public')->assertExists($post->thumbnail_path);
        Storage::disk('local')->assertExists($post->content_file_path);
        $this->assertSame($post->content_file_path, $product->download_path);
        $this->assertStringStartsWith('private/course-content/', $post->content_file_path);
    }

    public function test_content_studio_updates_paid_pdf_price_and_thumbnail_without_replacing_purchased_pdf(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'Paid Algebra Notes',
            'long_description' => 'Algebra PDF',
            'access_type' => 'paid',
            'regular_price' => '400',
            'status' => 'published',
            'thumbnail' => UploadedFile::fake()->create('algebra.jpg', 20, 'image/jpeg'),
            'content_file' => UploadedFile::fake()->create('algebra.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $post = CourseContentPost::where('slug', 'paid-algebra-notes')->firstOrFail();
        $product = Product::findOrFail($post->product_id);
        $student = User::factory()->create(['role' => 'student']);
        $order = Order::create([
            'order_number' => 'AEC-CONTENT-ORDER-001',
            'user_id' => $student->id,
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_reference' => 'CONTENT-PAY-001',
            'currency' => 'BDT',
            'subtotal' => 400,
            'discount_total' => 0,
            'total' => 400,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_type' => 'ebook',
            'product_name' => $product->name,
            'quantity' => 1,
            'regular_price' => 400,
            'line_total' => 400,
        ]);
        DownloadPermission::create([
            'order_item_id' => $item->id,
            'user_id' => $student->id,
            'product_id' => $product->id,
            'downloads_remaining' => 2,
        ]);

        $oldPdf = $post->content_file_path;
        $this->actingAs($admin)->put(route('admin.course-content-posts.update', $post), [
            'title' => $post->title,
            'long_description' => $post->long_description,
            'access_type' => 'paid',
            'regular_price' => '450',
            'sale_price' => '375',
            'status' => 'published',
            'thumbnail' => UploadedFile::fake()->create('algebra-new.jpg', 25, 'image/jpeg'),
        ])->assertRedirect(route('admin.course-content-posts.index'));

        $this->assertSame('450.00', $product->fresh()->regular_price);
        $this->assertSame('375.00', $product->fresh()->sale_price);
        $this->assertSame($oldPdf, $post->fresh()->content_file_path);
        Storage::disk('local')->assertExists($oldPdf);

        $this->actingAs($admin)->put(route('admin.course-content-posts.update', $post->fresh()), [
            'title' => $post->title,
            'long_description' => $post->long_description,
            'access_type' => 'paid',
            'regular_price' => '450',
            'status' => 'published',
            'content_file' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_paid_course_content_requires_purchase_and_download_uses_existing_permission_limits(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'Paid Biology Notes',
            'long_description' => 'Biology notes content.',
            'access_type' => 'paid',
            'regular_price' => '200',
            'status' => 'published',
            'content_file' => UploadedFile::fake()->create('biology.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $post = CourseContentPost::where('slug', 'paid-biology-notes')->firstOrFail();
        CourseCurriculumItem::create([
            'course_id' => $course->id,
            'course_content_post_id' => $post->id,
            'position' => 1,
            'is_preview' => false,
        ]);

        $this->get(route('courses.content.show', [$course, $post]))
            ->assertRedirect(route('login', ['continue' => route('ebooks.show', $post->product->slug, false)]));

        $order = Order::create([
            'order_number' => 'AEC-CONTENT-ORDER-002',
            'user_id' => $student->id,
            'status' => 'completed',
            'payment_method' => 'manual',
            'payment_reference' => 'CONTENT-PAY-002',
            'currency' => 'BDT',
            'subtotal' => 200,
            'discount_total' => 0,
            'total' => 200,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
        $item = $order->items()->create([
            'product_id' => $post->product_id,
            'product_type' => 'ebook',
            'product_name' => $post->title,
            'quantity' => 1,
            'regular_price' => 200,
            'line_total' => 200,
        ]);
        $permission = DownloadPermission::create([
            'order_item_id' => $item->id,
            'user_id' => $student->id,
            'product_id' => $post->product_id,
            'downloads_remaining' => 1,
        ]);

        $this->actingAs($student)->get(route('courses.content.show', [$course, $post]))
            ->assertOk()->assertSee('Biology notes content.');
        $this->actingAs($student)->get(route('courses.content.file', [$course, $post]))->assertOk();
        $this->assertDatabaseHas('download_permissions', [
            'id' => $permission->id,
            'downloads_remaining' => 0,
            'download_count' => 1,
        ]);
        $this->actingAs($student)->get(route('courses.content.file', [$course, $post]))->assertGone();
    }

    public function test_course_builder_filters_new_content_to_the_courses_academic_level(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classNine = AcademicLevel::create(['name' => 'Class 9', 'slug' => 'class-9', 'sort_order' => 1, 'is_active' => true]);
        $classTen = AcademicLevel::create(['name' => 'Class 10', 'slug' => 'class-10', 'sort_order' => 2, 'is_active' => true]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'academic_level_id' => $classNine->id,
            'status' => 'published',
        ]);
        $matching = $this->makePost($admin, null, 'Class 9 Algebra');
        $matching->update(['academic_level_id' => $classNine->id]);
        $unassigned = $this->makePost($admin, null, 'General Study Skills');
        $otherLevel = $this->makePost($admin, null, 'Class 10 Chemistry');
        $otherLevel->update(['academic_level_id' => $classTen->id]);

        $this->actingAs($admin)->get(route('admin.course-builder.edit', $course))
            ->assertOk()->assertSee('Class 9 Algebra')->assertSee('General Study Skills')
            ->assertDontSee('Class 10 Chemistry');

        $this->actingAs($admin)->from(route('admin.course-builder.edit', $course))
            ->put(route('admin.course-builder.update', $course), [
                'post_ids' => [$otherLevel->id],
                'positions' => [$otherLevel->id => 10],
            ])
            ->assertRedirect(route('admin.course-builder.edit', $course))
            ->assertSessionHasErrors('post_ids.0');

        $this->assertDatabaseMissing('course_curriculum_items', [
            'course_id' => $course->id,
            'course_content_post_id' => $otherLevel->id,
        ]);
    }

    private function makePost(User $author, ?CourseCategory $category, string $title): CourseContentPost
    {
        $post = CourseContentPost::create([
            'author_id' => $author->id,
            'title' => $title,
            'slug' => str()->slug($title),
            'short_description' => 'Short description',
            'long_description' => 'Lesson content',
            'status' => 'published',
            'published_at' => now(),
            'tags' => ['test'],
        ]);

        if ($category) {
            $post->categories()->attach($category->id);
        }

        return $post;
    }
}
