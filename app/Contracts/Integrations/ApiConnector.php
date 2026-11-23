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

    public function verifyWebhook(Request $request, array $credentials): bool;

    /** @return array{event_id:string,event_type:string,external_account_id:?string,payload:array} */
    public function normalizeWebhook(Request $request): array;

    /** @return array{accepted:bool,provider_message_id:?string,status:string} */
    public function sendMessage(array $credentials, array $message): array;
}
