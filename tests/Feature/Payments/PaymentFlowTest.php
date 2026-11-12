<?php

namespace Tests\Feature\Payments;

use App\Exceptions\ChapaException;
use App\Jobs\ProcessChapaWebhook;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';
    private const KEY = 'CHASECK_TEST-fake-key';

    private User $user;
    private Customer $customer;
    private Warehouse $main;
    private Product $water;
    private Sale $sale; // pending, total 25.00

    // What the FAKE Chapa answers when a payment is verified, by tx_ref.
    private array $answers = [];
    private bool $chapaDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Http::preventStrayRequests(); // a test can NEVER reach the real Chapa
        config([
            'chapa.secret_key' => self::KEY,
            'chapa.webhook_secret' => self::SECRET,
            'chapa.mode' => 'test',
            'chapa.retry_delays_ms' => [0, 0],
            'chapa.reconcile_after_minutes' => 10,
        ]);

        Http::fake(function ($request) {
            if ($this->chapaDown) {
                throw new ConnectionException('Connection timed out');
            }

            $txRef = rawurldecode(Str::afterLast($request->url(), '/'));
            [$body, $status] = $this->answers[$txRef]
                ?? [['message' => 'Transaction not found', 'status' => 'failed', 'data' => null], 404];

            return Http::response($body, $status);
        });

        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Abebe Kebede']);
        $this->main = Warehouse::create(['name' => 'Main store']);
        $this->water = Product::create([
            'category_id' => Category::create(['name' => 'Beverages'])->id,
            'unit_id' => Unit::create(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'cost_price' => '1.00',
            'selling_price' => '2.50',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);

        app(StockService::class)->receive($this->water->id, $this->main->id, '100', $this->user->id);
        $this->sale = $this->newSale();
    }

    private function newSale(): Sale
    {
        return app(SaleService::class)->create($this->customer->id, [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => '10'],
        ], $this->user->id);
    }

    private function makePayment(?Sale $sale = null, string $status = 'pending'): Payment
    {
        $sale ??= $this->sale;

        return Payment::create([
            'sale_id' => $sale->id,
            'provider' => 'chapa',
            'tx_ref' => $sale->sale_number.'-TEST'.strtoupper(Str::random(8)),
            'amount' => (string) $sale->total_amount,
            'currency' => 'ETB',
            'status' => $status,
            'checkout_url' => 'https://checkout.chapa.co/checkout/payment/test',
        ]);
    }

    private function age(Payment $payment, int $minutes = 11): void
    {
        Payment::whereKey($payment->id)->update(['created_at' => now()->subMinutes($minutes)]);
    }

    // Tells the fake Chapa what to answer when this payment is verified. Default: a matching success.
    private function chapaSays(Payment $payment, array $data = []): void
    {
        $this->answers[$payment->tx_ref] = [[
            'message' => 'Payment details',
            'status' => 'success',
            'data' => array_merge([
                'currency' => 'ETB', 'amount' => '25.00', 'mode' => 'test', 'status' => 'success',
                'reference' => 'AP634JFwEbxd', 'tx_ref' => $payment->tx_ref,
            ], $data),
        ], 200];
    }

    private function webhookPayload(Payment $payment, array $changes = []): array
    {
        return array_merge([
            'event' => 'charge.success', 'currency' => 'ETB', 'amount' => '25.00', 'status' => 'success',
            'mode' => 'test', 'reference' => 'AP634JFwEbxd', 'tx_ref' => $payment->tx_ref, 'type' => 'API',
        ], $changes);
    }

    // Sends a webhook, signed (by default) with the payload signature, like Chapa does.
    private function deliver(array $payload, ?string $secret = self::SECRET)
    {
        $raw = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($secret !== null) {
            $server['HTTP_X_CHAPA_SIGNATURE'] = hash_hmac('sha256', $raw, $secret);
        }

        return $this->call('POST', '/webhooks/chapa', [], [], [], $server, $raw);
    }

    private function makeEvent(Payment $payment): PaymentEvent
    {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'provider_event_id' => 'charge.success:'.$payment->tx_ref.':AP1',
            'event_type' => 'charge.success',
            'payload' => $this->webhookPayload($payment),
            'received_at' => now(),
            'processed_at' => null,
        ]);
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->orderBy('id')->get();
    }

    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    // ---------------------------------------------------------------- webhook

    public function test_requests_without_a_valid_signature_are_rejected_and_logged(): void
    {
        Queue::fake();
        $payment = $this->makePayment();
        $raw = json_encode($this->webhookPayload($payment));

        $cases = [
            'no signature' => [],
            'wrong secret' => ['HTTP_X_CHAPA_SIGNATURE' => hash_hmac('sha256', $raw, 'wrong-secret')],
            'signature of another body' => ['HTTP_X_CHAPA_SIGNATURE' => hash_hmac('sha256', $raw.' ', self::SECRET)],
            'wrong plain signature' => ['HTTP_CHAPA_SIGNATURE' => 'nope'],
            // A bad payload signature must NOT fall back to a good plain one.
            'downgrade attempt' => [
                'HTTP_X_CHAPA_SIGNATURE' => 'bad',
                'HTTP_CHAPA_SIGNATURE' => hash_hmac('sha256', self::SECRET, self::SECRET),
            ],
        ];

        foreach ($cases as $name => $headers) {
            $this->call('POST', '/webhooks/chapa', [], [], [], ['CONTENT_TYPE' => 'application/json'] + $headers, $raw)
                ->assertStatus(401);
        }

        $this->assertSame(0, PaymentEvent::count());
        Queue::assertNothingPushed();

        $rows = $this->audit('webhook.rejected');
        $this->assertCount(5, $rows);
        $this->assertNull($rows->first()->user_id);                           // a system action: nobody signed in
        $this->assertStringNotContainsString('AP634JFwEbxd', json_encode($rows)); // the body is not copied into the log
    }

    public function test_a_valid_payload_signature_stores_the_event_and_queues_the_job(): void
    {
        Queue::fake();
        $payment = $this->makePayment();

        $this->deliver($this->webhookPayload($payment))->assertOk()->assertJson(['received' => true]);

        $event = PaymentEvent::first();
        $this->assertSame($payment->id, (int) $event->payment_id);
        $this->assertSame('charge.success', $event->event_type);
        $this->assertSame($payment->tx_ref, $event->payload['tx_ref']);
        $this->assertSame('charge.success:'.$payment->tx_ref.':AP634JFwEbxd', $event->provider_event_id);
        $this->assertNull($event->processed_at);

        Queue::assertPushed(ProcessChapaWebhook::class, fn ($job) => $job->eventId === $event->id);
        $this->assertSame('pending', $payment->fresh()->status); // the webhook alone settles nothing
    }

    public function test_the_plain_signature_is_accepted_only_when_the_payload_signature_is_absent(): void
    {
        Queue::fake();
        $payment = $this->makePayment();
        $raw = json_encode($this->webhookPayload($payment));
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_CHAPA_SIGNATURE' => hash_hmac('sha256', self::SECRET, self::SECRET)];

        $this->call('POST', '/webhooks/chapa', [], [], [], $server, $raw)->assertOk();
        $this->assertSame(1, PaymentEvent::count());

        // Signed with the API key instead of a separate webhook secret (to be confirmed in the sandbox).
        config(['chapa.webhook_secret' => null]);
        $this->deliver($this->webhookPayload($payment, ['reference' => 'OTHER']), self::KEY)->assertOk();
        $this->assertSame(2, PaymentEvent::count());
    }

    public function test_a_repeated_delivery_is_processed_once(): void
    {
        Queue::fake();
        $payment = $this->makePayment();
        $payload = $this->webhookPayload($payment);

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        Queue::assertPushed(ProcessChapaWebhook::class, 2); // never processed yet, so it is queued again

        PaymentEvent::first()->update(['processed_at' => now()]);
        $this->deliver($payload)->assertOk();

        $this->assertSame(1, PaymentEvent::count());
        Queue::assertPushed(ProcessChapaWebhook::class, 2); // processed: nothing more is queued

        // A different event for the same payment is a new row.
        $this->deliver($this->webhookPayload($payment, ['event' => 'charge.refunded']))->assertOk();
        $this->assertSame(2, PaymentEvent::count());
    }

    public function test_events_that_cannot_be_matched_are_ignored_with_a_200(): void
    {
        Queue::fake();

        $this->deliver(['event' => 'charge.success', 'tx_ref' => 'NOT-OURS', 'reference' => 'X1'])->assertOk();
        $this->deliver(['event' => 'payout.success', 'type' => 'Payout', 'reference' => 'MYMER3434989'])->assertOk();

        $raw = 'this is not json';
        $this->call('POST', '/webhooks/chapa', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CHAPA_SIGNATURE' => hash_hmac('sha256', $raw, self::SECRET),
        ], $raw)->assertOk();

        $this->assertSame(0, PaymentEvent::count());
        Queue::assertNothingPushed();
        $this->assertCount(3, $this->audit('webhook.ignored'));
    }

    public function test_the_webhook_route_skips_csrf_and_is_throttled(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.chapa');

        $this->assertContains(PreventRequestForgery::class, $route->excludedMiddleware());
        $this->assertContains('throttle:120,1', $route->gatherMiddleware());
    }

    public function test_the_job_verifies_with_chapa_and_marks_the_event_processed(): void
    {
        $payment = $this->makePayment();
        $this->chapaSays($payment);
        $event = $this->makeEvent($payment);

        (new ProcessChapaWebhook($event->id))->handle(app(PaymentService::class));

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $this->sale->fresh()->status);
        $this->assertNotNull($event->fresh()->processed_at);

        // Run again: already processed, so Chapa is not asked again.
        $asked = Http::recorded()->count();
        (new ProcessChapaWebhook($event->id))->handle(app(PaymentService::class));
        $this->assertSame($asked, Http::recorded()->count());

        // Chapa unreachable: the job FAILS (so the queue retries it) and the event stays unprocessed.
        $other = $this->makePayment($this->newSale());
        $otherEvent = $this->makeEvent($other);
        $this->chapaDown = true;

        try {
            (new ProcessChapaWebhook($otherEvent->id))->handle(app(PaymentService::class));
            $this->fail('The job should fail while Chapa is down.');
        } catch (ChapaException) {
            $this->assertNull($otherEvent->fresh()->processed_at);
            $this->assertSame('pending', $other->fresh()->status);
        }
    }

    public function test_a_valid_webhook_settles_the_payment_end_to_end(): void
    {
        config(['queue.default' => 'sync']); // the job runs at once
        $payment = $this->makePayment();
        $this->chapaSays($payment);

        $this->deliver($this->webhookPayload($payment))->assertOk();

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $this->sale->fresh()->status);
        $this->assertNotNull(PaymentEvent::where('event_type', 'charge.success')->first()->processed_at);
        $this->assertCount(1, $this->audit('sale.paid'));
    }

    // ------------------------------------------------------------ return page

    public function test_the_return_page_is_public_verifies_a_pending_payment_and_shows_only_safe_data(): void
    {
        $payment = $this->makePayment();
        $this->chapaSays($payment);

        $this->get('/pay/'.$payment->tx_ref.'/return') // no login: the customer is not a user
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/PaymentReturn')
                ->where('payment.status', 'success')
                ->where('payment.sale_number', $this->sale->sale_number)
                ->where('payment.amount', '25.00')
                ->where('payment.currency', 'ETB')
                ->has('payment', 4)); // nothing else is revealed (PAY-12)

        $this->assertSame('paid', $this->sale->fresh()->status);
    }

    public function test_a_settled_payment_is_shown_without_asking_chapa(): void
    {
        $payment = $this->makePayment(null, 'success');

        $this->get('/pay/'.$payment->tx_ref.'/return')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('payment.status', 'success'));

        $this->assertSame(0, Http::recorded()->count());
    }

    public function test_the_return_page_survives_chapa_being_down_and_unknown_references_give_404(): void
    {
        $payment = $this->makePayment();
        $this->chapaDown = true;

        $this->get('/pay/'.$payment->tx_ref.'/return')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('payment.status', 'pending'));

        $this->get('/pay/NOT-A-REAL-REFERENCE/return')->assertNotFound();
    }

    // ---------------------------------------------------------- reconciliation

    public function test_old_pending_payments_are_verified_and_young_ones_are_left_alone(): void
    {
        $old = $this->makePayment();
        $this->age($old);
        $this->chapaSays($old);
        $young = $this->makePayment($this->newSale()); // just created

        $result = app(PaymentService::class)->reconcile();

        $this->assertSame(['checked' => 1, 'expired' => 0], $result);
        $this->assertSame('success', $old->fresh()->status);
        $this->assertSame('paid', $this->sale->fresh()->status);
        $this->assertSame('pending', $young->fresh()->status);
        $this->assertSame(1, Http::recorded()->count()); // only the old payment was asked about
    }

    public function test_pending_payments_of_cancelled_sales_expire_unless_they_were_paid(): void
    {
        $sale = $this->newSale();
        $unpaid = $this->makePayment($sale);
        $paidLate = $this->makePayment($sale);
        app(SaleService::class)->cancel($sale, $this->user->id);

        $this->chapaSays($unpaid, ['status' => 'pending']);
        $this->chapaSays($paidLate); // this customer DID pay, just before the sale expired

        $result = app(PaymentService::class)->reconcile();

        $this->assertSame(['checked' => 2, 'expired' => 1], $result);

        $this->assertSame('expired', $unpaid->fresh()->status);
        $this->assertSame('expired', PaymentEvent::where('payment_id', $unpaid->id)->first()->event_type);
        $changes = $this->audit('payment.status_changed')->filter(fn ($row) => (int) $row->entity_id === $unpaid->id);
        $this->assertSame(['status' => 'expired'], $this->values($changes->last(), 'new_values'));

        // Asked FIRST, so the late payment is recorded as money received, flagged, and the sale stays cancelled.
        $this->assertSame('success', $paidLate->fresh()->status);
        $this->assertSame('cancelled', $sale->fresh()->status);
        $flagged = $this->values($this->audit('payment.flagged_for_review')->last(), 'new_values');
        $this->assertSame(['sale_not_pending'], $flagged['reasons']);
    }

    public function test_chapa_being_down_skips_the_payment_and_changes_nothing(): void
    {
        $old = $this->makePayment();
        $this->age($old);

        $sale = $this->newSale();
        $ofCancelled = $this->makePayment($sale);
        app(SaleService::class)->cancel($sale, $this->user->id);

        $this->chapaDown = true;

        $result = app(PaymentService::class)->reconcile();

        $this->assertSame(['checked' => 0, 'expired' => 0], $result);
        $this->assertSame('pending', $old->fresh()->status);
        $this->assertSame('pending', $ofCancelled->fresh()->status); // not expired when we could not even ask
        $this->assertSame(0, PaymentEvent::count());
    }

    public function test_the_command_reports_and_is_scheduled_every_five_minutes(): void
    {
        $old = $this->makePayment();
        $this->age($old);
        $this->chapaSays($old, ['status' => 'pending']);

        $this->artisan('payments:reconcile')
            ->expectsOutputToContain('Checked 1 pending payment(s), expired 0.')
            ->assertSuccessful();

        Artisan::call('schedule:list'); // loads routes/console.php
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'payments:reconcile'));

        $this->assertCount(1, $events, 'payments:reconcile must be scheduled exactly once (routes/console.php).');
        $this->assertSame('*/5 * * * *', $events->first()->expression);
    }
}