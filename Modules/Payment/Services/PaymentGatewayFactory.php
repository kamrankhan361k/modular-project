<?php

namespace Modules\Payment\Services;

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Services\Gateways\PaypalService;
use Modules\Payment\Services\Gateways\StripeService;
use Modules\Payment\Services\Gateways\RazorpayService;
use Modules\Payment\Services\Gateways\FlutterwaveService;
use Modules\Payment\Services\Gateways\MollieService;
use Modules\Payment\Services\Gateways\PaystackService;
use Modules\Payment\Services\Gateways\InstamojoService;
use Modules\Payment\Services\Gateways\BankPaymentService;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'paypal' => app(PaypalService::class),
            'stripe' => app(StripeService::class),
            'razorpay' => app(RazorpayService::class),
            'flutterwave' => app(FlutterwaveService::class),
            'mollie' => app(MollieService::class),
            'paystack' => app(PaystackService::class),
            'instamojo' => app(InstamojoService::class),
            'bank' => app(BankPaymentService::class),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }

    public function getSupportedGateways(): array
    {
        return [
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'razorpay' => 'Razorpay',
            'flutterwave' => 'Flutterwave',
            'mollie' => 'Mollie',
            'paystack' => 'Paystack',
            'instamojo' => 'Instamojo',
            'bank' => 'Bank Transfer'
        ];
    }

    public function getEnabledGateways(): array
    {
        $enabled = [];

        foreach ($this->getSupportedGateways() as $key => $name) {
            $gateway = $this->make($key);
            if ($gateway->isEnabled()) {
                $enabled[$key] = $name;
            }
        }

        return $enabled;
    }
}
