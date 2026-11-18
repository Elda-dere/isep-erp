<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckChapa extends Command
{
    protected $signature = 'chapa:check';

    protected $description = 'Check the Chapa settings and show the webhook address (no secret is ever printed)';

    public function handle(): int
    {
        $problems = [];

        $this->line('Chapa settings (secrets are never printed)');

        // A secret is reported only as "set" and its length, never its value.
        $key = config('chapa.secret_key');
        $this->line('  Secret key ........ '.($this->filled($key) ? 'set ('.strlen($key).' characters)' : 'MISSING'));

        if (! $this->filled($key)) {
            $problems[] = 'CHAPA_SECRET_KEY is missing from the .env file.';
        }

        $hook = config('chapa.webhook_secret');
        $this->line('  Webhook secret .... '.($this->filled($hook) ? 'set ('.strlen($hook).' characters)' : 'not set (the secret key is used instead)'));

        $mode = (string) config('chapa.mode');
        $this->line("  Mode .............. {$mode}");

        if (app()->isProduction() && $mode !== 'live') {
            $problems[] = 'This is a production server but CHAPA_MODE is not "live".';
        }

        $this->line('  API address ....... '.config('chapa.base_url'));

        $return = (string) config('chapa.return_url');
        $this->line("  Return page ....... {$return}");

        if (! str_contains($return, '{tx_ref}')) {
            $problems[] = 'CHAPA_RETURN_URL must contain {tx_ref}.';
        }

        $webhook = rtrim((string) config('app.url'), '/').'/webhooks/chapa';
        $this->line("  Webhook address ... {$webhook}   <- paste this into the Chapa dashboard");

        if (! str_starts_with($webhook, 'https://') || preg_match('#^https://(localhost|127\.0\.0\.1)#', $webhook)) {
            $this->warn('  Chapa cannot reach this address from the internet. For a test, use a tunnel and set APP_URL to its https address.');
        }

        if (config('queue.default') === 'sync') {
            $this->line('  Queue ............. sync (webhooks are verified inside the request: fine for testing, production needs a queue worker)');
        }

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $this->info('Settings look complete.');

        return self::SUCCESS;
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}