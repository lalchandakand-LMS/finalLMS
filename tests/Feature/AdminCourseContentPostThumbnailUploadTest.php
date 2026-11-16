<?php

namespace Tests\Feature;

use App\Models\CourseContentPost;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCourseContentPostThumbnailUploadTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPngUpload(string $name): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGP4DwQACfsD/fteaysAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $bytes ?: '');
    }

    public function test_admin_can_upload_a_thumbnail_when_creating_a_course_content_post(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.course-content-posts.store'), [
            'title' => 'New lesson article',
            'long_description' => 'Lesson content for the learner.',
            'status' => 'draft',
            'thumbnail_file' => $this->tinyPngUpload('lesson-cover.png'),
        ])->assertRedirect(route('admin.course-content-posts.index'))
            ->assertSessionHas('success');

        $post = CourseContentPost::query()->where('slug', 'new-lesson-article')->firstOrFail();
        $this->assertNotNull($post->thumbnail_media_id);

        $media = MediaAsset::query()->findOrFail($post->thumbnail_media_id);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertSame('public', $media->disk);
        $this->assertStringStartsWith('image/', $media->mime_type);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_admin_can_replace_a_post_thumbnail_without_deleting_the_old_media_asset(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $oldPath = 'media/old-cover.png';
        Storage::disk('public')->put($oldPath, 'old image bytes');
        $oldMedia = MediaAsset::query()->create([
            'uploaded_by' => $admin->id,
            'disk' => 'public',
            'path' => $oldPath,
            'original_name' => 'old-cover.png',
            'mime_type' => 'image/png',
            'size' => 15,
        ]);
        $post = CourseContentPost::query()->create([
            'author_id' => $admin->id,
            'thumbnail_media_id' => $oldMedia->id,
            'title' => 'Existing lesson',
            'slug' => 'existing-lesson',
            'long_description' => 'Existing lesson content.',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->put(route('admin.course-content-posts.update', $post), [
            'title' => 'Existing lesson updated',
            'long_description' => 'Updated lesson content.',
            'status' => 'draft',
            'thumbnail_file' => $this->tinyPngUpload('new-cover.png'),
        ])->assertRedirect(route('admin.course-content-posts.index'))
            ->assertSessionHas('success');

        $post->refresh();
        $this->assertNotSame($oldMedia->id, $post->thumbnail_media_id);
        $this->assertDatabaseHas('media_assets', ['id' => $oldMedia->id, 'path' => $oldPath]);
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists($post->thumbnail->path);
    }

    public function test_thumbnail_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->from(route('admin.course-content-posts.create'))
            ->post(route('admin.course-content-posts.store'), [
                'title' => 'Unsafe file post',
                'long_description' => 'Lesson content.',
                'status' => 'draft',
                'thumbnail_file' => UploadedFile::fake()->create('not-an-image.pdf', 200, 'application/pdf'),
            ])->assertSessionHasErrors('thumbnail_file');

        $this->assertDatabaseCount('course_content_posts', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }
}
