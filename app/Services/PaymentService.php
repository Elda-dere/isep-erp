<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns every payment status change and the moment a sale becomes paid. Controllers never change them.
 *
 * Chapa is asked OUTSIDE database transactions (a network wait must never hold a lock). Locks are always
 * taken in the same order, SALE first, then payment, so two things working on the same sale cannot deadlock.
 *
 * Throws ChapaException when Chapa cannot be reached or refuses a request, DomainException for business refusals.
 */
class PaymentService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(private ChapaClient $chapa)
    {
    }

    // PAY-01 to PAY-03: starts one payment attempt for a PENDING sale. A retry always creates a NEW row.
    public function start(Sale $sale): Payment
    {
        $sale = Sale::with('customer')->findOrFail($sale->id); // the copy we were given may be out of date
        $this->assertPayable($sale);

        // Not guessable: the sale number plus a random suffix.
        $txRef = $sale->sale_number.'-'.strtoupper(Str::random(12));
        $amount = (string) BigDecimal::of((string) $sale->total_amount)->toScale(2);

        // If Chapa refuses, this throws and NOTHING is saved: the sale stays pending (SRS 6.2).
        $checkoutUrl = $this->chapa->initialize($this->initializePayload($sale, $txRef, $amount));

        return DB::transaction(function () use ($sale, $txRef, $amount, $checkoutUrl) {
            $locked = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->assertPayable($locked); // it may have been cancelled while we waited for Chapa

            $payment = Payment::create([
                'sale_id' => $locked->id,
                'provider' => Payment::PROVIDER_CHAPA,
                'tx_ref' => $txRef,
                'amount' => $amount,              // DR-06: the sale's amount and currency
                'currency' => $locked->currency,
                'status' => Payment::STATUS_PENDING,
                'checkout_url' => $checkoutUrl,
            ]);

            AuditLogger::log('payment.created', 'payment', $payment->id, null, [
                'sale_id' => (int) $locked->id,
                'tx_ref' => $txRef,
                'amount' => $amount,
                'currency' => $locked->currency,
            ]);

            return $payment;
        }, self::TRANSACTION_ATTEMPTS);
    }

    // PAY-08 to PAY-11: asks Chapa for the REAL state and applies it. Safe to call again and again.
    public function verify(Payment $payment): Payment
    {
        $payment = Payment::findOrFail($payment->id);

        if ($payment->status === Payment::STATUS_SUCCESS) {
            return $payment; // already settled: asking Chapa again would only waste a call
        }

        $remote = $this->chapa->verify($payment->tx_ref); // may throw: the payment then stays as it was

        return DB::transaction(function () use ($payment, $remote) {
            $sale = Sale::whereKey($payment->sale_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Check again under the lock: another request may have settled it meanwhile.
            if ($payment->status !== Payment::STATUS_SUCCESS) {
                $this->apply($payment, $sale, $remote);
            }

            return $payment->fresh();
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function assertPayable(Sale $sale): void
    {
        if ($sale->status !== Sale::STATUS_PENDING) {
            throw new DomainException("Only a pending sale can be paid (this one is {$sale->status}).");
        }

        if (! BigDecimal::of((string) $sale->total_amount)->isPositive()) {
            throw new DomainException('A sale with a total of zero cannot be paid online.');
        }
    }

    private function initializePayload(Sale $sale, string $txRef, string $amount): array
    {
        $parts = array_pad(explode(' ', trim((string) $sale->customer->name), 2), 2, '');

        $payload = [
            'amount' => $amount,
            'currency' => $sale->currency,
            'tx_ref' => $txRef,
            'first_name' => $parts[0],
            'return_url' => str_replace('{tx_ref}', $txRef, (string) config('chapa.return_url')),
            'customization' => [
                'title' => Str::limit((string) config('chapa.checkout_title'), 16, ''),
                'description' => 'Payment for sale '.$sale->sale_number, // plain words, numbers and hyphens only
            ],
        ];

        if ($parts[1] !== '') {
            $payload['last_name'] = $parts[1];
        }

        // SRS Q07: send the e-mail when there is one, else a configured placeholder, else none.
        $email = $sale->customer->email ?: config('chapa.placeholder_email');

        if ($email) {
            $payload['email'] = $email;
        }

        if ($phone = $this->chapaPhone($sale->customer->phone)) {
            $payload['phone_number'] = $phone;
        }

        if ($callback = config('chapa.callback_url')) {
            $payload['callback_url'] = str_replace('{tx_ref}', $txRef, (string) $callback);
        }

        return $payload;
    }

    // Chapa expects 09xxxxxxxx or 07xxxxxxxx. Anything else is left out rather than risk a refused payment.
    private function chapaPhone(?string $phone): ?string
    {
        $number = preg_replace('/[^\d+]/', '', (string) $phone);
        $number = preg_replace('/^\+?251/', '0', $number);

        return preg_match('/^0[79]\d{8}$/', $number) === 1 ? $number : null;
    }

    // Turns Chapa's answer into a payment status. Runs inside the transaction, with the sale and payment locked.
    private function apply(Payment $payment, Sale $sale, array $remote): void
    {
        $data = is_array($remote['data'] ?? null) ? $remote['data'] : [];

        $outcome = match (strtolower((string) ($data['status'] ?? ''))) {
            'success' => Payment::STATUS_SUCCESS,
            'failed' => Payment::STATUS_FAILED,
            'cancelled', 'canceled' => Payment::STATUS_CANCELLED,
            default => null, // still pending (or a value we do not know): change nothing and write nothing
        };

        if ($outcome === null) {
            return;
        }

        // PAY-11: failed or cancelled. The SALE stays pending, so another attempt can be made.
        if ($outcome !== Payment::STATUS_SUCCESS) {
            if ($payment->status !== $outcome) {
                $this->recordEvent($payment, 'verify_failed', $remote);
                $this->changeStatus($payment, $outcome);
            }

            return;
        }

        // PAY-08 / PAY-10: "success" is only believed if EVERYTHING matches what we expect.
        $problems = $this->mismatches($payment, $data);

        if ($problems !== []) {
            if ($payment->status !== Payment::STATUS_FAILED) {
                $this->recordEvent($payment, 'verify_failed', $remote);
                $this->changeStatus($payment, Payment::STATUS_FAILED);
                $this->flag($payment, $problems, $data);
            }

            return;
        }

        // PAY-09: success. A payment that earlier failed may still succeed (the money DID arrive), so any
        // non-success status may move to success.
        $this->changeStatus($payment, Payment::STATUS_SUCCESS, [
            'provider_transaction_id' => isset($data['reference']) ? (string) $data['reference'] : null,
            'verified_at' => now(),
            'paid_at' => now(),
        ]);
        $this->recordEvent($payment, 'verify_success', $remote);

        $this->settleSale($payment, $sale);
    }

    // What differs between Chapa's answer and what we expect? Amounts are compared as NUMBERS ("25" equals "25.00").
    private function mismatches(Payment $payment, array $data): array
    {
        $problems = [];

        if (($data['tx_ref'] ?? null) !== $payment->tx_ref) {
            $problems[] = 'tx_ref';
        }

        if (strtoupper((string) ($data['currency'] ?? '')) !== strtoupper((string) $payment->currency)) {
            $problems[] = 'currency';
        }

        if (strtolower((string) ($data['mode'] ?? '')) !== strtolower((string) config('chapa.mode', 'test'))) {
            $problems[] = 'mode';
        }

        try {
            $sameAmount = BigDecimal::of((string) ($data['amount'] ?? ''))->isEqualTo((string) $payment->amount);
        } catch (MathException) {
            $sameAmount = false;
        }

        if (! $sameAmount) {
            $problems[] = 'amount';
        }

        return $problems;
    }

    // PAY-14: a sale is paid when its successful payments add up to its total.
    private function settleSale(Payment $payment, Sale $sale): void
    {
        // Money arrived, but the sale cannot use it (it was cancelled or already paid): a person must look.
        if ($sale->status !== Sale::STATUS_PENDING) {
            $reason = $sale->status === Sale::STATUS_PAID ? 'sale_already_paid' : 'sale_not_pending';
            $this->flag($payment, [$reason], [], ['sale_status' => $sale->status]);

            return;
        }

        $paid = BigDecimal::of((string) Payment::where('sale_id', $sale->id)->where('status', Payment::STATUS_SUCCESS)->sum('amount'));

        if ($paid->isLessThan(BigDecimal::of((string) $sale->total_amount))) {
            return;
        }

        $sale->update(['status' => Sale::STATUS_PAID]);

        AuditLogger::log('sale.paid', 'sale', $sale->id, ['status' => Sale::STATUS_PENDING], ['status' => Sale::STATUS_PAID]);
    }

    // The one place where a payment status changes: update it, then write the audit row (AUD-02).
    private function changeStatus(Payment $payment, string $status, array $extra = []): void
    {
        $old = $payment->status;

        $payment->update(['status' => $status] + $extra);

        AuditLogger::log('payment.status_changed', 'payment', $payment->id, ['status' => $old], ['status' => $status]);
    }

    // PAY-10: records why a payment needs a person's attention. Only amounts and references, never secrets.
    private function flag(Payment $payment, array $reasons, array $received = [], array $extra = []): void
    {
        AuditLogger::log('payment.flagged_for_review', 'payment', $payment->id, null, [
            'reasons' => $reasons,
            'tx_ref' => $payment->tx_ref,
            'expected' => [
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'mode' => config('chapa.mode'),
            ],
            'received' => [
                'amount' => isset($received['amount']) ? (string) $received['amount'] : null,
                'currency' => $received['currency'] ?? null,
                'mode' => $received['mode'] ?? null,
            ],
        ] + $extra);
    }

    // PAY-06: every answer from Chapa is kept as one event row, with the raw payload.
    private function recordEvent(Payment $payment, string $type, array $payload): PaymentEvent
    {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            // Internal events have no Chapa id, so each gets a unique one (the column must be unique, DR-02).
            'provider_event_id' => $type.':'.$payment->id.':'.Str::uuid(),
            'event_type' => $type,
            'payload' => $payload,
            'received_at' => now(),
            'processed_at' => now(),
        ]);
    }
}