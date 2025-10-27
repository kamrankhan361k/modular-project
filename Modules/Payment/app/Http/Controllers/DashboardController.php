<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Payment\Entities\PaymentTransaction;
use Modules\Payment\Transformers\PaymentResource;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $transactions = PaymentTransaction::with(['user', 'order'])
            ->latest()
            ->paginate(20);

        if ($request->wantsJson()) {
            return PaymentResource::collection($transactions);
        }

        return view('payment::dashboard.index', compact('transactions'));
    }

    public function show(PaymentTransaction $transaction)
    {
        return view('payment::dashboard.show', compact('transaction'));
    }

    public function statistics()
    {
        $totalTransactions = PaymentTransaction::count();
        $totalRevenue = PaymentTransaction::completed()->sum('amount');
        $successfulTransactions = PaymentTransaction::completed()->count();
        $pendingTransactions = PaymentTransaction::pending()->count();

        return response()->json([
            'total_transactions' => $totalTransactions,
            'total_revenue' => $totalRevenue,
            'successful_transactions' => $successfulTransactions,
            'pending_transactions' => $pendingTransactions,
            'success_rate' => $totalTransactions > 0 ? ($successfulTransactions / $totalTransactions) * 100 : 0
        ]);
    }
}
