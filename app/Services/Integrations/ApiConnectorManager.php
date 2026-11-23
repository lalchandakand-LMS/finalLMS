<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\ApiConnector;
use App\Models\ApiConnection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ApiConnectorManager
{
    /** @var array<string, class-string<ApiConnector>> */
    private array $drivers = [];

    public function register(string $key, string $driver): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{1,79}$/D', $key) || ! is_subclass_of($driver, ApiConnector::class)) {
            throw new InvalidArgumentException('Invalid API connector registration.');
        }
        if (isset($this->drivers[$key])) {
            throw new InvalidArgumentException('API connector key is already registered.');
        }
        $instance = app($driver);
        if (! hash_equals($key, $instance->key())) {
            throw new InvalidArgumentException('Connector key does not match its adapter.');
        }
        $this->drivers[$key] = $driver;
    }

    public function driver(string $key): ApiConnector
    {
        $class = $this->drivers[$key] ?? null;
        abort_unless($class, 404, 'Unsupported API connector.');
        return app($class);
    }

    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->drivers as $key => $class) {
            $driver = app($class);
            $connection = ApiConnection::query()->where('provider_key', $key)->whereNull('branch_id')->first();
            $catalog[$key] = [
                'key' => $key,
                'label' => $driver->label(),
                'status' => $connection?->status ?? 'not_configured',
                'external_account_id' => $connection?->external_account_id,
                'capabilities' => $connection?->capabilities ?? $driver->capabilities(),
                'credential_schema' => $driver->credentialSchema(),
                'last_checked_at' => $connection?->last_credential_check_at,
                'last_event_at' => $connection?->last_webhook_event_at,
                'last_success_at' => $connection?->last_successful_operation_at,
                'last_error' => $connection?->last_error,
                'webhook_verified' => (bool) $connection?->webhook_verified,
            ];
        }
        return $catalog;
    }

    public function connect(string $key, array $credentials): ApiConnection
    {
        $driver = $this->driver($key);
        $result = $driver->validateCredentials($credentials);
        if (($result['valid'] ?? false) !== true) {
            throw new RuntimeException('Provider credentials or required permissions could not be verified.');
        }

        $connection = ApiConnection::query()->updateOrCreate(
            ['provider_key' => $key, 'branch_id' => null],
            [
                'display_name' => $driver->label(),
                'external_account_id' => isset($result['account_id']) ? (string) $result['account_id'] : null,
                'credentials_encrypted' => $credentials,
                'granted_scopes' => is_array($result['scopes'] ?? null) ? $result['scopes'] : [],
                'capabilities' => $driver->capabilities(),
                'status' => 'pending_verification',
                'last_credential_check_at' => now(),
                'last_error' => null,
            ]
        );

        $this->audit($key, 'credentials_verified', ['account_id' => $connection->external_account_id]);
        return $connection;
    }

    public function processWebhook(string $key, \Illuminate\Http\Request $request): bool
    {
        $driver = $this->driver($key);
        $connection = ApiConnection::query()->where('provider_key', $key)->whereNull('branch_id')->firstOrFail();
        $credentials = $connection->credentials_encrypted ?? [];
        abort_unless($driver->verifyWebhook($request, $credentials), 401, 'Webhook authentication failed.');
        $event = $driver->normalizeWebhook($request);
        if (empty($event['event_id']) || empty($event['event_type'])) {
            abort(422, 'Webhook event is missing its provider event identity.');
        }

        $inserted = DB::table('api_connector_events')->insertOrIgnore([
            'provider_key' => $key,
            'provider_event_id' => (string) $event['event_id'],
            'event_type' => (string) $event['event_type'],
            'external_account_id' => $event['external_account_id'] ?? null,
            'payload' => json_encode($event['payload'] ?? [], JSON_THROW_ON_ERROR),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted) {
            $connection->forceFill([
                'status' => 'connected',
                'webhook_verified' => true,
                'last_webhook_event_at' => now(),
                'last_successful_operation_at' => now(),
                'last_error' => null,
            ])->save();
            $this->audit($key, 'webhook_event_received', ['event_id' => (string) $event['event_id'], 'event_type' => (string) $event['event_type']]);
        }
        return (bool) $inserted;
    }

    public function pause(string $key): void
    {
        $this->connection($key)->update(['status' => 'paused']);
        $this->audit($key, 'paused');
    }

    public function disconnect(string $key): void
    {
        $this->connection($key)->update(['status' => 'disconnected', 'credentials_encrypted' => null, 'webhook_verified' => false, 'capabilities' => null]);
        $this->audit($key, 'disconnected');
    }

    public function sendMessage(string $key, array $message): array
    {
        $connection = $this->connection($key);
        if (! $connection->isConnected()) {
            throw new RuntimeException('The connector is not fully connected and verified.');
        }
        $result = $this->driver($key)->sendMessage($connection->credentials_encrypted ?? [], $message);
        if (($result['accepted'] ?? false) === true) {
            $connection->forceFill(['last_successful_operation_at' => now(), 'last_error' => null])->save();
        }
        $this->audit($key, 'outbound_message_attempted', ['accepted' => (bool) ($result['accepted'] ?? false), 'status' => $result['status'] ?? 'unknown']);
        return $result;
    }

    private function connection(string $key): ApiConnection
    {
        return ApiConnection::query()->where('provider_key', $key)->whereNull('branch_id')->firstOrFail();
    }

    private function audit(string $key, string $event, array $metadata = []): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            try {
                \App\Models\ActivityLog::query()->create([
                    'user_id' => auth()->id(),
                    'event' => 'api_connector.'.$event,
                    'description' => mb_substr($key.' '.json_encode($metadata, JSON_THROW_ON_ERROR), 0, 255),
                    'method' => request()?->method(),
                    'route' => request()?->route()?->getName(),
                    'ip_address' => request()?->ip(),
                    'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
