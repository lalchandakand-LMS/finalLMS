<?php

namespace Tests\Feature;

use App\Services\Payments\BkashCheckoutService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashCheckoutServiceTest extends TestCase
{
    public function test_checkout_client_uses_server_side_credentials_and_verifies_payment_status_endpoint(): void
    {
        config([
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
            'services.bkash.verify_ssl' => true,
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['id_token' => 'sandbox-token'], 200),
            '*/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000',
                'paymentID' => 'PAYMENT-123',
                'bkashURL' => 'https://sandbox-checkout.example/pay/PAYMENT-123',
            ], 200),
            '*/tokenized/checkout/execute' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'PAYMENT-123',
                'trxID' => 'TRX-123',
                'amount' => '1500.00',
            ], 200),
            '*/tokenized/checkout/payment/status' => Http::response([
                'transactionStatus' => 'Completed',
                'paymentID' => 'PAYMENT-123',
                'trxID' => 'TRX-123',
                'amount' => '1500.00',
            ], 200),
        ]);

        $gateway = app(BkashCheckoutService::class);
        $token = $gateway->grantToken();
        $created = $gateway->createPayment(
            $token,
            '1500.00',
            'AEC-INV-123',
            'https://academy.example/payments/bkash/callback',
            'student-42'
        );
        $executed = $gateway->executePayment($token, 'PAYMENT-123');
        $status = $gateway->queryPayment($token, 'PAYMENT-123');

        $this->assertSame('sandbox-token', $token);
        $this->assertSame('PAYMENT-123', $created['paymentID']);
        $this->assertSame('Completed', $executed['transactionStatus']);
        $this->assertSame('TRX-123', $status['trxID']);

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/token/grant')
            && $request->hasHeader('username', 'sandbox-user')
            && $request->hasHeader('password', 'sandbox-password')
            && $request['app_secret'] === 'sandbox-app-secret'
        );

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/create')
            && $request->hasHeader('Authorization', 'Bearer sandbox-token')
            && $request['mode'] === '0011'
            && $request['intent'] === 'sale'
            && $request['merchantInvoiceNumber'] === 'AEC-INV-123'
        );

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/execute')
            && $request['paymentID'] === 'PAYMENT-123'
        );

        Http::assertSent(fn (Request $request) =>
            str_ends_with($request->url(), '/tokenized/checkout/payment/status')
            && $request['paymentID'] === 'PAYMENT-123'
        );
    }

    public function test_checkout_client_rejects_a_token_response_without_an_id_token(): void
    {
        config([
            'services.bkash.base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
            'services.bkash.username' => 'sandbox-user',
            'services.bkash.password' => 'sandbox-password',
            'services.bkash.app_key' => 'sandbox-app-key',
            'services.bkash.app_secret' => 'sandbox-app-secret',
        ]);

        Http::fake([
            '*/tokenized/checkout/token/grant' => Http::response(['statusCode' => '9999'], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        app(BkashCheckoutService::class)->grantToken();
    }
}
