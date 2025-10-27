<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Payment\Services\PaymentService;
use Modules\Payment\Http\Requests\ProcessPaymentRequest;

class PaymentController extends Controller
{
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function showCheckoutForm()
    {
        $gateways = $this->paymentService->getSupportedGateways();

        return view('payment::checkout', compact('gateways'));
    }

    public function processPayment(ProcessPaymentRequest $request)
    {
        $paymentData = $request->validated();
        $paymentData['user_id'] = auth()->id();
        $paymentData['ip_address'] = $request->ip();
        $paymentData['user_agent'] = $request->userAgent();

        $result = $this->paymentService->processPayment($paymentData);

        if ($result['status'] === 'success') {
            if (isset($result['redirect_url'])) {
                return redirect()->away($result['redirect_url']);
            }

            if (isset($result['checkout_data'])) {
                return view('payment::gateways.razorpay', [
                    'checkout_data' => $result['checkout_data']
                ]);
            }

            return redirect()->route('payment.success', [
                'gateway' => $paymentData['gateway'],
                'transaction_id' => $result['transaction']->transaction_id
            ]);
        }

        return back()->with('error', $result['error'] ?? 'Payment processing failed');
    }

    public function paymentSuccess(Request $request, string $gateway)
    {
        $transactionId = $request->get('transaction_id');

        if ($transactionId) {
            $verification = $this->paymentService->verifyPayment($gateway, [
                'transaction_id' => $transactionId
            ]);

            if ($verification['status'] === 'success') {
                return view('payment::success', [
                    'transaction' => $verification['transaction']
                ]);
            }
        }

        return view('payment::success');
    }

    public function paymentCancel(string $gateway)
    {
        return view('payment::cancel');
    }

    public function paymentWebhook(Request $request, string $gateway)
    {
        $result = $this->paymentService->handleWebhook($gateway, $request->all());

        if ($result['status'] === 'success') {
            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'failed'], 400);
    }
}
