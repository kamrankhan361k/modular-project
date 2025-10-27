<?php

namespace Modules\Payment\Services;

use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Payment\Entities\PaymentTransaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private $gatewayFactory;

    public function __construct(PaymentGatewayFactory $gatewayFactory)
    {
        $this->gatewayFactory = $gatewayFactory;
    }

    public function processPayment(array $data)
    {
        try {
            $gateway = $this->gatewayFactory->make($data['gateway']);

            if (!$gateway->isEnabled()) {
                throw new \Exception("Payment gateway {$data['gateway']} is not enabled");
            }

            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'transaction_id' => Str::uuid(),
                'user_id' => $data['user_id'],
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'payment_gateway' => $data['gateway'],
                'status' => 'pending',
                'payment_details' => $data
            ]);

            $paymentData = array_merge($data, [
                'transaction_id' => $transaction->transaction_id,
                'return_url' => route('payment.success', ['gateway' => $data['gateway']]),
                'cancel_url' => route('payment.cancel', ['gateway' => $data['gateway']])
            ]);

            $result = $gateway->pay($paymentData);

            if ($result['status'] === 'success') {
                $transaction->update([
                    'gateway_transaction_id' => $result['transaction_id'],
                    'gateway_response' => json_encode($result['gateway_response']),
                    'payment_details' => array_merge($data, $result)
                ]);

                return [
                    'status' => 'success',
                    'transaction' => $transaction,
                    'redirect_url' => $result['redirect_url'] ?? null,
                    'checkout_data' => $result['checkout_data'] ?? null
                ];
            }

            $transaction->update([
                'status' => 'failed',
                'failure_reason' => $result['error'] ?? 'Payment failed',
                'gateway_response' => json_encode($result)
            ]);

            return [
                'status' => 'failed',
                'error' => $result['error'] ?? 'Payment failed'
            ];

        } catch (\Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage());

            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function verifyPayment(string $gateway, array $data)
    {
        try {
            $gatewayService = $this->gatewayFactory->make($gateway);
            $result = $gatewayService->verify($data);

            if ($result['status'] === 'success') {
                $transaction = PaymentTransaction::where('gateway_transaction_id', $data['transaction_id'])
                    ->orWhere('transaction_id', $data['transaction_id'])
                    ->first();

                if ($transaction) {
                    $status = $this->mapGatewayStatus($result['transaction_status']);

                    $transaction->update([
                        'status' => $status,
                        'paid_at' => $status === 'completed' ? now() : null,
                        'gateway_response' => json_encode($result['gateway_response'])
                    ]);

                    return [
                        'status' => 'success',
                        'transaction' => $transaction
                    ];
                }
            }

            return [
                'status' => 'failed',
                'error' => $result['error'] ?? 'Verification failed'
            ];

        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook(string $gateway, array $data)
    {
        try {
            $gatewayService = $this->gatewayFactory->make($gateway);
            $result = $gatewayService->handleWebhook($data);

            // Update transaction based on webhook data
            if (isset($data['transaction_id'])) {
                $transaction = PaymentTransaction::where('gateway_transaction_id', $data['transaction_id'])
                    ->first();

                if ($transaction) {
                    $status = $this->mapGatewayStatus($data['status'] ?? 'pending');

                    $transaction->update([
                        'status' => $status,
                        'paid_at' => $status === 'completed' ? now() : null,
                        'gateway_response' => json_encode($data)
                    ]);
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Webhook handling failed: ' . $e->getMessage());

            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    private function mapGatewayStatus(string $gatewayStatus): string
    {
        $statusMap = [
            'completed' => 'completed',
            'succeeded' => 'completed',
            'paid' => 'completed',
            'captured' => 'completed',
            'failed' => 'failed',
            'canceled' => 'cancelled',
            'denied' => 'failed',
            'expired' => 'failed'
        ];

        return $statusMap[strtolower($gatewayStatus)] ?? 'pending';
    }

    public function getSupportedGateways(): array
    {
        return $this->gatewayFactory->getSupportedGateways();
    }
}
