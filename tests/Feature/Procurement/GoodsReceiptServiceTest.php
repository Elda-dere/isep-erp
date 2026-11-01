<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class GoodsReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $orders;
    private GoodsReceiptService $receipts;
    private Supplier $supplier;
    private User $user;
    private Warehouse $warehouse;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = new PurchaseOrderService();
        $this->receipts = new GoodsReceiptService(new StockService(), $this->orders);
        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::create(['name' => 'Main store']);
        // Water is created first, so it has the lower id: postings send it to the stock first.
        $this->water = $this->makeProduct('SKU-001');
        $this->juice = $this->makeProduct('SKU-002');
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
        ], $this->user->id);

        return $this->orders->place($draft, $this->user->id)->load('items');
    }

    // One receipt line for the order line at $index (0 = water, 1 = juice).
    private function line(PurchaseOrder $order, int $index, string $quantity): array
    {
        return ['purchase_order_item_id' => $order->items[$index]->id, 'quantity' => $quantity];
    }

    // The quantity of a product in the test warehouse, as an exact string.
    private function stockOf(Product $product): string
    {
        $quantity = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        return (string) ($quantity ?? '0.000');
    }

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->orderBy('id')->get();
    }

    // The JSON columns come back as text: turn them into arrays.
    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_a_draft_receipt_is_created_with_a_number_receiver_and_lines(): void
    {
        $order = $this->placedOrder();

        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);

        $this->assertSame('draft', $receipt->status);
        $this->assertSame($order->id, (int) $receipt->purchase_order_id);
        $this->assertSame($this->user->id, (int) $receipt->received_by);
        $this->assertNotNull($receipt->fresh()->received_at);
        $this->assertMatchesRegularExpression('/^GRN-\d{4}-0001$/', $receipt->receipt_number);

        $this->assertCount(1, $receipt->items);
        $this->assertSame($this->water->id, (int) $receipt->items[0]->product_id); // taken from the order line
        $this->assertSame('4.000', $receipt->items[0]->fresh()->quantity_received);

        // A draft does not touch the stock.
        $this->assertSame(0, StockMovement::count());
    }

    public function test_receipt_numbers_count_up_and_follow_the_addis_ababa_year(): void
    {
        $order = $this->placedOrder();
        $line = [$this->line($order, 0, '1')];

        Carbon::setTestNow('2026-03-01 10:00:00');

        try {
            $this->assertSame('GRN-2026-0001', $this->receipts->createDraft($order, $line, $this->user->id)->receipt_number);
            $this->assertSame('GRN-2026-0002', $this->receipts->createDraft($order, $line, $this->user->id)->receipt_number);

            // 22:00 UTC on 31 December is already 1 January in Addis Ababa (UTC+3): a new business year.
            Carbon::setTestNow('2026-12-31 22:00:00');
            $this->assertSame('GRN-2027-0001', $this->receipts->createDraft($order, $line, $this->user->id)->receipt_number);
        } finally {
            Carbon::setTestNow(); // always put the clock back, even if an assertion fails
        }
    }

    public function test_goods_can_be_received_only_against_a_placed_order(): void
    {
        $order = $this->placedOrder();
        $line = [$this->line($order, 0, '1')];

        foreach (['draft', 'cancelled', 'received'] as $status) {
            PurchaseOrder::whereKey($order->id)->update(['status' => $status]);

            try {
                $this->receipts->createDraft($order, $line, $this->user->id);
                $this->fail("A {$status} order should not accept receipts.");
            } catch (DomainException) {
                $this->assertSame(0, GoodsReceipt::count());
            }
        }

        // A partially received order still accepts receipts.
        PurchaseOrder::whereKey($order->id)->update(['status' => 'partially_received']);
        $this->assertSame('draft', $this->receipts->createDraft($order, $line, $this->user->id)->status);
    }

    public function test_lines_are_validated(): void
    {
        $order = $this->placedOrder();
        $otherOrder = $this->placedOrder(); // its lines do not belong to $order

        $bad = [
            'no lines' => [],
            'zero quantity' => [$this->line($order, 0, '0')],
            'negative quantity' => [$this->line($order, 0, '-1')],
            'four decimals' => [$this->line($order, 0, '1.2345')],
            'not a number' => [$this->line($order, 0, 'abc')],
            'line of another order' => [$this->line($otherOrder, 0, '1')],
            'same line twice' => [$this->line($order, 0, '1'), $this->line($order, 0, '2')],
        ];

        foreach ($bad as $name => $lines) {
            try {
                $this->receipts->createDraft($order, $lines, $this->user->id);
                $this->fail("This should be refused: {$name}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, GoodsReceipt::count());
    }

    public function test_a_draft_cannot_receive_more_than_is_outstanding(): void
    {
        $order = $this->placedOrder();

        try {
            $this->receipts->createDraft($order, [$this->line($order, 0, '10.001')], $this->user->id);
            $this->fail('Receiving more than ordered should be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('10.000', $e->getMessage()); // says how much is outstanding
            $this->assertSame(0, GoodsReceipt::count());
        }

        // Exactly the ordered quantity is fine.
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '10')], $this->user->id);
        $this->assertSame('10.000', $receipt->items[0]->fresh()->quantity_received);
    }

    public function test_posting_adds_stock_with_one_receipt_movement_per_line(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4'), $this->line($order, 1, '5')], $this->user->id);
        $poster = User::factory()->create();

        $posted = $this->receipts->post($receipt, $this->warehouse->id, $poster->id);

        $this->assertSame('posted', $posted->fresh()->status);
        $this->assertSame('4.000', $this->stockOf($this->water));
        $this->assertSame('5.000', $this->stockOf($this->juice));

        $movements = StockMovement::where('movement_type', 'receipt')->orderBy('product_id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame($receipt->items[0]->id, (int) $movements[0]->goods_receipt_item_id);
        $this->assertSame($this->warehouse->id, (int) $movements[0]->warehouse_id); // the warehouse lives only here (PRC-08)
        $this->assertSame($poster->id, (int) $movements[0]->performed_by);
        $this->assertSame('4.000', $movements[0]->fresh()->quantity);
    }

    public function test_receipts_move_the_order_to_partially_received_and_then_received(): void
    {
        $order = $this->placedOrder();

        $first = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);
        $this->receipts->post($first, $this->warehouse->id, $this->user->id);
        $this->assertSame('partially_received', $order->fresh()->status);

        $received = $this->orders->receivedQuantities($order);
        $this->assertSame('4.000', (string) $received[$order->items[0]->id]);
        $this->assertArrayNotHasKey($order->items[1]->id, $received); // nothing of the juice yet

        // The rest: 6 more water and all 5 juice.
        $second = $this->receipts->createDraft($order, [$this->line($order, 0, '6'), $this->line($order, 1, '5')], $this->user->id);
        $this->receipts->post($second, $this->warehouse->id, $this->user->id);

        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('10.000', $this->stockOf($this->water));
        $this->assertSame('5.000', $this->stockOf($this->juice));
    }

    public function test_posting_checks_the_outstanding_quantity_again(): void
    {
        $order = $this->placedOrder();

        // Two drafts of 6 each look fine on their own (10 are outstanding) ...
        $first = $this->receipts->createDraft($order, [$this->line($order, 0, '6')], $this->user->id);
        $second = $this->receipts->createDraft($order, [$this->line($order, 0, '6')], $this->user->id);

        $this->receipts->post($first, $this->warehouse->id, $this->user->id);

        // ... but once the first is posted, only 4 are outstanding, so the second must be refused.
        try {
            $this->receipts->post($second, $this->warehouse->id, $this->user->id);
            $this->fail('The second receipt should be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('4.000', $e->getMessage());
        }

        $this->assertSame('draft', $second->fresh()->status);
        $this->assertSame('6.000', $this->stockOf($this->water));
        $this->assertSame(1, StockMovement::count());
        $this->assertSame('partially_received', $order->fresh()->status);
    }

    public function test_only_a_draft_receipt_can_be_posted_and_never_twice(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);
        $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);

        try {
            // $receipt in memory still says "draft", but the service reads the real status under a lock.
            $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);
            $this->fail('A posted receipt cannot be posted again.');
        } catch (DomainException) {
            $this->assertSame('4.000', $this->stockOf($this->water)); // not doubled
            $this->assertSame(1, StockMovement::count());
        }
    }

    public function test_posting_needs_a_placed_order_and_an_existing_warehouse(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);

        try {
            $this->receipts->post($receipt, 99999, $this->user->id);
            $this->fail('An unknown warehouse should be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame('draft', $receipt->fresh()->status);
        }

        // The order is cancelled after the draft was written (allowed: nothing was received yet).
        $this->orders->cancel($order, $this->user->id);

        try {
            $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);
            $this->fail('A cancelled order should not receive goods.');
        } catch (DomainException) {
            $this->assertSame('draft', $receipt->fresh()->status);
            $this->assertSame(0, StockMovement::count());
        }
    }

    public function test_a_failed_posting_changes_nothing(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4'), $this->line($order, 1, '5')], $this->user->id);

        // Make the SECOND line fail: pretend its stock was already booked (StockService refuses repeats).
        StockMovement::create([
            'product_id' => $this->juice->id,
            'warehouse_id' => $this->warehouse->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'quantity' => '5.000',
            'goods_receipt_item_id' => $receipt->items[1]->id,
            'performed_by' => $this->user->id,
        ]);

        try {
            $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);
            $this->fail('The posting should fail.');
        } catch (DomainException) {
            // The first line (water) had already been received when the second one failed:
            // all of it is undone, so no stock row, no extra movement, and nothing changed state.
            $this->assertSame(0, InventoryStock::count());
            $this->assertSame(1, StockMovement::count()); // only the one we added by hand
            $this->assertSame('draft', $receipt->fresh()->status);
            $this->assertSame('ordered', $order->fresh()->status);
            $this->assertCount(0, $this->audit('goods_receipt.posted'));
        }
    }

    public function test_a_draft_receipt_can_be_cancelled_but_a_posted_one_cannot(): void
    {
        $order = $this->placedOrder();
        $draft = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);

        $this->receipts->cancel($draft, $this->user->id);
        $this->assertSame('cancelled', $draft->fresh()->status);

        try {
            $this->receipts->post($draft, $this->warehouse->id, $this->user->id);
            $this->fail('A cancelled receipt cannot be posted.');
        } catch (DomainException) {
            $this->assertSame(0, StockMovement::count());
        }

        $posted = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);
        $this->receipts->post($posted, $this->warehouse->id, $this->user->id);

        try {
            $this->receipts->cancel($posted, $this->user->id); // mistakes are fixed with a stock adjustment instead
            $this->fail('A posted receipt cannot be cancelled.');
        } catch (DomainException) {
            $this->assertSame('posted', $posted->fresh()->status);
            $this->assertSame('4.000', $this->stockOf($this->water));
        }
    }

    public function test_a_draft_can_be_edited_but_a_posted_receipt_cannot(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '4')], $this->user->id);

        $updated = $this->receipts->updateDraft($receipt, [$this->line($order, 0, '3'), $this->line($order, 1, '2')], $this->user->id);

        $this->assertCount(2, $updated->items);
        $this->assertSame('3.000', $updated->items[0]->fresh()->quantity_received);
        $this->assertSame($receipt->receipt_number, $updated->receipt_number); // the number never changes

        $this->receipts->post($updated, $this->warehouse->id, $this->user->id);

        try {
            $this->receipts->updateDraft($updated, [$this->line($order, 0, '1')], $this->user->id);
            $this->fail('A posted receipt cannot be edited.');
        } catch (DomainException) {
            $this->assertCount(2, $updated->fresh()->items); // the lines are untouched
        }
    }

    public function test_an_order_cannot_be_cancelled_once_goods_were_received(): void
    {
        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '1')], $this->user->id);
        $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);

        // The PRC-05 rule from part 1, now with a real posted receipt.
        $this->expectException(DomainException::class);
        $this->orders->cancel($order, $this->user->id);
    }

    public function test_receipt_actions_are_audit_logged(): void
    {
        $this->actingAs($this->user); // so the audit logger records who did it

        $order = $this->placedOrder();
        $receipt = $this->receipts->createDraft($order, [$this->line($order, 0, '10'), $this->line($order, 1, '5')], $this->user->id);
        $this->receipts->post($receipt, $this->warehouse->id, $this->user->id);

        $created = $this->audit('goods_receipt.created');
        $this->assertCount(1, $created);
        $this->assertSame($receipt->receipt_number, $this->values($created->first(), 'new_values')['receipt_number']);

        $posted = $this->audit('goods_receipt.posted');
        $this->assertCount(1, $posted);
        $this->assertSame($this->user->id, (int) $posted->first()->user_id);
        $this->assertSame($receipt->id, (int) $posted->first()->entity_id);
        $this->assertSame($this->warehouse->id, $this->values($posted->first(), 'new_values')['warehouse_id']);

        // Everything was received, so the order changed status: ordered -> received.
        $last = $this->audit('purchase_order.status_changed')->last();
        $this->assertSame(['status' => 'ordered'], $this->values($last, 'old_values'));
        $this->assertSame(['status' => 'received'], $this->values($last, 'new_values'));

        // Cancelling a draft receipt is logged too.
        $other = $this->placedOrder();
        $draft = $this->receipts->createDraft($other, [$this->line($other, 0, '1')], $this->user->id);
        $this->receipts->cancel($draft, $this->user->id);
        $this->assertCount(1, $this->audit('goods_receipt.cancelled'));
    }

    public function test_refused_actions_write_no_audit_rows(): void
    {
        $order = $this->placedOrder();
        $statusChangesBefore = $this->audit('purchase_order.status_changed')->count();

        try {
            $this->receipts->createDraft($order, [$this->line($order, 0, '11')], $this->user->id); // over-receipt
        } catch (DomainException) {
            // expected
        }

        $this->assertCount(0, $this->audit('goods_receipt.created'));
        $this->assertCount($statusChangesBefore, $this->audit('purchase_order.status_changed'));
    }
}