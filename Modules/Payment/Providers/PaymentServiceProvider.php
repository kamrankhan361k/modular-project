<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Payment\Services\PaymentService;

class TestPaymentCommand extends Command
{
    protected $signature = 'payment:test {gateway} {amount=100}';
    protected $description = 'Test payment gateway integration';

    public function handle(PaymentService $paymentService)
    {
        $gateway = $this->argument('gateway');
        $amount = $this->argument('amount');

        $this->info("Testing {$gateway} gateway with amount {$amount}");

        $paymentData = [
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => 'USD',
            'description' => 'Test Payment',
            'order_id' => 'TEST_' . time(),
            'user_id' => 1, // Assuming user with ID 1 exists
        ];

        try {
            $result = $paymentService->processPayment($paymentData);

            if ($result['status'] === 'success') {
                $this->info("✅ Payment initiated successfully!");
                $this->info("Transaction ID: " . $result['transaction']->transaction_id);

                if (isset($result['redirect_url'])) {
                    $this->info("Redirect URL: " . $result['redirect_url']);
                }
            } else {
                $this->error("❌ Payment failed: " . ($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}
