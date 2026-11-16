<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CPanelQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorageQuotaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_storage_manager_without_exposing_unconfigured_credentials(): void
    {
        config([
            'storage-manager.cpanel.host' => null,
            'storage-manager.cpanel.username' => null,
            'storage-manager.cpanel.api_token' => null,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get(route('admin.storage.index'))
            ->assertOk()
            ->assertSee('Storage Manager')
            ->assertSee('Live cPanel quota is not connected yet')
            ->assertDontSee('secret-test-token');
    }

    public function test_student_cannot_access_storage_manager(): void
    {
        $student = User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
        $this->actingAs($student)->get(route('admin.storage.index'))->assertForbidden();
    }

    public function test_cpanel_service_converts_live_quota_to_bytes_and_percent(): void
    {
        config([
            'storage-manager.cpanel.host' => 'cpanel.example.test',
            'storage-manager.cpanel.username' => 'testcpanel',
            'storage-manager.cpanel.api_token' => 'secret-test-token',
        ]);
        Http::fake([
            'https://cpanel.example.test:2083/execute/Quota/get_quota_info' => Http::response([
                'result' => ['status' => 1, 'data' => [
                    'megabytes_used' => 512,
                    'megabyte_limit' => 10240,
                    'megabytes_remain' => 9728,
                ]],
            ], 200),
        ]);

        $quota = app(CPanelQuotaService::class)->getAccountQuota();

        $this->assertTrue($quota['available']);
        $this->assertSame(536870912, $quota['used_bytes']);
        $this->assertSame(10737418240, $quota['limit_bytes']);
        $this->assertSame(10200547328, $quota['free_bytes']);
        $this->assertSame(5.0, $quota['usage_percent']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'cpanel testcpanel:secret-test-token'));
    }

    public function test_invalid_cpanel_host_is_rejected_without_network_request(): void
    {
        config([
            'storage-manager.cpanel.host' => 'https://attacker.example/path',
            'storage-manager.cpanel.username' => 'testcpanel',
            'storage-manager.cpanel.api_token' => 'secret-test-token',
        ]);
        Http::fake();

        $quota = app(CPanelQuotaService::class)->getAccountQuota();

        $this->assertFalse($quota['available']);
        $this->assertStringContainsString('hostname is invalid', $quota['reason']);
        Http::assertNothingSent();
    }
}
