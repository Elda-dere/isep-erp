<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderListScreensTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private User $manager;
    private Supplier $supplier;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->service = new PurchaseOrderService();
        $this->manager = $this->userWith('purchase_orders.view', 'purchase_orders.manage');
        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->water = $this->makeProduct('SKU-001');
        $this->juice = $this->makeProduct('SKU-002');
    }

    // Builds a user whose role has exactly the permissions named.
    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Test role ' . uniqid()]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        $user->roles()->attach($role);

        return $user;
    }

    private function makeProduct(string $sku): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
    }

    // A draft through the service. Default lines: 10 x 2.50 = 25.00 and 0.010 x 0.50 = 0.01 (half-up), total 25.01.
    private function draft(?Supplier $supplier = null, ?array $lines = null): PurchaseOrder
    {
        return $this->service->createDraft(
            ($supplier ?? $this->supplier)->id,
            $lines ?? [
                ['product_id' => $this->water->id, 'quantity' => '10', 'unit_cost' => '2.50'],
                ['product_id' => $this->juice->id, 'quantity' => '0.010', 'unit_cost' => '0.50'],
            ],
            $this->manager->id,
        );
    }

    // Writes a goods receipt directly, because the receipts slice does not exist yet.
    private function receipt(PurchaseOrder $order, string $status, string $number): int
    {
        return DB::table('goods_receipts')->insertGetId([
            'purchase_order_id' => $order->id,
            'receipt_number' => $number,
            'received_by' => $this->manager->id,
            'received_at' => now(),
            'status' => $status,
        ]);
    }

    private function receiptLine(int $receiptId, $item, string $quantity): void
    {
        DB::table('goods_receipt_items')->insert([
            'goods_receipt_id' => $receiptId,
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity_received' => $quantity,
        ]);
    }

    private function statusChanges(): int
    {
        return DB::table('audit_logs')->where('action', 'purchase_order.status_changed')->count();
    }

    public function test_users_without_view_cannot_open_the_list_or_the_detail(): void
    {
        $order = $this->draft();
        $other = $this->userWith('suppliers.manage'); // other procurement permissions are not enough

        $this->actingAs($other)->get('/purchase-orders')->assertForbidden();
        $this->actingAs($other)->get("/purchase-orders/{$order->id}")->assertForbidden();
    }

    public function test_a_viewer_can_look_but_cannot_place_or_cancel(): void
    {
        $order = $this->draft();
        $viewer = $this->userWith('purchase_orders.view');

        $this->actingAs($viewer)->get('/purchase-orders')->assertOk();
        $this->actingAs($viewer)->get("/purchase-orders/{$order->id}")->assertOk();

        $this->actingAs($viewer)->post("/purchase-orders/{$order->id}/place")->assertForbidden();
        $this->actingAs($viewer)->post("/purchase-orders/{$order->id}/cancel")->assertForbidden();

        $this->assertSame('draft', $order->fresh()->status);
        $this->assertSame(0, $this->statusChanges());
    }

    public function test_the_list_shows_orders_newest_first_with_exact_totals(): void
    {
        $this->draft();
        $other = Supplier::create(['name' => 'Other Supplier']);
        $second = $this->draft($other, [['product_id' => $this->water->id, 'quantity' => '3', 'unit_cost' => '4.00']]);

        $this->actingAs($this->manager)->get('/purchase-orders')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/PurchaseOrders/Index')
                ->has('orders.data', 2)
                ->where('orders.data.0.order_number', $second->order_number) // newest first
                ->where('orders.data.0.supplier', 'Other Supplier')
                ->where('orders.data.0.status', 'draft')
                ->where('orders.data.0.lines_count', 1)
                ->where('orders.data.0.total', '12.00')                     // exact strings
                ->where('orders.data.1.lines_count', 2)
                ->where('orders.data.1.total', '25.01')
                ->has('suppliers', 2)
                ->has('statuses', 5));
    }

    public function test_the_list_can_be_filtered_by_status_and_supplier(): void
    {
        $draft = $this->draft();
        $other = Supplier::create(['name' => 'Other Supplier']);
        $placed = $this->service->place(
            $this->draft($other, [['product_id' => $this->water->id, 'quantity' => '3', 'unit_cost' => '4.00']]),
            $this->manager->id,
        );

        $this->actingAs($this->manager)->get('/purchase-orders?status=ordered')
            ->assertInertia(fn(Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $placed->order_number));

        $this->actingAs($this->manager)->get("/purchase-orders?supplier_id={$this->supplier->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $draft->order_number));

        // Both filters together: Acme has no ordered order.
        $this->actingAs($this->manager)->get("/purchase-orders?status=ordered&supplier_id={$this->supplier->id}")
            ->assertInertia(fn(Assert $page) => $page->has('orders.data', 0));
    }

    public function test_the_list_date_filter_uses_addis_ababa_business_days(): void
    {
        $order = $this->draft();

        // 22:00 UTC on 5 January is 01:00 on 6 January in Addis Ababa (UTC+3).
        PurchaseOrder::whereKey($order->id)->update(['created_at' => '2026-01-05 22:00:00']);

        $this->actingAs($this->manager)->get('/purchase-orders?from=2026-01-06&to=2026-01-06')
            ->assertInertia(fn(Assert $page) => $page->has('orders.data', 1));

        $this->actingAs($this->manager)->get('/purchase-orders?from=2026-01-05&to=2026-01-05')
            ->assertInertia(fn(Assert $page) => $page->has('orders.data', 0));
    }

    public function test_an_unknown_status_filter_is_rejected(): void
    {
        $this->actingAs($this->manager)->get('/purchase-orders?status=banana')
            ->assertSessionHasErrors('status');
    }

    public function test_the_detail_page_shows_the_lines_with_exact_values(): void
    {
        $order = $this->draft();

        $this->actingAs($this->manager)->get("/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->where('order.order_number', $order->order_number)
                ->where('order.status', 'draft')
                ->where('order.supplier.name', 'Acme Trading')
                ->where('order.created_by', $this->manager->name)
                ->where('order.ordered_at', null)
                ->where('order.total', '25.01')
                ->has('order.lines', 2)
                ->where('order.lines.0.product.sku', 'SKU-001')
                ->where('order.lines.0.quantity', '10.000')
                ->where('order.lines.0.unit_cost', '2.50')
                ->where('order.lines.0.line_total', '25.00')
                ->where('order.lines.0.received', '0.000')
                ->where('order.lines.0.outstanding', '10.000')
                ->where('order.lines.1.line_total', '0.01'));          // 0.005 rounds half-up
    }

    public function test_outstanding_quantities_count_only_posted_receipts(): void
    {
        $order = $this->service->place($this->draft(), $this->manager->id);
        $water = $order->items[0];

        // 4 received on a POSTED receipt, 3 on a DRAFT receipt (which has not touched any stock).
        $this->receiptLine($this->receipt($order, 'posted', 'GRN-TEST-0001'), $water, '4.000');
        $this->receiptLine($this->receipt($order, 'draft', 'GRN-TEST-0002'), $water, '3.000');

        $this->actingAs($this->manager)->get("/purchase-orders/{$order->id}")
            ->assertInertia(fn(Assert $page) => $page
                ->where('order.lines.0.received', '4.000')
                ->where('order.lines.0.outstanding', '6.000')
                ->where('order.lines.1.received', '0.000')
                ->where('order.lines.1.outstanding', '0.010'));
    }

    public function test_a_manager_can_place_a_draft_order(): void
    {
        $order = $this->draft();

        $this->actingAs($this->manager)->post("/purchase-orders/{$order->id}/place")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertSame('ordered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ordered_at);
        $this->assertSame(1, $this->statusChanges());
    }

    public function test_placing_twice_shows_the_error_and_changes_nothing(): void
    {
        $order = $this->draft();
        $this->actingAs($this->manager)->post("/purchase-orders/{$order->id}/place");

        $this->actingAs($this->manager)->post("/purchase-orders/{$order->id}/place")
            ->assertSessionHasErrors('status');

        $this->assertSame('ordered', $order->fresh()->status);
        $this->assertSame(1, $this->statusChanges()); // the refused attempt is not logged
    }

    public function test_a_manager_can_cancel_a_draft_order(): void
    {
        $order = $this->draft();

        $this->actingAs($this->manager)->post("/purchase-orders/{$order->id}/cancel")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase_orders.show', $order));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1, $this->statusChanges());
    }

    public function test_an_order_with_posted_receipts_cannot_be_cancelled_from_the_screen(): void
    {
        $order = $this->service->place($this->draft(), $this->manager->id);
        $this->receiptLine($this->receipt($order, 'posted', 'GRN-TEST-0001'), $order->items[0], '1.000');

        $this->actingAs($this->manager)->post("/purchase-orders/{$order->id}/cancel")
            ->assertSessionHasErrors('status');

        $this->assertSame('ordered', $order->fresh()->status);
        $this->assertSame(1, $this->statusChanges()); // only the placing was logged
    }

    public function test_the_create_page_still_opens_next_to_the_detail_route(): void
    {
        // "create" must never be read as an order number.
        $this->actingAs($this->manager)->get('/purchase-orders/create')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->component('Procurement/PurchaseOrders/Form'));
    }
}
