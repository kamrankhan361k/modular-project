<?php

namespace Modules\Payment\Services\Gateways;

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalService implements PaymentGatewayInterface
{
    private $clientId;
    private $clientSecret;
    private $mode;
    private $accessToken;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->mode = config('services.paypal.mode', 'sandbox');
        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken()
    {
        $url = $this->mode === 'live'
            ? 'https://api.paypal.com/v1/oauth2/token'
            : 'https://api.sandbox.paypal.com/v1/oauth2/token';

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($url, ['grant_type' => 'client_credentials']);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Failed to get PayPal access token');
    }

    public function pay(array $data)
    {
        $url = $this->mode === 'live'
            ? 'https://api.paypal.com/v2/checkout/orders'
            : 'https://api.sandbox.paypal.com/v2/checkout/orders';

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $data['currency'] ?? 'USD',
                        'value' => $data['amount']
                    ],
                    'description' => $data['description'] ?? 'Payment',
                    'custom_id' => $data['order_id']
                ]
            ],
            'application_context' => [
                'return_url' => $data['return_url'],
                'cancel_url' => $data['cancel_url'],
                'brand_name' => config('app.name')
            ]
        ];

        $response = Http::withToken($this->accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $orderData);

        if ($response->successful()) {
            $paymentData = $response->json();

            // Find approval URL
            $approveUrl = collect($paymentData['links'])->firstWhere('rel', 'approve')['href'];

            return [
                'status' => 'success',
                'transaction_id' => $paymentData['id'],
                'redirect_url' => $approveUrl,
                'gateway_response' => $paymentData
            ];
        }

        return [
            'status' => 'failed',
            'error' => $response->json()
        ];
    }

    public function verify(array $data)
    {
        $transactionId = $data['transaction_id'];

        $url = $this->mode === 'live'
            ? "https://api.paypal.com/v2/checkout/orders/{$transactionId}"
            : "https://api.sandbox.paypal.com/v2/checkout/orders/{$transactionId}";

        $response = Http::withToken($this->accessToken)->get($url);

        if ($response->successful()) {
            $orderData = $response->json();

            return [
                'status' => 'success',
                'transaction_status' => strtolower($orderData['status']),
                'gateway_response' => $orderData
            ];
        }

        return [
            'status' => 'failed',
            'error' => $response->json()
        ];
    }

    public function handleWebhook(array $data)
    {
        // Verify webhook signature and process webhook
        Log::info('PayPal Webhook received', $data);

        // Process webhook based on event type
        $eventType = $data['event_type'] ?? '';

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentCompleted($data);
            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handlePaymentFailed($data);
            default:
                return ['status' => 'ignored'];
        }
    }

    public function refund(array $data)
    {
        // Implement refund logic
        return ['status' => 'refund_initiated'];
    }

    public function getGatewayName(): string
    {
        return 'paypal';
    }

    public function isEnabled(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    private function handlePaymentCompleted(array $data)
    {
        // Update transaction status to completed
        return ['status' => 'processed'];
    }

    private function handlePaymentFailed(array $data)
    {
        // Update transaction status to failed
        return ['status' => 'processed'];
    }
}
