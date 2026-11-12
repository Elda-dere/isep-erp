<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessChapaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $eventId)
    {
    }

    // Seconds to wait between attempts when Chapa cannot be reached.
    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    public function handle(PaymentService $payments): void
    {
        $event = PaymentEvent::find($this->eventId);

        if (! $event || $event->processed_at !== null) {
            return; // already done
        }

        $payment = Payment::find($event->payment_id);

        if (! $payment) {
            return;
        }

        // What the webhook says is only a HINT: the real state comes from asking Chapa (PAY-08).
        // If Chapa cannot be reached this throws, and the queue tries again later.
        $payments->verify($payment);

        $event->update(['processed_at' => now()]); // PAY-09: allowed once
    }
}