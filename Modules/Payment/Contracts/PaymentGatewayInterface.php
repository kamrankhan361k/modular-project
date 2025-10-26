<?php

namespace Modules\Payment\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process payment
     */
    public function pay(array $data);

    /**
     * Verify payment
     */
    public function verify(array $data);

    /**
     * Handle webhook
     */
    public function handleWebhook(array $data);

    /**
     * Refund payment
     */
    public function refund(array $data);

    /**
     * Get gateway name
     */
    public function getGatewayName(): string;

    /**
     * Check if gateway is enabled
     */
    public function isEnabled(): bool;
}
