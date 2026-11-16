<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupRestoreRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BackupRestoreRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_is_not_started_when_pre_restore_snapshot_cannot_be_published(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'admin']);
        $localDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');
        $failingLocalDisk = Mockery::mock($localDisk)->makePartial();
        $failingLocalDisk->shouldReceive('put')->andReturn(false);

        Storage::shouldReceive('disk')->with('local')->andReturn($failingLocalDisk);
        Storage::shouldReceive('disk')->with('public')->andReturn($publicDisk);

        try {
            app(BackupRestoreRecovery::class)->applyWithRecovery(['users'], [
                'tables' => ['users' => []],
                'public_media' => [],
                'private_files' => [],
            ]);
            $this->fail('Restore must stop if the pre-restore safety snapshot cannot be persisted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('pre-restore safety snapshot could not be written', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_failed_media_restore_compensates_database_and_files_from_retained_safety_snapshot(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'admin']);
        $publicDisk = Storage::disk('public');
        $localDisk = Storage::disk('local');
        $publicDisk->put('existing-before-restore.txt', 'original-bytes');

        $failingPublicDisk = Mockery::mock($publicDisk)->makePartial();
        $failedTargetWrite = false;
        $failingPublicDisk->shouldReceive('put')->andReturnUsing(
            function (string $path, string $contents) use ($publicDisk, &$failedTargetWrite): bool {
                if ($path === 'target-added-by-restore.txt' && ! $failedTargetWrite) {
                    $failedTargetWrite = true;
                    return false;
                }

                return $publicDisk->put($path, $contents);
            }
        );

        Storage::shouldReceive('disk')->with('public')->andReturn($failingPublicDisk);
        Storage::shouldReceive('disk')->with('local')->andReturn($localDisk);

        $targetPayload = [
            'tables' => ['users' => []],
            'public_media' => [
                'target-added-by-restore.txt' => base64_encode('target-bytes'),
            ],
            'private_files' => [],
        ];

        try {
            app(BackupRestoreRecovery::class)->applyWithRecovery(['users'], $targetPayload);
            $this->fail('The simulated media write failure should abort the restore.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('previous database and media state was automatically restored', $exception->getMessage());
            $this->assertStringContainsString('backups/aloms-lms-backup-', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $publicDisk->assertExists('existing-before-restore.txt');
        $this->assertSame('original-bytes', $publicDisk->get('existing-before-restore.txt'));
        $publicDisk->assertMissing('target-added-by-restore.txt');

        $snapshots = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_ends_with($path, '.json.gz'));
        $this->assertCount(1, $snapshots, 'The verified recovery snapshot must remain available after compensation.');
    }
}
