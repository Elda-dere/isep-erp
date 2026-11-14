<?php

namespace Tests\Feature\Payments;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Customer $customer;
    private Sale $sale; // pending, total 25.00
    private Warehouse $main;
    private Product $water;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Http::preventStrayRequests(); // a test can NEVER reach the real Chapa
        config([
            'chapa.secret_key' => 'CHASECK_TEST-fake-key',
            'chapa.mode' => 'test',
            'chapa.retry_delays_ms' => [0, 0],
        ]);

        $this->cashier = $this->userWith('sales.create', 'sales.view');
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

        app(StockService::class)->receive($this->water->id, $this->main->id, '100', $this->cashier->id);
        $this->sale = $this->newSale();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Test role '.uniqid()]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        $user->roles()->attach($role);

        return $user;
    }

    private function newSale(): Sale
    {
        return app(SaleService::class)->create($this->customer->id, [
            ['product_id' => $this->water->id, 'warehouse_id' => $this->main->id, 'quantity' => '10'],
        ], $this->cashier->id);
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
            'checkout_url' => 'https://checkout.chapa.co/checkout/payment/'.Str::random(6),
        ]);
    }

    private function fakeInitialize(int $status = 200, ?array $body = null): void
    {
        Http::fake([
            'api.chapa.co/v1/transaction/initialize' => Http::response($body ?? [
                'message' => 'Hosted Link', 'status' => 'success',
                'data' => ['checkout_url' => 'https://checkout.chapa.co/checkout/payment/abc123'],
            ], $status),
        ]);
    }

    private function fakeVerify(Payment $payment, array $data = []): void
    {
        Http::fake([
            'api.chapa.co/v1/transaction/verify/*' => Http::response([
                'message' => 'Payment details', 'status' => 'success',
                'data' => array_merge([
                    'currency' => 'ETB', 'amount' => '25.00', 'mode' => 'test', 'status' => 'success',
                    'reference' => 'AP634JFwEbxd', 'tx_ref' => $payment->tx_ref,
                ], $data),
            ], 200),
        ]);
    }

    public function test_users_without_the_right_permission_get_403(): void
    {
        Http::fake();
        $payment = $this->makePayment();
        $nothing = $this->userWith('sales.view');
        $viewer = $this->userWith('payments.view');
        $starter = $this->userWith('payments.start');

        foreach ([$nothing, $viewer, $starter] as $user) {
            $this->actingAs($user);
            $this->assertSame($user->id === $viewer->id ? 200 : 403, $this->get('/payments')->getStatusCode());
        }

        $this->actingAs($nothing)->get("/payments/{$payment->id}")->assertForbidden();
        $this->actingAs($nothing)->post("/sales/{$this->sale->id}/payments")->assertForbidden();
        $this->actingAs($nothing)->post("/payments/{$payment->id}/verify")->assertForbidden();

        // Being able to LOOK is not enough to start or re-check a payment.
        $this->actingAs($viewer)->post("/sales/{$this->sale->id}/payments")->assertForbidden();
        $this->actingAs($viewer)->post("/payments/{$payment->id}/verify")->assertForbidden();

        // Being able to start is not enough to browse or re-check.
        $this->actingAs($starter)->get("/payments/{$payment->id}")->assertForbidden();
        $this->actingAs($starter)->post("/payments/{$payment->id}/verify")->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(1, Payment::count());
    }

    public function test_a_cashier_starts_a_payment_and_is_sent_back_to_the_sale_page(): void
    {
        $this->fakeInitialize();
        $starter = $this->userWith('payments.start');

        $this->actingAs($starter)->post("/sales/{$this->sale->id}/payments")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('sales.show', $this->sale));

        $payment = Payment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('25.00', $payment->amount);
        $this->assertSame('https://checkout.chapa.co/checkout/payment/abc123', $payment->checkout_url);
        $this->assertSame('25.00', Http::recorded()->last()[0]['amount']);
    }

    public function test_a_refused_start_shows_the_message_and_saves_nothing(): void
    {
        $starter = $this->userWith('payments.start');

        // Chapa refuses.
        $this->fakeInitialize(400, ['message' => 'Invalid currency', 'status' => 'failed', 'data' => null]);
        $this->actingAs($starter)->post("/sales/{$this->sale->id}/payments")->assertSessionHasErrors('payment');
        $this->assertStringContainsString('Invalid currency', session('errors')->first('payment'));
        $this->assertSame(0, Payment::count());

        // The sale rule refuses (a cancelled sale cannot be paid), and Chapa is not even asked.
        app(SaleService::class)->cancel($this->sale, $this->cashier->id);
        $asked = Http::recorded()->count();
        $this->actingAs($starter)->post("/sales/{$this->sale->id}/payments")->assertSessionHasErrors('payment');
        $this->assertSame($asked, Http::recorded()->count());
        $this->assertSame(0, Payment::count());
    }

    public function test_the_sale_page_lists_its_payments_and_only_a_pending_one_carries_a_link(): void
    {
        $this->makePayment($this->sale, 'success');
        $this->makePayment($this->sale, 'failed');
        $pending = $this->makePayment($this->sale, 'pending');

        $this->actingAs($this->cashier)->get("/sales/{$this->sale->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Show')
                ->has('payments', 3)
                ->where('payments.0.status', 'pending')                     // newest first
                ->where('payments.0.checkout_url', $pending->checkout_url)
                ->where('payments.0.amount', '25.00')                       // exact string
                ->where('payments.1.status', 'failed')
                ->where('payments.1.checkout_url', null)
                ->where('payments.2.status', 'success')
                ->where('payments.2.checkout_url', null));
    }

    public function test_the_payment_list_shows_rows_newest_first_and_can_be_filtered(): void
    {
        $viewer = $this->userWith('payments.view');
        $first = $this->makePayment($this->sale, 'success');
        $second = $this->makePayment($this->newSale(), 'failed');

        $this->actingAs($viewer)->get('/payments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Index')
                ->has('payments.data', 2)
                ->where('payments.data.0.tx_ref', $second->tx_ref)          // newest first
                ->where('payments.data.0.status', 'failed')
                ->where('payments.data.0.amount', '25.00')
                ->where('payments.data.1.sale_number', $this->sale->sale_number)
                ->has('statuses', 5));

        $this->actingAs($viewer)->get('/payments?status=success')
            ->assertInertia(fn (Assert $page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.tx_ref', $first->tx_ref));

        $this->actingAs($viewer)->get('/payments?provider=chapa&status=failed')
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));

        // The date filter uses Addis Ababa days: 22:00 UTC on 5 January is 6 January there.
        Payment::whereKey($first->id)->update(['created_at' => '2026-01-05 22:00:00']);
        $this->actingAs($viewer)->get('/payments?from=2026-01-06&to=2026-01-06')
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.tx_ref', $first->tx_ref));
        $this->actingAs($viewer)->get('/payments?from=2026-01-05&to=2026-01-05')
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 0));
    }

    public function test_invalid_list_filters_are_rejected(): void
    {
        $viewer = $this->userWith('payments.view');

        $this->actingAs($viewer)->get('/payments?status=banana')->assertSessionHasErrors('status');
        $this->actingAs($viewer)->get('/payments?provider=cash')->assertSessionHasErrors('provider');
        $this->actingAs($viewer)->get('/payments?from=2026-01-10&to=2026-01-05')->assertSessionHasErrors('to');
    }

    public function test_the_payment_detail_shows_the_history_and_hides_raw_payloads_without_the_permission(): void
    {
        $payment = $this->makePayment();
        foreach (['charge.success', 'verify_success'] as $index => $type) {
            PaymentEvent::create([
                'payment_id' => $payment->id,
                'provider_event_id' => $type.':'.$index,
                'event_type' => $type,
                'payload' => ['event' => $type, 'tx_ref' => $payment->tx_ref, 'email' => 'abebe@example.test'],
                'received_at' => now(),
                'processed_at' => $index === 1 ? now() : null,
            ]);
        }

        $viewer = $this->userWith('payments.view');
        $auditor = $this->userWith('payments.view', 'payments.view_payload');

        $this->actingAs($viewer)->get("/payments/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Show')
                ->where('payment.tx_ref', $payment->tx_ref)
                ->where('payment.amount', '25.00')
                ->where('payment.sale.sale_number', $this->sale->sale_number)
                ->where('payment.checkout_url', $payment->checkout_url)        // pending: the link is useful
                ->has('events', 2)
                ->where('events.0.event_type', 'charge.success')               // oldest first
                ->where('events.0.processed_at', null)
                ->where('events.0.payload', null)                              // PAY-15: hidden without the permission
                ->where('events.1.payload', null)
                ->where('canViewPayload', false));

        $this->actingAs($auditor)->get("/payments/{$payment->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.payload.tx_ref', $payment->tx_ref)
                ->where('canViewPayload', true));

        $this->actingAs($viewer)->get('/payments/99999')->assertNotFound();
    }

    public function test_the_payment_detail_explains_why_a_payment_was_flagged(): void
    {
        $payment = $this->makePayment();
        $this->fakeVerify($payment, ['amount' => '24.99']); // Chapa says a different amount was paid

        app(PaymentService::class)->verify($payment);

        $viewer = $this->userWith('payments.view');

        $this->actingAs($viewer)->get("/payments/{$payment->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('payment.status', 'failed')
                ->where('payment.checkout_url', null)                          // no longer payable
                ->has('flags', 1)
                ->where('flags.0.reasons', ['amount'])
                ->where('flags.0.expected.amount', '25.00')
                ->where('flags.0.received.amount', '24.99'));
    }

        public function test_re_verifying_settles_a_payment_and_reports_chapa_problems(): void
    {
        $checker = $this->userWith('payments.verify', 'payments.view');
        $payment = $this->makePayment();

        // ONE fake for the whole test, whose behaviour we switch: a second Http::fake() would not replace the first.
        $down = true;
        Http::fake(function () use (&$down, $payment) {
            if ($down) {
                throw new ConnectionException('Connection timed out');
            }

            return Http::response([
                'message' => 'Payment details', 'status' => 'success',
                'data' => [
                    'currency' => 'ETB', 'amount' => '25.00', 'mode' => 'test', 'status' => 'success',
                    'reference' => 'AP634JFwEbxd', 'tx_ref' => $payment->tx_ref,
                ],
            ], 200);
        });

        // Chapa unreachable: a clear message, and nothing changes.
        $this->actingAs($checker)->post("/payments/{$payment->id}/verify")
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHasErrors('verify');
        $this->assertSame('pending', $payment->fresh()->status);

        // Chapa answers: the payment and the sale are settled.
        $down = false;
        $this->actingAs($checker)->post("/payments/{$payment->id}/verify")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('payments.show', $payment));

        $this->assertSame('success', $payment->fresh()->status);
        $this->assertSame('paid', $this->sale->fresh()->status);

        // Settled already: checking again never asks Chapa and changes nothing.
        $asked = Http::recorded()->count();
        $this->actingAs($checker)->post("/payments/{$payment->id}/verify")->assertSessionHasNoErrors();
        $this->assertSame($asked, Http::recorded()->count());
    }
}