<?php

namespace App\Contracts\Integrations;

use Illuminate\Http\Request;

interface ApiConnector
{
    public function key(): string;
    public function label(): string;

    /** @return array<string, array{label:string,required:bool,secret:bool}> */
    public function credentialSchema(): array;

    /** @return array<string, bool|string|int> */
    public function capabilities(): array;

    /** Validate credentials without sending a message or causing an external side effect. */
    public function validateCredentials(array $credentials): array;

    /** @return array{credentials:bool,webhook:bool,api:bool,detail?:string} */
    public function healthCheck(array $credentials): array;

    /** Return an explicit unsupported result when the provider does not expose templates. */
    public function sendTemplate(array $credentials, array $message): array;

    /** Return an explicit unsupported result when authoritative delivery receipts are unavailable. */
    public function getDeliveryStatus(array $credentials, string $providerMessageId): array;

    public function verifyWebhook(Request $request, array $credentials): bool;

    /** @return array{event_id:string,event_type:string,external_account_id:?string,payload:array} */
    public function normalizeWebhook(Request $request): array;

    /** @return array{accepted:bool,provider_message_id:?string,status:string} */
    public function sendMessage(array $credentials, array $message): array;
}
