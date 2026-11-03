<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GoodsReceiptScreensTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $orders;
    private GoodsReceiptService $receipts;
    private User $clerk;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        // The container builds the services with their dependencies, like the app does.
        $this->orders = app(PurchaseOrderService::class);
        $this->receipts = app(GoodsReceiptService::class);

        $this->clerk = $this->userWith('goods_receipts.manage');
        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->warehouse = Warehouse::create(['name' => 'Main store']);
        $this->water = $this->makeProduct('SKU-001');
        $this->juice = $this->makeProduct('SKU-002');
    }

    // Builds a user whose role has exactly the permissions named.
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

    // A placed order: 10 water and 5 juice.
    private function placedOrder(): PurchaseOrder
    {
        $draft = $this->orders->createDraft($this->supplier->id, [
            ['product_id' => $this->water->id, 'quantity' => '10', 'unit_cost' => '2.50'],
            ['product_id' => $this->juice->id, 'quantity' => '5', 'unit_cost' => '1.00'],
        ], $this->clerk->id);

        return $this->orders->place($draft, $this->clerk->id)->load('items');
    }

    // A draft receipt through the service. $quantities maps the order line index (0 = water, 1 = juice) to a quantity.
    private function draftReceipt(PurchaseOrder $order, array $quantities): GoodsReceipt
    {
        $lines = [];

        foreach ($quantities as $index => $quantity) {
            $lines[] = ['purchase_order_item_id' => $order->items[$index]->id, 'quantity' => $quantity];
        }

        return $this->receipts->createDraft($order, $lines, $this->clerk->id);
    }

    // The data the form sends: every line, in order. A blank quantity means "nothing arrived".
    private function formLines(PurchaseOrder $order, string $water, string $juice = ''): array
    {
        return [
            ['purchase_order_item_id' => $order->items[0]->id, 'quantity' => $water],
            ['purchase_order_item_id' => $order->items[1]->id, 'quantity' => $juice],
        ];
    }

    private function stockOf(Product $product): string
    {
        $quantity = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        return (string) ($quantity ?? '0.000');
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->get();
    }

    public function test_users_without_goods_receipts_manage_get_403_everywhere(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);
        // Other procurement and stock permissions are not enough.
        $other = $this->userWith('purchase_orders.view', 'purchase_orders.manage', 'inventory.adjust');

        $this->actingAs($other)->get('/goods-receipts')->assertForbidden();
        $this->actingAs($other)->get("/goods-receipts/{$receipt->id}")->assertForbidden();
        $this->actingAs($other)->get("/purchase-orders/{$order->id}/receive")->assertForbidden();
        $this->actingAs($other)->post("/purchase-orders/{$order->id}/goods-receipts", ['lines' => $this->formLines($order, '1')])->assertForbidden();
        $this->actingAs($other)->get("/goods-receipts/{$receipt->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/goods-receipts/{$receipt->id}", ['lines' => $this->formLines($order, '1')])->assertForbidden();
        $this->actingAs($other)->post("/goods-receipts/{$receipt->id}/post", ['warehouse_id' => $this->warehouse->id])->assertForbidden();
        $this->actingAs($other)->post("/goods-receipts/{$receipt->id}/cancel")->assertForbidden();

        $this->assertSame(1, GoodsReceipt::count()); // only the draft made above
        $this->assertSame('draft', $receipt->fresh()->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_the_receive_form_shows_ordered_received_and_outstanding_per_line(): void
    {
        $order = $this->placedOrder();
        $first = $this->draftReceipt($order, [0 => '4']);
        $this->receipts->post($first, $this->warehouse->id, $this->clerk->id);

        $this->actingAs($this->clerk)->get("/purchase-orders/{$order->id}/receive")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Procurement/GoodsReceipts/Form')
                ->where('order.order_number', $order->order_number)
                ->where('order.supplier', 'Acme Trading')
                ->where('receipt', null)
                ->has('lines', 2)
                ->where('lines.0.product.sku', 'SKU-001')
                ->where('lines.0.ordered', '10.000')           // exact strings
                ->where('lines.0.received', '4.000')
                ->where('lines.0.outstanding', '6.000')
                ->where('lines.0.quantity', '6.000')           // starts with what is outstanding
                ->where('lines.1.outstanding', '5.000')
                ->where('lines.1.quantity', '5.000'));
    }

    public function test_the_receive_form_is_closed_for_orders_that_are_not_placed(): void
    {
        $draft = $this->orders->createDraft($this->supplier->id, [
            ['product_id' => $this->water->id, 'quantity' => '1', 'unit_cost' => '1'],
        ], $this->clerk->id);

        $this->actingAs($this->clerk)->get("/purchase-orders/{$draft->id}/receive")->assertForbidden();

        $cancelled = $this->orders->cancel($this->placedOrder(), $this->clerk->id);
        $this->actingAs($this->clerk)->get("/purchase-orders/{$cancelled->id}/receive")->assertForbidden();

        $received = $this->placedOrder();
        PurchaseOrder::whereKey($received->id)->update(['status' => 'received']);
        $this->actingAs($this->clerk)->get("/purchase-orders/{$received->id}/receive")->assertForbidden();
    }

    public function test_a_draft_receipt_is_created_from_the_form_and_blank_lines_are_skipped(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->clerk)
            ->post("/purchase-orders/{$order->id}/goods-receipts", ['lines' => $this->formLines($order, '4', '')])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $receipt = GoodsReceipt::first();

        $this->assertSame('draft', $receipt->status);
        $this->assertSame($this->clerk->id, (int) $receipt->received_by);
        $this->assertCount(1, $receipt->items);                  // the blank juice line was skipped
        $this->assertSame('4.000', $receipt->items[0]->quantity_received);
        $this->assertSame(0, StockMovement::count());            // a draft changes no stock

        $rows = $this->audit('goods_receipt.created');
        $this->assertCount(1, $rows);
        $this->assertSame($this->clerk->id, (int) $rows->first()->user_id);
    }

    public function test_the_form_data_is_validated(): void
    {
        $order = $this->placedOrder();
        $item = $order->items[0]->id;

        // Each case: the lines to send and the error key we expect beside the field.
        $cases = [
            [[['purchase_order_item_id' => $item, 'quantity' => '-1']], 'lines.0.quantity'],
            [[['purchase_order_item_id' => $item, 'quantity' => '1.2345']], 'lines.0.quantity'],
            [[['purchase_order_item_id' => $item, 'quantity' => 'abc']], 'lines.0.quantity'],
            [[['purchase_order_item_id' => 99999, 'quantity' => '1']], 'lines.0.purchase_order_item_id'],
        ];

        foreach ($cases as [$lines, $expectedKey]) {
            $this->actingAs($this->clerk)->post("/purchase-orders/{$order->id}/goods-receipts", ['lines' => $lines])
                ->assertSessionHasErrors($expectedKey);
        }

        $this->actingAs($this->clerk)->post("/purchase-orders/{$order->id}/goods-receipts", [])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_a_form_with_only_blank_or_zero_quantities_is_refused(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->clerk)
            ->post("/purchase-orders/{$order->id}/goods-receipts", ['lines' => $this->formLines($order, '', '0')])
            ->assertSessionHasErrors('lines');

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_receiving_more_than_outstanding_is_refused_with_the_available_amount(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->clerk)
            ->post("/purchase-orders/{$order->id}/goods-receipts", ['lines' => $this->formLines($order, '10.001')])
            ->assertSessionHasErrors('lines');

        // Like the stock errors, the message says how much IS available.
        $this->assertStringContainsString('10.000', session('errors')->first('lines'));
        $this->assertSame(0, GoodsReceipt::count());
        $this->assertCount(0, $this->audit('goods_receipt.created'));
    }

    public function test_the_detail_page_of_a_draft_shows_the_lines_without_a_warehouse(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);

        $this->actingAs($this->clerk)->get("/goods-receipts/{$receipt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Procurement/GoodsReceipts/Show')
                ->where('receipt.receipt_number', $receipt->receipt_number)
                ->where('receipt.status', 'draft')
                ->where('receipt.received_by', $this->clerk->name)
                ->where('receipt.order.order_number', $order->order_number)
                ->where('receipt.order.supplier', 'Acme Trading')
                ->has('receipt.lines', 1)
                ->where('receipt.lines.0.product.sku', 'SKU-001')
                ->where('receipt.lines.0.quantity', '4.000')
                ->where('receipt.lines.0.warehouse', null)    // nothing is booked into stock yet
                ->has('warehouses', 1));
    }

    public function test_the_detail_page_of_a_posted_receipt_shows_the_warehouse(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);
        $this->receipts->post($receipt, $this->warehouse->id, $this->clerk->id);

        $this->actingAs($this->clerk)->get("/goods-receipts/{$receipt->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('receipt.status', 'posted')
                ->where('receipt.order.status', 'partially_received')
                ->where('receipt.lines.0.warehouse', 'Main store')); // read from the stock movement (PRC-08)
    }

    public function test_a_draft_can_be_edited_and_a_posted_receipt_cannot(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);

        $this->actingAs($this->clerk)->get("/goods-receipts/{$receipt->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Procurement/GoodsReceipts/Form')
                ->where('receipt.receipt_number', $receipt->receipt_number)
                ->where('lines.0.quantity', '4.000')           // the draft's own quantity
                ->where('lines.1.quantity', ''));              // the juice is not on this draft

        $this->actingAs($this->clerk)
            ->put("/goods-receipts/{$receipt->id}", ['lines' => $this->formLines($order, '3', '2')])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goods_receipts.show', $receipt));

        $this->assertCount(2, $receipt->fresh()->items);
        $this->assertSame('3.000', $receipt->fresh()->items[0]->quantity_received);

        $this->receipts->post($receipt, $this->warehouse->id, $this->clerk->id);

        $this->actingAs($this->clerk)->get("/goods-receipts/{$receipt->id}/edit")->assertForbidden();

        // The service also refuses, even if someone sends the request by hand.
        $this->actingAs($this->clerk)
            ->put("/goods-receipts/{$receipt->id}", ['lines' => $this->formLines($order, '1')])
            ->assertSessionHasErrors('lines');
        $this->assertSame('3.000', $receipt->fresh()->items[0]->quantity_received);
    }

    public function test_posting_adds_stock_updates_the_order_and_is_logged(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);

        $this->actingAs($this->clerk)
            ->post("/goods-receipts/{$receipt->id}/post", ['warehouse_id' => $this->warehouse->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goods_receipts.show', $receipt));

        $this->assertSame('posted', $receipt->fresh()->status);
        $this->assertSame('4.000', $this->stockOf($this->water));
        $this->assertSame('partially_received', $order->fresh()->status);

        $movement = StockMovement::first();
        $this->assertSame('receipt', $movement->movement_type);
        $this->assertSame($receipt->items[0]->id, (int) $movement->goods_receipt_item_id);
        $this->assertSame($this->clerk->id, (int) $movement->performed_by);

        $rows = $this->audit('goods_receipt.posted');
        $this->assertCount(1, $rows);
        $this->assertSame($this->clerk->id, (int) $rows->first()->user_id);
    }

    public function test_posting_needs_an_existing_warehouse(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->draftReceipt($order, [0 => '4']);

        $this->actingAs($this->clerk)->post("/goods-receipts/{$receipt->id}/post", [])
            ->assertSessionHasErrors('warehouse_id');
        $this->actingAs($this->clerk)->post("/goods-receipts/{$receipt->id}/post", ['warehouse_id' => 99999])
            ->assertSessionHasErrors('warehouse_id');

        $this->assertSame('draft', $receipt->fresh()->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_posting_twice_or_over_posting_shows_the_error_and_changes_nothing(): void
    {
        $order = $this->placedOrder();
        $first = $this->draftReceipt($order, [0 => '6']);
        $second = $this->draftReceipt($order, [0 => '6']); // fine on its own: 10 are outstanding

        $this->actingAs($this->clerk)->post("/goods-receipts/{$first->id}/post", ['warehouse_id' => $this->warehouse->id])
            ->assertSessionHasNoErrors();

        // Posting the same receipt again:
        $this->actingAs($this->clerk)->post("/goods-receipts/{$first->id}/post", ['warehouse_id' => $this->warehouse->id])
            ->assertSessionHasErrors('status');
        $this->assertSame('6.000', $this->stockOf($this->water)); // not doubled

        // Posting the second draft: only 4 are outstanding now.
        $this->actingAs($this->clerk)->post("/goods-receipts/{$second->id}/post", ['warehouse_id' => $this->warehouse->id])
            ->assertSessionHasErrors('status');
        $this->assertStringContainsString('4.000', session('errors')->first('status'));

        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame('6.000', $this->stockOf($this->water));
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_draft_can_be_cancelled_but_a_posted_receipt_cannot(): void
    {
        $order = $this->placedOrder();
        $draft = $this->draftReceipt($order, [0 => '4']);
        $posted = $this->draftReceipt($order, [0 => '3']);
        $this->receipts->post($posted, $this->warehouse->id, $this->clerk->id);

        $this->actingAs($this->clerk)->post("/goods-receipts/{$draft->id}/cancel")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goods_receipts.show', $draft));
        $this->assertSame('cancelled', $draft->fresh()->status);
        $this->assertCount(1, $this->audit('goods_receipt.cancelled'));

        $this->actingAs($this->clerk)->post("/goods-receipts/{$posted->id}/cancel")
            ->assertSessionHasErrors('status');
        $this->assertSame('posted', $posted->fresh()->status);
        $this->assertSame('3.000', $this->stockOf($this->water)); // the stock stays: it is corrected by an adjustment
    }

    public function test_the_list_shows_receipts_newest_first(): void
    {
        $first = $this->placedOrder();
        $second = $this->placedOrder();
        $this->draftReceipt($first, [0 => '4']);
        $newest = $this->draftReceipt($second, [0 => '1', 1 => '2']);

        $this->actingAs($this->clerk)->get('/goods-receipts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Procurement/GoodsReceipts/Index')
                ->has('receipts.data', 2)
                ->where('receipts.data.0.receipt_number', $newest->receipt_number) // newest first
                ->where('receipts.data.0.order_number', $second->order_number)
                ->where('receipts.data.0.supplier', 'Acme Trading')
                ->where('receipts.data.0.status', 'draft')
                ->where('receipts.data.0.lines_count', 2)
                ->where('receipts.data.0.received_by', $this->clerk->name)
                ->has('statuses', 3));
    }

    public function test_the_list_can_be_filtered_by_status_and_order(): void
    {
        $orderA = $this->placedOrder();
        $orderB = $this->placedOrder();
        $posted = $this->draftReceipt($orderA, [0 => '4']);
        $this->receipts->post($posted, $this->warehouse->id, $this->clerk->id);
        $draft = $this->draftReceipt($orderB, [0 => '1']);

        $this->actingAs($this->clerk)->get('/goods-receipts?status=posted')
            ->assertInertia(fn (Assert $page) => $page
                ->has('receipts.data', 1)
                ->where('receipts.data.0.receipt_number', $posted->receipt_number));

        $this->actingAs($this->clerk)->get("/goods-receipts?purchase_order_id={$orderB->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('receipts.data', 1)
                ->where('receipts.data.0.receipt_number', $draft->receipt_number));

        // Both filters together: order B has no posted receipt.
        $this->actingAs($this->clerk)->get("/goods-receipts?status=posted&purchase_order_id={$orderB->id}")
            ->assertInertia(fn (Assert $page) => $page->has('receipts.data', 0));

        $this->actingAs($this->clerk)->get('/goods-receipts?status=banana')->assertSessionHasErrors('status');
        $this->actingAs($this->clerk)->get('/goods-receipts?purchase_order_id=99999')->assertSessionHasErrors('purchase_order_id');
    }
}