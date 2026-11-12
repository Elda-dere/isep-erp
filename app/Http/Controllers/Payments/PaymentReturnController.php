<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\ChapaException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Inertia\Inertia;
use Inertia\Response;

class PaymentReturnController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    // PAY-12: where the customer lands after paying. Public: the tx_ref is long and unguessable.
    public function show(string $txRef): Response
    {
        $payment = Payment::with('sale:id,sale_number')->where('tx_ref', $txRef)->firstOrFail();

        if ($payment->status === Payment::STATUS_PENDING) {
            try {
                // Do not wait for the webhook: ask Chapa now.
                $payment = $this->payments->verify($payment)->load('sale:id,sale_number');
            } catch (ChapaException) {
                // Chapa cannot be reached right now: the page keeps saying "processing", and the
                // webhook or the reconciliation job will settle it.
            }
        }

        // Nothing more than the sale number, the amount and the status is revealed.
        return Inertia::render('Public/PaymentReturn', [
            'payment' => [
                'sale_number' => $payment->sale->sale_number,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
            ],
        ]);
    }
}