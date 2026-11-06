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

    public function test_non_admin_cannot_open_backup_management(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->actingAs($student)->get(route('admin.backups.index'))->assertForbidden();
    }
}
