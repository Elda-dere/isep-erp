<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class ChapaWebhookService
{
    // PAY-05: is this request really from Chapa? Everything is compared in constant time (hash_equals).
    public function isAuthentic(string $rawBody, ?string $payloadSignature, ?string $plainSignature): bool
    {
        $secrets = array_values(array_filter(
            [config('chapa.webhook_secret'), config('chapa.secret_key')],
            fn ($secret) => is_string($secret) && $secret !== ''
        ));

        // The signature of the PAYLOAD is the strong one: it also proves the body was not changed.
        // If it is present, it alone decides (no falling back to the weaker header).
        if ($payloadSignature !== null && $payloadSignature !== '') {
            foreach ($secrets as $secret) {
                if (hash_equals(hash_hmac('sha256', $rawBody, $secret), strtolower(trim($payloadSignature)))) {
                    return true;
                }
            }

            return false;
        }

        // The "plain" signature only proves the sender knows the secret, not what the body says.
        // That is acceptable only because every webhook is re-checked with Chapa's API before anything happens.
        if ($plainSignature !== null && $plainSignature !== '') {
            foreach ($secrets as $secret) {
                if (hash_equals(hash_hmac('sha256', $secret, $secret), strtolower(trim($plainSignature)))) {
                    return true;
                }
            }
        }

        return false;
    }

    // PAY-06 / PAY-07: stores the event ONCE. Returns the event to process, or null when there is nothing to do.
    public function store(array $payload): ?PaymentEvent
    {
        $txRef = $payload['tx_ref'] ?? null;

        // Payout events and pings carry no tx_ref: nothing of ours to settle.
        if (! is_string($txRef) || $txRef === '') {
            AuditLogger::log('webhook.ignored', null, null, null, ['reason' => 'no_tx_ref', 'event' => $payload['event'] ?? null]);

            return null;
        }

        $payment = Payment::where('tx_ref', $txRef)->first();

        if (! $payment) {
            AuditLogger::log('webhook.ignored', null, null, null, ['reason' => 'unknown_tx_ref', 'event' => $payload['event'] ?? null]);

            return null;
        }

        $type = Str::limit((string) ($payload['event'] ?? 'unknown'), 80, '');

        // Chapa sends no event id, so the key is made of the event type, the tx_ref and Chapa's reference.
        $key = $type.':'.$txRef.':'.(string) ($payload['reference'] ?? '');
        $key = strlen($key) > 150 ? hash('sha256', $key) : $key;

        try {
            return PaymentEvent::create([
                'payment_id' => $payment->id,
                'provider_event_id' => $key,
                'event_type' => $type,
                'payload' => $payload,
                'received_at' => now(),
                'processed_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A repeated delivery. If the first one was never processed (its job may have failed), try again.
            $existing = PaymentEvent::where('provider_event_id', $key)->first();

            return $existing && $existing->processed_at === null ? $existing : null;
        }
    }
}