<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupRestoreRecovery
{
    /**
     * Capture the complete pre-restore database and local media state in memory.
     * The caller must persist this payload before applying the requested restore.
     */
    public function capture(array $tables): array
    {
        $database = [];
        foreach ($tables as $table) {
            $database[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        $publicFiles = [];
        foreach (Storage::disk('public')->allFiles() as $path) {
            $publicFiles[$path] = base64_encode(Storage::disk('public')->get($path));
        }

        $privateFiles = [];
        foreach (Storage::disk('local')->allFiles() as $path) {
            if (str_starts_with($path, 'backups/') || str_starts_with($path, 'updates/')) {
                continue;
            }
            $privateFiles[$path] = base64_encode(Storage::disk('local')->get($path));
        }

        return [
            'format_version' => 1,
            'snapshot_type' => 'pre_restore_safety',
            'created_at' => now()->toIso8601String(),
            'tables' => $database,
            'public_media' => $publicFiles,
            'private_files' => $privateFiles,
        ];
    }

    /**
     * Persist a signed, verified recovery point before any destructive restore work.
     * The snapshot is intentionally retained after success or failure.
     */
    public function publishSafetySnapshot(array $payload): string
    {
        $disk = Storage::disk('local');
        $filename = 'aloms-lms-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(5)).'.json.gz';
        $finalPath = 'backups/'.$filename;
        $temporaryPath = 'backups/.'.$filename.'.'.Str::lower(Str::random(10)).'.tmp';
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        $archive = gzencode(json_encode([
            'payload' => base64_encode($payloadJson),
            'signature' => hash_hmac('sha256', $payloadJson, (string) config('app.key')),
        ], JSON_THROW_ON_ERROR), 9);

        if (! is_string($archive) || $archive === '') {
            throw new RuntimeException('The pre-restore safety snapshot could not be compressed.');
        }

        $published = false;
        try {
            if (! $disk->put($temporaryPath, $archive)) {
                throw new RuntimeException('The pre-restore safety snapshot could not be written.');
            }

            $stored = $disk->get($temporaryPath);
            $envelopeJson = is_string($stored) ? @gzdecode($stored, 536870912) : false;
            if (! is_string($envelopeJson)) {
                throw new RuntimeException('The pre-restore safety snapshot failed compression verification.');
            }

            $envelope = json_decode($envelopeJson, true, 512, JSON_THROW_ON_ERROR);
            $storedPayload = base64_decode($envelope['payload'] ?? '', true);
            $storedSignature = $envelope['signature'] ?? null;
            if (! is_string($storedPayload)
                || ! is_string($storedSignature)
                || ! hash_equals(hash('sha256', $payloadJson), hash('sha256', $storedPayload))
                || ! hash_equals(hash_hmac('sha256', $storedPayload, (string) config('app.key')), $storedSignature)) {
                throw new RuntimeException('The pre-restore safety snapshot failed payload or signature verification.');
            }

            if (! $disk->move($temporaryPath, $finalPath)) {
                throw new RuntimeException('The verified pre-restore safety snapshot could not be published.');
            }

            $publishedBytes = $disk->get($finalPath);
            if (! is_string($publishedBytes) || ! hash_equals(hash('sha256', $archive), hash('sha256', $publishedBytes))) {
                throw new RuntimeException('The published pre-restore safety snapshot failed final integrity verification.');
            }

            $published = true;
            return $filename;
        } finally {
            if ($disk->exists($temporaryPath)) {
                $disk->delete($temporaryPath);
            }
            if (! $published && $disk->exists($finalPath)) {
                $disk->delete($finalPath);
            }
        }
    }

    /**
     * Apply a requested restore and automatically compensate from a verified
     * pre-restore snapshot if any database or media write fails.
     */
    public function applyWithRecovery(array $tables, array $targetPayload): string
    {
        $safetyPayload = $this->capture($tables);
        $snapshotFilename = $this->publishSafetySnapshot($safetyPayload);

        try {
            $this->restoreDatabase($tables, $targetPayload['tables']);
            $this->restoreFiles(
                $targetPayload['public_media'] ?? [],
                $targetPayload['private_files'] ?? []
            );
        } catch (Throwable $restoreFailure) {
            try {
                $this->restoreDatabase($tables, $safetyPayload['tables']);
                $this->compensateFiles($targetPayload, $safetyPayload);
            } catch (Throwable $recoveryFailure) {
                report($recoveryFailure);
                throw new RuntimeException(
                    'Restore failed and automatic recovery was incomplete. The verified pre-restore safety snapshot is retained at backups/'.$snapshotFilename.'. Do not retry; use that snapshot for manual recovery.',
                    0,
                    $restoreFailure
                );
            }

            throw new RuntimeException(
                'Restore failed. The previous database and media state was automatically restored. A verified safety snapshot is retained at backups/'.$snapshotFilename.'.',
                0,
                $restoreFailure
            );
        }

        return $snapshotFilename;
    }

    private function restoreDatabase(array $tables, array $rowsByTable): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tables, $rowsByTable): void {
                foreach (array_reverse($tables) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($tables as $table) {
                    $rows = $rowsByTable[$table] ?? [];
                    foreach (array_chunk($rows, 500) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function restoreFiles(array $publicFiles, array $privateFiles): void
    {
        foreach ($publicFiles as $path => $encoded) {
            $contents = base64_decode($encoded, true);
            if (! is_string($contents) || ! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('A public media file could not be restored.');
            }
        }
        foreach ($privateFiles as $path => $encoded) {
            $contents = base64_decode($encoded, true);
            if (! is_string($contents) || ! Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('A private media file could not be restored.');
            }
        }
    }

    private function compensateFiles(array $targetPayload, array $safetyPayload): void
    {
        $safetyPublic = $safetyPayload['public_media'] ?? [];
        foreach (array_keys($targetPayload['public_media'] ?? []) as $path) {
            if (! array_key_exists($path, $safetyPublic) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $safetyPrivate = $safetyPayload['private_files'] ?? [];
        foreach (array_keys($targetPayload['private_files'] ?? []) as $path) {
            if (! array_key_exists($path, $safetyPrivate) && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $this->restoreFiles($safetyPublic, $safetyPrivate);
    }
}
