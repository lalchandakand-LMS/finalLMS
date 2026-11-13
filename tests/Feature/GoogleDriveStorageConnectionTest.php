<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Services\GoogleDriveQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveStorageConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function configureGoogleOAuth(): void
    {
        config([
            'services.google_drive.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google_drive.client_secret' => 'test-client-secret',
            'services.google_drive.redirect_uri' => 'https://lms.example.test/admin/storage/google/callback',
        ]);
    }

    public function test_admin_oauth_redirect_uses_state_and_limited_drive_file_scope(): void
    {
        $this->configureGoogleOAuth();
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.storage.google.connect'));
        $response->assertRedirect();

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('test-client-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('https://lms.example.test/admin/storage/google/callback', $query['redirect_uri']);
        $this->assertContains('https://www.googleapis.com/auth/drive.file', explode(' ', $query['scope']));
        $this->assertContains('openid', explode(' ', $query['scope']));
        $this->assertSame('offline', $query['access_type']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($query['state'], session()->get('google_drive_oauth_state'));
    }

    public function test_oauth_callback_rejects_a_mismatched_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->withSession(['google_drive_oauth_state' => 'expected-state'])
            ->actingAs($admin)
            ->get(route('admin.storage.google.callback', ['state' => 'attacker-state', 'code' => 'oauth-code']))
            ->assertForbidden();
    }

    public function test_oauth_callback_encrypts_and_persists_google_tokens(): void
    {
        $this->configureGoogleOAuth();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'plain-access-token',
                'refresh_token' => 'plain-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'email' => 'storage-owner@example.com',
                'email_verified' => true,
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $this->withSession(['google_drive_oauth_state' => 'valid-state'])
            ->actingAs($admin)
            ->get(route('admin.storage.google.callback', ['state' => 'valid-state', 'code' => 'oauth-code']))
            ->assertRedirect(route('admin.storage.index'));

        $connection = GoogleDriveConnection::query()->firstOrFail();
        $this->assertSame('storage-owner@example.com', $connection->google_email);
        $this->assertTrue($connection->is_active);
        $this->assertSame('plain-access-token', $connection->accessTokenValue());
        $this->assertSame('plain-refresh-token', $connection->refreshTokenValue());
        $this->assertNotSame('plain-access-token', $connection->getRawOriginal('access_token'));
        $this->assertNotSame('plain-refresh-token', $connection->getRawOriginal('refresh_token'));
    }

    public function test_google_drive_api_returns_shared_account_quota(): void
    {
        $connection = GoogleDriveConnection::create([
            'google_email' => 'storage-owner@example.com',
            'access_token' => Crypt::encryptString('valid-access-token'),
            'refresh_token' => Crypt::encryptString('valid-refresh-token'),
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        Http::fake([
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['emailAddress' => 'storage-owner@example.com'],
                'storageQuota' => [
                    'limit' => '16106127360',
                    'usage' => '2147483648',
                    'usageInDrive' => '1073741824',
                    'usageInDriveTrash' => '0',
                ],
            ], 200),
        ]);

        $quota = app(GoogleDriveQuotaService::class)->getAccountQuota();

        $this->assertTrue($quota['connected']);
        $this->assertTrue($quota['available']);
        $this->assertSame('storage-owner@example.com', $quota['email']);
        $this->assertSame(2147483648, $quota['used_bytes']);
        $this->assertSame(16106127360, $quota['limit_bytes']);
        $this->assertSame(13958643712, $quota['free_bytes']);
        $this->assertSame(13.3, $quota['usage_percent']);
        $this->assertNotNull($connection->fresh()->last_checked_at);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/about?')
            && str_contains(urldecode($request->url()), 'storageQuota')
            && $request->hasHeader('Authorization', 'Bearer valid-access-token'));
    }

    public function test_expired_drive_access_token_is_refreshed_before_quota_lookup(): void
    {
        $connection = GoogleDriveConnection::create([
            'google_email' => 'storage-owner@example.com',
            'access_token' => Crypt::encryptString('expired-access-token'),
            'refresh_token' => Crypt::encryptString('valid-refresh-token'),
            'token_expires_at' => now()->subMinute(),
            'is_active' => true,
        ]);
        $this->configureGoogleOAuth();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'renewed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/drive/v3/about*' => Http::response([
                'storageQuota' => ['limit' => '1000', 'usage' => '100'],
            ], 200),
        ]);

        $quota = app(GoogleDriveQuotaService::class)->getAccountQuota();

        $this->assertTrue($quota['available']);
        $this->assertSame('renewed-access-token', $connection->fresh()->accessTokenValue());
        $this->assertSame('valid-refresh-token', $connection->fresh()->refreshTokenValue());
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
    }

    public function test_disconnect_revokes_and_clears_local_tokens_without_deleting_drive_files(): void
    {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response([], 200)]);
        $connection = GoogleDriveConnection::create([
            'google_email' => 'storage-owner@example.com',
            'access_token' => Crypt::encryptString('valid-access-token'),
            'refresh_token' => Crypt::encryptString('valid-refresh-token'),
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.storage.google.disconnect'))
            ->assertRedirect(route('admin.storage.index'));

        $this->assertDatabaseHas('google_drive_connections', [
            'id' => $connection->id,
            'is_active' => false,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'valid-refresh-token');
    }
}
