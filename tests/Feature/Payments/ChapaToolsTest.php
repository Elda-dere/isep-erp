<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChapaToolsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'CHASECK_TEST-super-secret-value';
    private const HOOK = 'whsec_super_secret_hash';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests(); // a test can NEVER reach the real Chapa
        config([
            'chapa.secret_key' => self::SECRET,
            'chapa.webhook_secret' => self::HOOK,
            'chapa.mode' => 'test',
            'chapa.return_url' => 'http://localhost:8000/pay/{tx_ref}/return',
            'app.url' => 'https://erp.example.test',
        ]);
    }

    private function storedEvent(): PaymentEvent
    {
        $sale = Sale::create([
            'sale_number' => 'SAL-TEST-0001',
            'customer_id' => Customer::create(['name' => 'Abebe Kebede'])->id,
            'status' => Sale::STATUS_PENDING,
            'total_amount' => '25.00',
            'currency' => 'ETB',
            'created_by' => User::factory()->create()->id,
        ]);

        $payment = Payment::create([
            'sale_id' => $sale->id, 'provider' => 'chapa', 'tx_ref' => 'SAL-TEST-0001-ABC', 'amount' => '25.00',
            'currency' => 'ETB', 'status' => 'pending', 'checkout_url' => 'https://checkout.chapa.co/x',
        ]);

        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'provider_event_id' => 'charge.success:SAL-TEST-0001-ABC:AP1',
            'event_type' => 'charge.success',
            'payload' => ['event' => 'charge.success', 'tx_ref' => 'SAL-TEST-0001-ABC', 'amount' => '25.00', 'reference' => 'AP1'],
            'received_at' => now(),
        ]);
    }

    public function test_the_check_shows_the_webhook_address_and_never_prints_a_secret(): void
    {
        $this->assertSame(0, Artisan::call('chapa:check'));
        $output = Artisan::output();

        $this->assertStringContainsString('https://erp.example.test/webhooks/chapa', $output);
        $this->assertStringContainsString('Settings look complete', $output);
        $this->assertStringNotContainsString(self::SECRET, $output);
        $this->assertStringNotContainsString(self::HOOK, $output);
    }

    public function test_the_check_fails_and_says_what_is_missing(): void
    {
        config(['chapa.secret_key' => null, 'chapa.return_url' => 'http://localhost/no-reference']);

        $this->assertSame(1, Artisan::call('chapa:check'));
        $output = Artisan::output();

        $this->assertStringContainsString('CHAPA_SECRET_KEY is missing', $output);
        $this->assertStringContainsString('{tx_ref}', $output);
    }

    public function test_the_replay_sends_the_stored_message_signed_like_chapa(): void
    {
        Http::fake(['*' => Http::response(['received' => true], 200)]);
        $event = $this->storedEvent();

        $this->artisan('chapa:replay', ['event' => $event->id])
            ->expectsOutputToContain('HTTP 200')
            ->assertSuccessful();

        // The database may reorder the keys of a JSON column, so the expected text is built from what is stored.
        $raw = json_encode($event->fresh()->payload);

        Http::assertSent(fn ($request) => $request->url() === 'https://erp.example.test/webhooks/chapa'
            && $request->body() === $raw
            && $request->header('x-chapa-signature') === [hash_hmac('sha256', $raw, self::HOOK)]);
    }

    public function test_the_replay_refuses_to_run_in_production_and_for_unknown_events(): void
    {
        Http::fake();
        $event = $this->storedEvent();

        $this->artisan('chapa:replay', ['event' => 99999])->assertFailed(); // unknown event

        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('chapa:replay', ['event' => $event->id])
            ->expectsOutputToContain('development only')
            ->assertFailed();

        Http::assertNothingSent();
    }
}