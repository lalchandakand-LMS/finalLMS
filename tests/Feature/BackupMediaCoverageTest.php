<?php

namespace Tests\Feature;

use App\Http\Controllers\BackupController;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupMediaCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_media_backup_includes_course_images_and_private_lesson_assets(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'image_path' => 'courses/backup-course.png',
        ]);
        $lesson = $course->lessons()->create([
            'title' => 'Protected video lesson',
            'content' => 'Backup coverage fixture.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_path' => 'lesson-video.mp4',
            'thumbnail_path' => 'course-thumbnails/lesson.png',
            'attachment_path' => 'course-media/'.$course->id.'/1/lesson-notes.pdf',
        ]);

        Storage::disk('public')->put($course->image_path, 'course-image-bytes');
        Storage::disk('public')->put($lesson->thumbnail_path, 'lesson-thumbnail-bytes');
        Storage::disk('local')->put('course-videos/'.$lesson->video_path, 'private-video-bytes');
        Storage::disk('local')->put($lesson->attachment_path, 'private-attachment-bytes');
        Storage::disk('local')->put('assignment-submissions/submission.pdf', 'submission-bytes');
        Storage::disk('local')->put('payment-proofs/payment-proof.png', 'payment-proof-bytes');

        app(BackupController::class)->create();

        $backupPath = collect(Storage::disk('local')->files('backups'))->first();
        $this->assertNotNull($backupPath);

        $compressed = Storage::disk('local')->get($backupPath);
        $envelopeJson = gzdecode($compressed);
        $this->assertIsString($envelopeJson);
        $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
        $payloadJson = base64_decode($envelope['payload'], true);
        $this->assertIsString($payloadJson);
        $this->assertSame(
            hash_hmac('sha256', $payloadJson, (string) config('app.key')),
            $envelope['signature']
        );

        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            base64_encode('course-image-bytes'),
            $payload['public_media']['courses/backup-course.png']
        );
        $this->assertSame(
            base64_encode('lesson-thumbnail-bytes'),
            $payload['public_media']['course-thumbnails/lesson.png']
        );
        $this->assertSame(
            base64_encode('private-video-bytes'),
            $payload['private_files']['course-videos/lesson-video.mp4']
        );
        $this->assertSame(
            base64_encode('private-attachment-bytes'),
            $payload['private_files'][$lesson->attachment_path]
        );
        $this->assertSame(
            base64_encode('submission-bytes'),
            $payload['private_files']['assignment-submissions/submission.pdf']
        );
        $this->assertSame(
            base64_encode('payment-proof-bytes'),
            $payload['private_files']['payment-proofs/payment-proof.png']
        );
    }
    public function test_backup_restore_restores_course_images_private_lesson_media_and_payment_proofs(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'image_path' => 'courses/restore-course.png',
        ]);
        $lesson = $course->lessons()->create([
            'title' => 'Restore protected lesson',
            'content' => 'Restore fixture.',
            'duration_minutes' => 10,
            'order' => 1,
            'video_path' => 'restore-video.mp4',
            'thumbnail_path' => 'course-thumbnails/restore-lesson.png',
            'attachment_path' => 'course-media/'.$course->id.'/1/restore-notes.pdf',
        ]);

        Storage::disk('public')->put($course->image_path, 'course-image');
        Storage::disk('public')->put($lesson->thumbnail_path, 'lesson-thumbnail');
        Storage::disk('local')->put('course-videos/'.$lesson->video_path, 'private-video');
        Storage::disk('local')->put($lesson->attachment_path, 'lesson-attachment');
        Storage::disk('local')->put('payment-proofs/restore-proof.png', 'payment-proof');

        app(BackupController::class)->create();
        $backupPath = collect(Storage::disk('local')->files('backups'))->first();
        $this->assertNotNull($backupPath);
        $archiveBytes = Storage::disk('local')->get($backupPath);

        Storage::disk('public')->delete($course->image_path);
        Storage::disk('public')->delete($lesson->thumbnail_path);
        Storage::disk('local')->delete('course-videos/'.$lesson->video_path);
        Storage::disk('local')->delete($lesson->attachment_path);
        Storage::disk('local')->delete('payment-proofs/restore-proof.png');

        $response = $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('restore.json.gz', $archiveBytes),
            'confirmation' => 'RESTORE',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.backups.index'));
        $response->assertSessionHas('success');
        Storage::disk('public')->assertExists($course->image_path);
        Storage::disk('public')->assertExists($lesson->thumbnail_path);
        Storage::disk('local')->assertExists('course-videos/'.$lesson->video_path);
        Storage::disk('local')->assertExists($lesson->attachment_path);
        Storage::disk('local')->assertExists('payment-proofs/restore-proof.png');
        $this->assertSame('private-video', Storage::disk('local')->get('course-videos/'.$lesson->video_path));
    }

}
