<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_course_thumbnail(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Spoken English',
            'description' => 'A practical speaking course.',
            'teacher_id' => $teacher->id,
            'price' => 3000,
            'status' => 'published',
            'image' => UploadedFile::fake()->image('spoken-english.webp'),
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::where('slug', 'spoken-english')->firstOrFail();
        $this->assertNotEmpty($course->image_path);
        Storage::disk('public')->assertExists($course->image_path);
    }

    public function test_admin_can_replace_a_course_thumbnail_and_old_file_is_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        Storage::disk('public')->put('courses/old-thumbnail.jpg', 'old image');
        $course = Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Spoken English',
            'slug' => 'spoken-english',
            'description' => 'A practical speaking course.',
            'price' => 3000,
            'status' => 'published',
            'image_path' => 'courses/old-thumbnail.jpg',
        ]);

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'title' => 'Spoken English Updated',
            'description' => 'Updated course description.',
            'teacher_id' => $teacher->id,
            'price' => 3500,
            'status' => 'published',
            'image' => UploadedFile::fake()->image('new-thumbnail.png'),
        ])->assertRedirect(route('admin.courses.index'));

        $course->refresh();
        $this->assertNotSame('courses/old-thumbnail.jpg', $course->image_path);
        Storage::disk('public')->assertMissing('courses/old-thumbnail.jpg');
        Storage::disk('public')->assertExists($course->image_path);
    }

    public function test_course_thumbnail_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Invalid upload',
            'description' => 'This should not be saved.',
            'teacher_id' => $teacher->id,
            'price' => 0,
            'status' => 'draft',
            'image' => UploadedFile::fake()->create('script.php', 10, 'text/plain'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('courses', ['slug' => 'invalid-upload']);
    }
}
