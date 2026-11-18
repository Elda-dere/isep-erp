<?php

namespace App\Console\Commands;

use App\Models\PaymentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ReplayChapaWebhook extends Command
{
    protected $signature = 'chapa:replay {event : The id of a stored payment event} {--url= : Where to send it (default: this app\'s webhook address)}';

    protected $description = 'DEVELOPMENT ONLY: send a stored Chapa webhook to this app again, signed like Chapa signs it';

    public function handle(): int
    {
        // This pretends to be Chapa, so it must never exist on a real server.
        if (app()->isProduction()) {
            $this->error('This command is for development only.');

            return self::FAILURE;
        }

        $secret = config('chapa.webhook_secret') ?: config('chapa.secret_key');

        if (! is_string($secret) || $secret === '') {
            $this->error('Set CHAPA_WEBHOOK_SECRET (or CHAPA_SECRET_KEY) in the .env file first.');

            return self::FAILURE;
        }

        $event = PaymentEvent::find($this->argument('event'));

        if (! $event || ! is_array($event->payload) || ! isset($event->payload['tx_ref'])) {
            $this->error('That event does not exist, or it is not a Chapa webhook message.');

            return self::FAILURE;
        }

        // Sign exactly the text we send, the way Chapa signs the body of a webhook (HMAC-SHA256).
        $raw = json_encode($event->payload);
        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/webhooks/chapa';

        $response = Http::withBody($raw, 'application/json')
            ->withHeaders(['x-chapa-signature' => hash_hmac('sha256', $raw, $secret)])
            ->timeout(30)
            ->post($url);

        $this->info("Sent event {$event->id} to {$url}: HTTP {$response->status()}");

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}