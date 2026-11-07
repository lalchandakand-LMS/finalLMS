<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_download_a_private_backup(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.backups.create'))->assertRedirect(route('admin.backups.index'));

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.json.gz', $files[0]);

        $this->actingAs($admin)->get(route('admin.backups.download', basename($files[0])))->assertOk();
    }

    public function test_restore_requires_admin_password_and_explicit_confirmation(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.backups.create'));
        $path = Storage::disk('local')->files('backups')[0];
        $archive = Storage::disk('local')->get($path);

        $this->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('backup.json.gz', $archive),
            'confirmation' => 'RESTORE',
            'password' => 'incorrect-password',
        ])->assertForbidden();

        $this->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('backup.json.gz', $archive),
            'confirmation' => 'NO',
            'password' => 'password',
        ])->assertSessionHasErrors('confirmation');
    }


    public function test_valid_backup_restores_database_rows_after_password_confirmation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.backups.create'))->assertRedirect();
        $path = Storage::disk('local')->files('backups')[0];
        $archive = Storage::disk('local')->get($path);

        $temporary = User::factory()->create(['role' => 'student', 'email' => 'temporary@example.test']);
        $this->assertDatabaseHas('users', ['id' => $temporary->id]);

        $this->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('backup.json.gz', $archive),
            'confirmation' => 'RESTORE',
            'password' => 'password',
        ])->assertRedirect(route('admin.backups.index'));

        $this->assertDatabaseMissing('users', ['email' => 'temporary@example.test']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => $admin->email]);
    }

    public function test_non_admin_cannot_open_backup_management(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.backups.index'))->assertForbidden();
    }

    public function test_restore_rejects_a_tampered_backup_without_changing_database_rows(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.backups.create'))->assertRedirect();
        $path = Storage::disk('local')->files('backups')[0];
        $archive = Storage::disk('local')->get($path);

        $envelope = json_decode(gzdecode($archive), true, 512, JSON_THROW_ON_ERROR);
        $payload = base64_decode($envelope['payload'], true);
        $payloadData = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $payloadData['created_at'] = now()->addDay()->toIso8601String();
        $envelope['payload'] = base64_encode(json_encode($payloadData, JSON_THROW_ON_ERROR));
        // Keep the original signature to simulate a modified archive.
        $tamperedArchive = gzencode(json_encode($envelope, JSON_THROW_ON_ERROR), 9);

        $temporary = User::factory()->create(['role' => 'student', 'email' => 'must-remain@example.test']);

        $this->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('tampered.json.gz', $tamperedArchive),
            'confirmation' => 'RESTORE',
            'password' => 'password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', ['email' => 'must-remain@example.test']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_restore_rejects_invalid_gzip_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'backup_file' => UploadedFile::fake()->createWithContent('invalid.json.gz', 'not-a-gzip-file'),
            'confirmation' => 'RESTORE',
            'password' => 'password',
        ])->assertStatus(422);
    }

}
