<?php

namespace App\Modules\Subscription\Contracts;

use App\Modules\Subscription\Enums\GatewayVerificationResult;
use App\Modules\Subscription\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment with the configured provider and return its redirect URL.
     *
     * Implementations should use the payment gateway_reference as the provider
     * idempotency key so retries do not create multiple provider-side charges.
     */
    public function initiatePayment(Payment $payment): string;

    /**
     * Verify a provider reference against the provider's authoritative API.
     */
    public function verifyPayment(string $gatewayReference): GatewayVerificationResult;
}
