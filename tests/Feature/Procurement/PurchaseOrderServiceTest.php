<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private Supplier $supplier;
    private User $user;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PurchaseOrderService();
        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->user = User::factory()->create();
        $this->water = $this->makeProduct('SKU-001');
        $this->juice = $this->makeProduct('SKU-002');
    }

    private function makeProduct(string $sku, bool $active = true): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => $active,
        ]);
    }

    // Two lines: 10 x 2.50 = 25.00 and 0.010 x 0.50 = 0.005 (which rounds half-up to 0.01).
    private function lines(): array
    {
        return [
            ['product_id' => $this->water->id, 'quantity' => '10', 'unit_cost' => '2.50'],
            ['product_id' => $this->juice->id, 'quantity' => '0.010', 'unit_cost' => '0.50'],
        ];
    }

    private function draft(): PurchaseOrder
    {
        return $this->service->createDraft($this->supplier->id, $this->lines(), $this->user->id);
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

    public function test_a_draft_order_is_created_with_a_number_creator_and_lines(): void
    {
        $order = $this->draft();

        $this->assertSame('draft', $order->status);
        $this->assertSame($this->user->id, (int) $order->created_by);
        $this->assertSame($this->supplier->id, (int) $order->supplier_id);
        $this->assertNull($order->fresh()->ordered_at);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $order->order_number);

        $this->assertCount(2, $order->items);
        $this->assertSame('10.000', $order->items[0]->fresh()->quantity); // exact decimal strings
        $this->assertSame('2.50', $order->items[0]->fresh()->unit_cost);
    }

    public function test_order_numbers_count_up_and_follow_the_addis_ababa_year(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        try {
            $this->assertSame('PO-2026-0001', $this->draft()->order_number);
            $this->assertSame('PO-2026-0002', $this->draft()->order_number);

            // 22:00 UTC on 31 December is already 1 January in Addis Ababa (UTC+3): a new business year.
            Carbon::setTestNow('2026-12-31 22:00:00');
            $this->assertSame('PO-2027-0001', $this->draft()->order_number);
        } finally {
            Carbon::setTestNow(); // always put the clock back, even if an assertion fails
        }
    }

    public function test_line_totals_and_the_order_total_round_half_up(): void
    {
        $order = $this->draft();

        $this->assertSame('25.00', $order->items[0]->lineTotal());
        $this->assertSame('0.01', $order->items[1]->lineTotal()); // 0.005 rounds UP, not to 0.00
        $this->assertSame('25.01', $order->total());
    }

    public function test_an_order_needs_at_least_one_line(): void
    {
        try {
            $this->service->createDraft($this->supplier->id, [], $this->user->id);
            $this->fail('An order without lines should be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, PurchaseOrder::count());
        }
    }

    public function test_inactive_and_unknown_products_are_refused(): void
    {
        $inactive = $this->makeProduct('OLD-001', false);

        try {
            $this->service->createDraft($this->supplier->id, [
                ['product_id' => $inactive->id, 'quantity' => '1', 'unit_cost' => '1'],
            ], $this->user->id);
            $this->fail('An inactive product should be refused.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('OLD-001', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->createDraft($this->supplier->id, [
            ['product_id' => 99999, 'quantity' => '1', 'unit_cost' => '1'],
        ], $this->user->id);
    }

    public function test_quantities_and_costs_are_validated(): void
    {
        $bad = [
            ['quantity' => '0', 'unit_cost' => '1'],        // quantity must be above zero
            ['quantity' => '-1', 'unit_cost' => '1'],
            ['quantity' => '1.2345', 'unit_cost' => '1'],   // at most 3 decimals
            ['quantity' => 'abc', 'unit_cost' => '1'],
            ['quantity' => '1', 'unit_cost' => '-0.01'],    // cost cannot be negative
            ['quantity' => '1', 'unit_cost' => '1.005'],    // at most 2 decimals
        ];

        foreach ($bad as $line) {
            try {
                $this->service->createDraft($this->supplier->id, [
                    ['product_id' => $this->water->id] + $line,
                ], $this->user->id);
                $this->fail('This line should be refused: ' . json_encode($line));
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, PurchaseOrder::count());

        // The smallest valid line: a free sample.
        $order = $this->service->createDraft($this->supplier->id, [
            ['product_id' => $this->water->id, 'quantity' => '0.001', 'unit_cost' => '0'],
        ], $this->user->id);
        $this->assertSame('0.00', $order->total());
    }

    public function test_a_draft_can_be_edited(): void
    {
        $order = $this->draft();
        $number = $order->order_number;
        $other = Supplier::create(['name' => 'Other Supplier']);

        $updated = $this->service->updateDraft($order, $other->id, [
            ['product_id' => $this->juice->id, 'quantity' => '3', 'unit_cost' => '4.00'],
        ], $this->user->id);

        $this->assertSame($number, $updated->order_number); // the number never changes
        $this->assertSame($other->id, (int) $updated->supplier_id);
        $this->assertCount(1, $updated->items);
        $this->assertSame('12.00', $updated->total());
        $this->assertSame(1, PurchaseOrderItem::where('purchase_order_id', $order->id)->count());
    }

    public function test_an_order_cannot_be_edited_after_it_is_placed(): void
    {
        $order = $this->draft();
        $this->service->place($order, $this->user->id);

        try {
            $this->service->updateDraft($order, $this->supplier->id, [
                ['product_id' => $this->juice->id, 'quantity' => '3', 'unit_cost' => '4.00'],
            ], $this->user->id);
            $this->fail('A placed order should not be editable.');
        } catch (DomainException) {
            $this->assertSame(2, PurchaseOrderItem::where('purchase_order_id', $order->id)->count());
        }
    }

    public function test_placing_sets_the_status_and_ordered_at(): void
    {
        Carbon::setTestNow('2026-03-01 10:00:00');

        try {
            $order = $this->service->place($this->draft(), $this->user->id);

            $this->assertSame('ordered', $order->fresh()->status);
            $this->assertSame('2026-03-01 10:00:00', $order->fresh()->ordered_at->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_only_a_draft_with_lines_can_be_placed(): void
    {
        $order = $this->draft();
        $this->service->place($order, $this->user->id);

        try {
            $this->service->place($order, $this->user->id); // placing twice
            $this->fail('A second placing should be refused.');
        } catch (DomainException) {
            $this->assertSame('ordered', $order->fresh()->status);
        }

        // An order without lines (only possible by writing to the table directly).
        $empty = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_number' => 'PO-TEST-0001',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->expectException(DomainException::class);
        $this->service->place($empty, $this->user->id);
    }

    public function test_a_draft_or_ordered_order_can_be_cancelled(): void
    {
        $draft = $this->draft();
        $this->service->cancel($draft, $this->user->id);
        $this->assertSame('cancelled', $draft->fresh()->status);

        $ordered = $this->service->place($this->draft(), $this->user->id);
        $this->service->cancel($ordered, $this->user->id);
        $this->assertSame('cancelled', $ordered->fresh()->status);

        // A cancelled order cannot be placed or cancelled again.
        $this->expectException(DomainException::class);
        $this->service->cancel($ordered, $this->user->id);
    }

    public function test_an_order_with_received_goods_cannot_be_cancelled(): void
    {
        // By status: partially received and received orders.
        foreach (['partially_received', 'received'] as $status) {
            $order = $this->service->place($this->draft(), $this->user->id);
            PurchaseOrder::whereKey($order->id)->update(['status' => $status]);

            try {
                $this->service->cancel($order, $this->user->id);
                $this->fail("A {$status} order should not be cancellable.");
            } catch (DomainException) {
                $this->assertSame($status, $order->fresh()->status);
            }
        }

        // By evidence: the status still says "ordered", but a posted goods receipt exists.
        $order = $this->service->place($this->draft(), $this->user->id);
        DB::table('goods_receipts')->insert([
            'purchase_order_id' => $order->id,
            'receipt_number' => 'GRN-TEST-0001',
            'received_by' => $this->user->id,
            'received_at' => now(),
            'status' => 'posted',
        ]);

        try {
            $this->service->cancel($order, $this->user->id);
            $this->fail('An order with a posted receipt should not be cancellable.');
        } catch (DomainException) {
            $this->assertSame('ordered', $order->fresh()->status);
        }
    }

    public function test_status_changes_are_audit_logged(): void
    {
        $this->actingAs($this->user); // so the audit logger records who did it

        $order = $this->draft();
        $this->service->place($order, $this->user->id);
        $this->service->cancel($order, $this->user->id);

        $created = $this->audit('purchase_order.created');
        $this->assertCount(1, $created);
        $this->assertSame($this->user->id, (int) $created->first()->user_id);
        $this->assertSame($order->order_number, $this->values($created->first(), 'new_values')['order_number']);
        $this->assertCount(2, $this->values($created->first(), 'new_values')['lines']);

        $changes = $this->audit('purchase_order.status_changed');
        $this->assertCount(2, $changes);
        $this->assertSame(['status' => 'draft'], $this->values($changes[0], 'old_values'));
        $this->assertSame(['status' => 'ordered'], $this->values($changes[0], 'new_values'));
        $this->assertSame(['status' => 'ordered'], $this->values($changes[1], 'old_values'));
        $this->assertSame(['status' => 'cancelled'], $this->values($changes[1], 'new_values'));
    }

    public function test_refused_actions_write_no_audit_rows(): void
    {
        // A refused creation leaves no order and no audit row.
        try {
            $this->service->createDraft($this->supplier->id, [], $this->user->id);
        } catch (InvalidArgumentException) {
            // expected
        }
        $this->assertSame(0, PurchaseOrder::count());
        $this->assertCount(0, $this->audit('purchase_order.created'));

        // Refused status changes write nothing either: only the real ones are logged.
        $order = $this->draft();
        $this->service->place($order, $this->user->id);

        try {
            $this->service->place($order, $this->user->id);
        } catch (DomainException) {
            // expected
        }

        $this->assertCount(1, $this->audit('purchase_order.status_changed'));
    }
}
