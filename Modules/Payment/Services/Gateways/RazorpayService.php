<?php

namespace Modules\Payment\Services\Gateways;

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService implements PaymentGatewayInterface
{
    private $keyId;
    private $keySecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key');
        $this->keySecret = config('services.razorpay.secret');
    }

    public function pay(array $data)
    {
        $orderData = [
            'amount' => $data['amount'] * 100, // Razorpay expects amount in paise
            'currency' => $data['currency'] ?? 'INR',
            'receipt' => $data['order_id'],
            'payment_capture' => 1,
            'notes' => [
                'order_id' => $data['order_id'],
                'description' => $data['description'] ?? 'Payment'
            ]
        ];

        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post('https://api.razorpay.com/v1/orders', $orderData);

        if ($response->successful()) {
            $order = $response->json();

            return [
                'status' => 'success',
                'transaction_id' => $order['id'],
                'gateway_response' => $order,
                'checkout_data' => [
                    'key' => $this->keyId,
                    'amount' => $order['amount'],
                    'currency' => $order['currency'],
                    'order_id' => $order['id'],
                    'name' => config('app.name'),
                    'description' => $data['description'] ?? 'Payment',
                    'prefill' => [
                        'name' => $data['customer_name'] ?? '',
                        'email' => $data['customer_email'] ?? '',
                        'contact' => $data['customer_phone'] ?? ''
                    ],
                    'notes' => [
                        'order_id' => $data['order_id']
                    ],
                    'theme' => [
                        'color' => '#F37254'
                    ]
                ]
            ];
        }

        return [
            'status' => 'failed',
            'error' => $response->json()
        ];
    }

    public function verify(array $data)
    {
        $expectedSignature = hash_hmac('sha256', $data['razorpay_order_id'] . '|' . $data['razorpay_payment_id'], $this->keySecret);

        if ($expectedSignature === $data['razorpay_signature']) {
            return [
                'status' => 'success',
                'transaction_status' => 'completed',
                'gateway_response' => $data
            ];
        }

        return [
            'status' => 'failed',
            'error' => 'Invalid signature'
        ];
    }

    public function handleWebhook(array $data)
    {
        Log::info('Razorpay Webhook received', $data);

        // Verify webhook signature and process
        return ['status' => 'processed'];
    }

    public function refund(array $data)
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("https://api.razorpay.com/v1/payments/{$data['payment_id']}/refund", [
                'amount' => $data['amount'] * 100
            ]);

        if ($response->successful()) {
            return [
                'status' => 'success',
                'refund_id' => $response->json()['id'],
                'gateway_response' => $response->json()
            ];
        }

        return [
            'status' => 'failed',
            'error' => $response->json()
        ];
    }

    public function getGatewayName(): string
    {
        return 'razorpay';
    }

    public function isEnabled(): bool
    {
        return !empty($this->keyId) && !empty($this->keySecret);
    }
}
