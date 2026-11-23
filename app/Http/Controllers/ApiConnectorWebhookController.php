<?php

namespace App\Http\Controllers;

use App\Services\Integrations\ApiConnectorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class ApiConnectorWebhookController extends Controller
{
    public function receive(string $provider, Request $request, ApiConnectorManager $manager): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 1048576 || (int) $request->header('Content-Length', 0) > 1048576, 413);
        try {
            $inserted = $manager->processWebhook($provider, $request);
            return response()->json(['received' => true, 'duplicate' => ! $inserted], 202);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Webhook could not be accepted.'], 401);
        }
    }
}
