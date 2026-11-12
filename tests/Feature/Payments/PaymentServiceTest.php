<?php

namespace Tests\Feature\Payments;

use App\Exceptions\ChapaException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
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
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'CHASECK_TEST-fake-key';

    private PaymentService $payments;
    private User $user;
    private Customer $customer;
    private Warehouse $main;
    private Product $water;
    private Sale $sale; // pending, total 25.00

    // What the FAKE Chapa answers. The tests change these.
    private array $initializeBody = [];
    private int $initializeStatus = 200;
    private array $verifyBody = [];
    private int $verifyStatus = 200;
    private bool $chapaDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests(); // a test can NEVER reach the real Chapa
        config([
            'chapa.secret_key' => self::KEY,
            'chapa.mode' => 'test',
            'chapa.retry_delays_ms' => [0, 0],
            'chapa.return_url' => 'https://erp.test/pay/{tx_ref}/return',
            'chapa.placeholder_email' => null,
            'chapa.callback_url' => null,
        ]);

        $this->initializeBody = [
            'message' => 'Hosted Link',
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/checkout/payment/abc123'],
        ];

        $this->payments = app(PaymentService::class);
        $this->user = User::factory()->create();
        $this->customer = Customer::create(['name' => 'Abebe Kebede', 'phone' => '0911000111', 'email' => 'abebe@example.test']);
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

    // The fake Chapa. Call it once in a test that needs HTTP.
    private function fakeChapa(): void
    {
        Http::fake(function ($request) {
            if ($this->chapaDown) {
                throw new ConnectionException('Connection timed out');
            }

            if (str_contains($request->url(), '/transaction/initialize')) {
                return Http::response($this->initializeBody, $this->initializeStatus);
            }

            if (str_contains($request->url(), '/transaction/verify/')) {
                return Http::response($this->verifyBody, $this->verifyStatus);
            }

            return Http::response(['message' => 'unexpected'], 404);
        });
    }

    // 10 x 2.50 = a pending sale of 25.00.
    private function newSale(?Customer $customer = null): Sale
    {
        return app(SaleService::class)->create(($customer ?? $this->customer)->id, [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => '10'],
        ], $this->user->id);
    }

    // A payment row made directly, so verify tests do not need the initialize call.
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

    // Tells the fake Chapa what to answer when this payment is verified. Defaults: a matching success.
    private function remoteSays(Payment $payment, array $data = [], int $http = 200): void
    {
        $this->verifyStatus = $http;
        $this->verifyBody = [
            'message' => 'Payment details',
            'status' => 'success',
            'data' => array_merge([
                'first_name' => 'Abebe',
                'last_name' => 'Kebede',
                'email' => 'abebe@example.test',
                'currency' => 'ETB',
                'amount' => '25.00',
                'charge' => '0.88',
                'mode' => 'test',
                'method' => 'telebirr',
                'type' => 'API',
                'status' => 'success',
                'reference' => 'AP634JFwEbxd',
                'tx_ref' => $payment->tx_ref,
            ], $data),
        ];
    }

    private function lastRequest()
    {
        return Http::recorded()->last()[0];
    }

    private function sentCount(): int
    {
        return Http::recorded()->count();
    }

    private function stockOf(): string
    {
        return (string) InventoryStock::where('product_id', $this->water->id)->where('warehouse_id', $this->main->id)->value('quantity');
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->orderBy('id')->get();
    }

    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_starting_a_payment_saves_a_pending_row_and_sends_the_right_request(): void
    {
        $this->fakeChapa();
        $this->actingAs($this->user);

        $payment = $this->payments->start($this->sale);

        $this->assertSame('pending', $payment->status);
        $this->assertSame('chapa', $payment->provider);
        $this->assertSame('25.00', $payment->fresh()->amount);             // DR-06: exactly the sale's amount
        $this->assertSame('ETB', $payment->currency);
        $this->assertSame('https://checkout.chapa.co/checkout/payment/abc123', $payment->checkout_url);
        $this->assertSame($this->sale->id, (int) $payment->sale_id);
        $this->assertNull($payment->provider_transaction_id);
        $this->assertNull($payment->paid_at);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->method());
        $this->assertSame('https://api.chapa.co/v1/transaction/initialize', $request->url());
        $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.self::KEY));
        $this->assertSame('25.00', $request['amount']);
        $this->assertSame('ETB', $request['currency']);
        $this->assertSame($payment->tx_ref, $request['tx_ref']);
        $this->assertSame('https://erp.test/pay/'.$payment->tx_ref.'/return', $request['return_url']);
        $this->assertSame('Abebe', $request['first_name']);
        $this->assertSame('Kebede', $request['last_name']);
        $this->assertSame('abebe@example.test', $request['email']);
        $this->assertSame('0911000111', $request['phone_number']);

        $rows = $this->audit('payment.created');
        $this->assertCount(1, $rows);
        $this->assertSame($this->user->id, (int) $rows->first()->user_id);
        $this->assertSame($payment->id, (int) $rows->first()->entity_id);
    }

    public function test_every_attempt_gets_its_own_unguessable_tx_ref(): void
    {
        $this->fakeChapa();

        $first = $this->payments->start($this->sale);
        $second = $this->payments->start($this->sale); // a retry creates a NEW row (PAY-03)

        $this->assertNotSame($first->tx_ref, $second->tx_ref);
        $this->assertSame(2, Payment::count());

        foreach ([$first, $second] as $payment) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($this->sale->sale_number, '/').'-[A-Z0-9]{12}$/', $payment->tx_ref);
        }
    }

    public function test_customer_details_are_sent_only_when_chapa_can_accept_them(): void
    {
        $this->fakeChapa();

        // A +251 phone is rewritten to the 09 form; no e-mail means none is sent.
        $this->payments->start($this->newSale(Customer::create(['name' => 'Tigist Haile Mariam', 'phone' => '+251 911 000 111'])));
        $data = $this->lastRequest()->data();
        $this->assertSame('Tigist', $data['first_name']);
        $this->assertSame('Haile Mariam', $data['last_name']);
        $this->assertSame('0911000111', $data['phone_number']);
        $this->assertArrayNotHasKey('email', $data);

        // A phone Chapa cannot take is left out; a one-word name has no last name.
        $this->payments->start($this->newSale(Customer::create(['name' => 'Madonna', 'phone' => 'abc'])));
        $data = $this->lastRequest()->data();
        $this->assertArrayNotHasKey('last_name', $data);
        $this->assertArrayNotHasKey('phone_number', $data);

        // SRS Q07: a configured placeholder e-mail is used when the customer has none.
        config(['chapa.placeholder_email' => 'payments@erp.test']);
        $this->payments->start($this->newSale(Customer::create(['name' => 'Zeta Shop'])));
        $this->assertSame('payments@erp.test', $this->lastRequest()->data()['email']);
    }

    public function test_only_a_pending_sale_with_a_positive_total_can_be_paid(): void
    {
        Http::fake(); // nothing may be sent, so nothing needs a stub

        $paid = $this->newSale();
        Sale::whereKey($paid->id)->update(['status' => Sale::STATUS_PAID]);

        $cancelled = $this->newSale();
        app(SaleService::class)->cancel($cancelled, $this->user->id);

        $free = $this->newSale();
        Sale::whereKey($free->id)->update(['total_amount' => '0.00']);

        foreach ([$paid, $cancelled, $free] as $sale) {
            try {
                $this->payments->start($sale);
                $this->fail('This sale should not be payable.');
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_a_chapa_refusal_when_starting_leaves_no_payment_and_no_secret_in_the_message(): void
    {
        $this->fakeChapa();

        // A 4xx means OUR request is wrong: one attempt only, no retry.
        $this->initializeStatus = 400;
        $this->initializeBody = ['message' => 'Invalid currency', 'status' => 'failed', 'data' => null];

        try {
            $this->payments->start($this->sale);
            $this->fail('Chapa refused, so this should fail.');
        } catch (ChapaException $e) {
            $this->assertStringContainsString('Invalid currency', $e->getMessage());
            $this->assertStringNotContainsString('CHASECK', $e->getMessage());
        }

        $this->assertSame(1, $this->sentCount());
        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $this->sale->fresh()->status); // the sale stays pending

        // A 5xx is Chapa's problem: retried twice (3 attempts), then reported.
        $this->initializeStatus = 500;
        $this->initializeBody = ['message' => 'Server error', 'status' => 'failed'];

        try {
            $this->payments->start($this->sale);
            $this->fail('Chapa is failing, so this should fail.');
        } catch (ChapaException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }

        $this->assertSame(1 + 3, $this->sentCount());
        $this->assertSame(0, Payment::count());
    }

    public function test_a_temporary_chapa_failure_is_retried(): void
    {
        Http::fake([
            'api.chapa.co/v1/transaction/initialize' => Http::sequence()
                ->pushStatus(500)
                ->push($this->initializeBody, 200),
        ]);

        $payment = $this->payments->start($this->sale);

        $this->assertSame('https://checkout.chapa.co/checkout/payment/abc123', $payment->checkout_url);
        Http::assertSentCount(2);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_missing_secret_key_is_reported_without_calling_chapa(): void
    {
        Http::fake();
        config(['chapa.secret_key' => null]);

        try {
            $this->payments->start($this->sale);
            $this->fail('Without a key this should fail.');
        } catch (ChapaException $e) {
            $this->assertStringContainsString('CHAPA_SECRET_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, Payment::count());
    }

    public function test_a_verified_success_marks_the_payment_and_the_sale_paid(): void
    {
        $this->actingAs($this->user);
        $this->fakeChapa();
        $payment = $this->makePayment();
        $this->remoteSays($payment);

        $result = $this->payments->verify($payment);

        $this->assertSame('success', $result->status);
        $this->assertSame('AP634JFwEbxd', $result->provider_transaction_id);
        $this->assertNotNull($result->verified_at);
        $this->assertNotNull($result->paid_at);
        $this->assertSame('paid', $this->sale->fresh()->status);

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->method());
        $this->assertSame('https://api.chapa.co/v1/transaction/verify/'.$payment->tx_ref, $request->url());
        $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.self::KEY));

        // The raw answer is kept as an event (PAY-06).
        $event = PaymentEvent::where('payment_id', $payment->id)->first();
        $this->assertSame('verify_success', $event->event_type);
        $this->assertSame($payment->tx_ref, $event->payload['data']['tx_ref']);
        $this->assertNotNull($event->processed_at);

        $changes = $this->audit('payment.status_changed');
        $this->assertCount(1, $changes);
        $this->assertSame(['status' => 'pending'], $this->values($changes->first(), 'old_values'));
        $this->assertSame(['status' => 'success'], $this->values($changes->first(), 'new_values'));
        $this->assertCount(1, $this->audit('sale.paid'));
        $this->assertCount(0, $this->audit('payment.flagged_for_review'));
    }

    public function test_verifying_twice_changes_nothing_the_second_time(): void
    {
        $this->fakeChapa();
        $payment = $this->makePayment();
        $this->remoteSays($payment);

        $this->payments->verify($payment);
        $again = $this->payments->verify($payment); // the copy in memory still says "pending"

        $this->assertSame('success', $again->status);
        $this->assertSame(1, $this->sentCount());                               // Chapa was not asked again
        $this->assertSame(1, PaymentEvent::count());
        $this->assertCount(1, $this->audit('payment.status_changed'));
        $this->assertCount(1, $this->audit('sale.paid'));
    }

    public function test_a_success_that_does_not_match_the_payment_is_not_accepted_and_is_flagged(): void
    {
        $this->fakeChapa();

        $cases = [
            'amount' => ['amount' => '24.99'],
            'currency' => ['currency' => 'USD'],
            'mode' => ['mode' => 'live'],
            'tx_ref' => ['tx_ref' => 'SOMEONE-ELSES-REFERENCE'],
        ];

        foreach ($cases as $reason => $changes) {
            $payment = $this->makePayment();
            $this->remoteSays($payment, $changes);

            $result = $this->payments->verify($payment);

            $this->assertSame('failed', $result->status, "A wrong {$reason} must not be accepted.");
            $this->assertNull($result->paid_at);
            $this->assertSame('pending', $this->sale->fresh()->status);         // the sale can be paid again

            $flagged = $this->values($this->audit('payment.flagged_for_review')->last(), 'new_values');
            $this->assertContains($reason, $flagged['reasons']);
            $this->assertSame('25.00', $flagged['expected']['amount']);
        }

        $this->assertCount(4, $this->audit('payment.flagged_for_review'));
        $this->assertSame(4, PaymentEvent::where('event_type', 'verify_failed')->count());
    }

    public function test_amounts_are_compared_as_numbers_not_as_text(): void
    {
        $this->fakeChapa();

        foreach (['25', 25, '25.0', '25.00'] as $amount) {
            $sale = $this->newSale();
            $payment = $this->makePayment($sale);
            $this->remoteSays($payment, ['amount' => $amount]);

            $this->assertSame('success', $this->payments->verify($payment)->status);
            $this->assertSame('paid', $sale->fresh()->status);
        }
    }

    public function test_failed_cancelled_and_still_pending_results(): void
    {
        $this->fakeChapa();

        // Still pending at Chapa: nothing changes and nothing is written.
        $waiting = $this->makePayment();
        $this->remoteSays($waiting, ['status' => 'pending']);
        $this->assertSame('pending', $this->payments->verify($waiting)->status);
        $this->assertSame(0, PaymentEvent::count());
        $this->assertCount(0, $this->audit('payment.status_changed'));

        // Failed: the payment fails, but the SALE stays pending (PAY-11) and keeps its stock.
        $failed = $this->makePayment();
        $this->remoteSays($failed, ['status' => 'failed']);
        $this->assertSame('failed', $this->payments->verify($failed)->status);
        $this->assertSame('pending', $this->sale->fresh()->status);
        $this->assertSame('90.000', $this->stockOf());                           // the pending sale still holds its stock
        $this->assertSame(1, PaymentEvent::where('event_type', 'verify_failed')->count());

        // Cancelled, in either spelling.
        foreach (['cancelled', 'canceled'] as $spelling) {
            $payment = $this->makePayment();
            $this->remoteSays($payment, ['status' => $spelling]);
            $this->assertSame('cancelled', $this->payments->verify($payment)->status);
        }

        $this->assertSame('pending', $this->sale->fresh()->status);
        $this->assertCount(0, $this->audit('sale.paid'));
    }

    public function test_a_failed_payment_can_still_succeed_later(): void
    {
        $this->fakeChapa();
        $payment = $this->makePayment();

        $this->remoteSays($payment, ['status' => 'failed']);
        $this->assertSame('failed', $this->payments->verify($payment)->status);

        // The customer retried on the same checkout page and paid: the money DID arrive.
        $this->remoteSays($payment);
        $this->assertSame('success', $this->payments->verify($payment)->status);
        $this->assertSame('paid', $this->sale->fresh()->status);
        $this->assertSame(['verify_failed', 'verify_success'], PaymentEvent::orderBy('id')->pluck('event_type')->all());
    }

    public function test_money_that_arrives_for_a_sale_that_cannot_use_it_is_flagged(): void
    {
        $this->fakeChapa();

        // (a) The sale was cancelled (its stock is already back), and then a payment succeeds.
        app(SaleService::class)->cancel($this->sale, $this->user->id);
        $late = $this->makePayment();
        $this->remoteSays($late);

        $this->assertSame('success', $this->payments->verify($late)->status);   // the money is real
        $this->assertSame('cancelled', $this->sale->fresh()->status);           // but the sale is not revived
        $this->assertSame('100.000', $this->stockOf());                         // and no stock was touched
        $flagged = $this->values($this->audit('payment.flagged_for_review')->last(), 'new_values');
        $this->assertSame(['sale_not_pending'], $flagged['reasons']);
        $this->assertSame('cancelled', $flagged['sale_status']);

        // (b) A second payment succeeds for a sale that is already paid.
        $sale = $this->newSale();
        $first = $this->makePayment($sale);
        $this->remoteSays($first);
        $this->payments->verify($first);
        $this->assertSame('paid', $sale->fresh()->status);

        $second = $this->makePayment($sale);
        $this->remoteSays($second);
        $this->assertSame('success', $this->payments->verify($second)->status);
        $this->assertSame('paid', $sale->fresh()->status);
        $flagged = $this->values($this->audit('payment.flagged_for_review')->last(), 'new_values');
        $this->assertSame(['sale_already_paid'], $flagged['reasons']);
    }

    public function test_chapa_being_unreachable_or_refusing_changes_nothing(): void
    {
        $this->fakeChapa();
        $payment = $this->makePayment();

        $this->chapaDown = true;

        try {
            $this->payments->verify($payment);
            $this->fail('Chapa is down, so this should fail.');
        } catch (ChapaException $e) {
            $this->assertStringContainsString('could not be reached', $e->getMessage());
        }

        $this->chapaDown = false;
        $this->verifyStatus = 404;
        $this->verifyBody = ['message' => 'Transaction not found', 'status' => 'failed', 'data' => null];

        try {
            $this->payments->verify($payment);
            $this->fail('Chapa does not know the transaction, so this should fail.');
        } catch (ChapaException $e) {
            $this->assertStringContainsString('Transaction not found', $e->getMessage());
        }

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, PaymentEvent::count());
        $this->assertCount(0, $this->audit('payment.status_changed'));
        $this->assertSame('pending', $this->sale->fresh()->status);
    }

    public function test_nothing_secret_is_stored(): void
    {
        $this->actingAs($this->user);
        $this->fakeChapa();

        $started = $this->payments->start($this->sale);
        $this->remoteSays($started);
        $this->payments->verify($started);

        $stored = json_encode([
            DB::table('audit_logs')->get(),
            DB::table('payment_events')->get(),
            DB::table('payments')->get(),
        ]);

        $this->assertStringNotContainsString('CHASECK', $stored);
        $this->assertStringNotContainsString(self::KEY, $stored);
    }

    public function test_payment_events_are_append_only(): void
    {
        $event = PaymentEvent::create([
            'payment_id' => $this->makePayment()->id,
            'provider_event_id' => 'evt-1',
            'event_type' => 'charge.success',
            'payload' => ['event' => 'charge.success'],
            'received_at' => now(),
            'processed_at' => null,
        ]);

        try {
            $event->update(['payload' => ['event' => 'tampered']]);
            $this->fail('The payload must never change.');
        } catch (LogicException) {
            $this->assertSame(['event' => 'charge.success'], $event->fresh()->payload);
        }

        $event->refresh(); // drop the refused change that is still sitting on the in-memory copy
        $event->update(['processed_at' => now()]);     // setting "processed_at" ONCE is allowed
        $this->assertNotNull($event->fresh()->processed_at);

        try {
            $event->update(['processed_at' => now()->addHour()]);
            $this->fail('processed_at can be set only once.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $event->delete();
            $this->fail('An event must never be deleted.');
        } catch (LogicException) {
            $this->assertSame(1, PaymentEvent::count());
        }
    }
}