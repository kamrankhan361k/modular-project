<?php

namespace Modules\Payment\Services\Gateways;

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeService implements PaymentGatewayInterface
{
    private $secretKey;
    private $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
    }

    public function pay(array $data)
    {
        \Stripe\Stripe::setApiKey($this->secretKey);

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $data['currency'] ?? 'usd',
                        'product_data' => [
                            'name' => $data['description'] ?? 'Payment',
                        ],
                        'unit_amount' => $data['amount'] * 100, // Stripe expects amount in cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $data['return_url'] . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $data['cancel_url'],
                'metadata' => [
                    'order_id' => $data['order_id']
                ],
            ]);

            return [
                'status' => 'success',
                'transaction_id' => $session->id,
                'redirect_url' => $session->url,
                'gateway_response' => $session->toArray()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function verify(array $data)
    {
        \Stripe\Stripe::setApiKey($this->secretKey);

        try {
            $session = \Stripe\Checkout\Session::retrieve($data['session_id']);

            return [
                'status' => 'success',
                'transaction_status' => $session->payment_status,
                'gateway_response' => $session->toArray()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook(array $data)
    {
        $payload = $data['payload'];
        $sigHeader = $data['signature'] ?? '';

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $this->webhookSecret
            );

            Log::info('Stripe Webhook received', ['type' => $event->type]);

            switch ($event->type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutSessionCompleted($event->data->object);
                case 'payment_intent.succeeded':
                    return $this->handlePaymentSucceeded($event->data->object);
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailed($event->data->object);
                default:
                    return ['status' => 'ignored'];
            }

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function refund(array $data)
    {
        \Stripe\Stripe::setApiKey($this->secretKey);

        try {
            $refund = \Stripe\Refund::create([
                'payment_intent' => $data['payment_intent_id'],
                'amount' => $data['amount'] * 100,
            ]);

            return [
                'status' => 'success',
                'refund_id' => $refund->id,
                'gateway_response' => $refund->toArray()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function getGatewayName(): string
    {
        return 'stripe';
    }

    public function isEnabled(): bool
    {
        return !empty($this->secretKey);
    }

    private function handleCheckoutSessionCompleted($session)
    {
        // Update transaction status
        return ['status' => 'processed'];
    }

    private function handlePaymentSucceeded($paymentIntent)
    {
        // Handle successful payment
        return ['status' => 'processed'];
    }

    private function handlePaymentFailed($paymentIntent)
    {
        // Handle failed payment
        return ['status' => 'processed'];
    }
}
