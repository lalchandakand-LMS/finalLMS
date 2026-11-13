<?php

namespace Tests\Feature;

use App\Models\SystemUpdateLock;
use App\Models\User;
use App\Services\UpdateOperationLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class UpdateOperationLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_update_operation_can_hold_the_database_lease(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lock = app(UpdateOperationLock::class);

        $result = $lock->run('package_prepare', $admin, function () use ($lock, $admin) {
            $this->assertTrue($lock->current()->isActive());
            $this->assertSame('package_prepare', $lock->current()->operation);

            try {
                $lock->run('private_extract', $admin, fn () => 'must not run');
                $this->fail('A second update operation must not acquire an active lease.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Another update operation is already running', $exception->getMessage());
            }

            return 'first operation completed';
        });

        $this->assertSame('first operation completed', $result);
        $this->assertNull($lock->current()->token);
        $this->assertFalse($lock->current()->isActive());
    }

    public function test_database_lease_is_released_when_an_update_operation_throws(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lock = app(UpdateOperationLock::class);

        try {
            $lock->run('pre_activation_backup', $admin, function () {
                throw new RuntimeException('simulated safe failure');
            });
            $this->fail('The operation exception should be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated safe failure', $exception->getMessage());
        }

        $this->assertNull($lock->current()->token);
        $this->assertNull($lock->current()->expires_at);
    }


    public function test_admin_update_action_is_rejected_while_another_operation_holds_the_lease(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lock = app(UpdateOperationLock::class);

        $lock->run('private_extract', $admin, function () use ($admin) {
            $this->actingAs($admin)
                ->post(route('admin.updates.prepare'))
                ->assertRedirect(route('admin.updates.index'))
                ->assertSessionHas('error', fn ($message) => str_contains($message, 'Another update operation is already running'));
        });

        $this->assertDatabaseHas('system_update_events', [
            'performed_by' => $admin->id,
            'event' => 'package_prepare',
            'status' => 'failed',
        ]);
    }

    public function test_expired_database_lease_can_be_reclaimed_after_an_interrupted_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lock = app(UpdateOperationLock::class);

        SystemUpdateLock::query()->whereKey(1)->update([
            'operation' => 'interrupted_extract',
            'token' => str_repeat('a', 64),
            'locked_by' => $admin->id,
            'acquired_at' => now()->subHours(3),
            'expires_at' => now()->subMinute(),
        ]);

        $result = $lock->run('activation_preflight', $admin, function () use ($lock) {
            $this->assertTrue($lock->current()->isActive());
            $this->assertSame('activation_preflight', $lock->current()->operation);

            return 'recovered';
        });

        $this->assertSame('recovered', $result);
        $this->assertNull($lock->current()->token);
    }
}
