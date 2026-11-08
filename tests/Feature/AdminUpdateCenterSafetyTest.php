<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\Hash;
use Illuminate\\Support\\Facades\\Storage;
use Tests\\TestCase;

class AdminUpdateCenterSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_center_rejects_wrong_admin_password_before_creating_a_backup(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.updates.migrate'), [
                'confirmation' => 'MIGRATE',
                'password' => 'incorrect-password',
            ])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('local')->allFiles('backups'));
    }

    public function test_update_center_creates_a_pre_migration_backup_after_password_confirmation(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('CorrectHorseBattery2026!'),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.updates.migrate'), [
                'confirmation' => 'MIGRATE',
                'password' => 'CorrectHorseBattery2026!',
            ])
            ->assertRedirect(route('admin.updates.index'))
            ->assertSessionHas('success');

        $backups = Storage::disk('local')->allFiles('backups');
        $this->assertCount(1, $backups);
        $this->assertStringEndsWith('.json.gz', $backups[0]);
    }
}
