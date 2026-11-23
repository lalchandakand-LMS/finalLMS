<?php

namespace App\Contracts\Payments;

/**
 * Optional adapter capability for providers whose callback reference is not
 * named paymentID. Implementations must only extract an opaque provider ID;
 * the Payment Core still verifies it server-to-server before fulfillment.
 */
interface PaymentGatewayCallbackReference
{
    /** @param array<string, mixed> $callback */
    public function callbackPaymentId(array $callback): ?string;
}
