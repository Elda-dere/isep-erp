<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;
    private Product $product;
    private Warehouse $warehouse;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockService();
        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::create(['name' => 'Main store']);
        $this->product = Product::create([
            'category_id' => Category::create(['name' => 'Beverages'])->id,
            'unit_id' => Unit::create(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
    }

    private function stock(?int $warehouseId = null): string
    {
        return (string) InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $warehouseId ?? $this->warehouse->id)
            ->value('quantity');
    }

    private function receive(string $quantity): StockMovement
    {
        return $this->service->receive($this->product->id, $this->warehouse->id, $quantity, $this->user->id);
    }

    private function makeSaleItem(): int
    {
        $customerId = DB::table('customers')->insertGetId(['name' => 'Test customer']);
        $saleId = DB::table('sales')->insertGetId([
            'sale_number' => 'SAL-' . uniqid(),
            'customer_id' => $customerId,
            'status' => 'pending',
            'total_amount' => '15.00',
            'currency' => 'ETB',
            'created_by' => $this->user->id,
            'created_at' => now(),
        ]);

        return DB::table('sale_items')->insertGetId([
            'sale_id' => $saleId,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => '1.000',
            'unit_price' => '15.00',
            'line_total' => '15.00',
        ]);
    }

    public function test_opening_stock_creates_stock_and_one_movement(): void
    {
        $movement = $this->service->setOpening($this->product->id, $this->warehouse->id, '10', $this->user->id);

        $this->assertSame('10.000', $this->stock());
        $this->assertSame('opening', $movement->movement_type);
        $this->assertSame('10.000', $movement->fresh()->quantity);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_opening_stock_can_only_be_entered_once(): void
    {
        $this->service->setOpening($this->product->id, $this->warehouse->id, '10', $this->user->id);

        $this->expectException(DomainException::class);
        $this->service->setOpening($this->product->id, $this->warehouse->id, '5', $this->user->id);
    }

    public function test_receive_increases_stock_with_a_positive_movement(): void
    {
        $movement = $this->receive('7.500');

        $this->assertSame('7.500', $this->stock());
        $this->assertSame('receipt', $movement->movement_type);
        $this->assertSame('7.500', $movement->fresh()->quantity);
    }

    public function test_deduct_reduces_stock_with_a_negative_movement(): void
    {
        $this->receive('10');

        $movement = $this->service->deduct($this->product->id, $this->warehouse->id, '3', $this->user->id);

        $this->assertSame('7.000', $this->stock());
        $this->assertSame('sale', $movement->movement_type);
        $this->assertSame('-3.000', $movement->fresh()->quantity);
    }

    public function test_deduct_more_than_available_is_rejected_and_changes_nothing(): void
    {
        $this->receive('5');

        try {
            $this->service->deduct($this->product->id, $this->warehouse->id, '5.001', $this->user->id);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertSame('5.000', $e->available);
            $this->assertSame('5.001', $e->requested);
        }

        $this->assertSame('5.000', $this->stock());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_adjustment_cannot_take_stock_below_zero(): void
    {
        $this->receive('2');

        $this->expectException(InsufficientStockException::class);
        $this->service->adjust($this->product->id, $this->warehouse->id, '-2.5', 'Count correction', $this->user->id);
    }

    public function test_adjustment_requires_a_note(): void
    {
        $this->receive('2');

        $this->expectException(InvalidArgumentException::class);
        $this->service->adjust($this->product->id, $this->warehouse->id, '-1', '   ', $this->user->id);
    }

    public function test_adjustment_can_add_or_remove_stock_and_stores_the_note(): void
    {
        $this->receive('10');

        $this->service->adjust($this->product->id, $this->warehouse->id, '-4', 'Damaged in storage', $this->user->id);
        $this->service->adjust($this->product->id, $this->warehouse->id, '1.5', 'Found during count', $this->user->id);

        $this->assertSame('7.500', $this->stock());
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'adjustment', 'note' => 'Damaged in storage']);
    }

    public function test_decimal_arithmetic_is_exact(): void
    {
        $this->receive('0.100');
        $this->receive('0.100');
        $this->receive('0.100');

        $this->assertSame('0.300', $this->stock());
    }

    public function test_more_than_three_decimals_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->receive('1.2345');
    }

    public function test_zero_and_negative_quantities_are_rejected(): void
    {
        foreach (['0', '-1'] as $bad) {
            try {
                $this->receive($bad);
                $this->fail("Quantity {$bad} should have been rejected.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_transfer_moves_stock_and_writes_two_linked_movements(): void
    {
        $second = Warehouse::create(['name' => 'Branch store']);
        $this->receive('10');

        $result = $this->service->transfer($this->product->id, $this->warehouse->id, $second->id, '4', $this->user->id, 'Restock');

        $this->assertSame('6.000', $this->stock());
        $this->assertSame('4.000', $this->stock($second->id));
        $this->assertSame('transfer_out', $result['out']->movement_type);
        $this->assertSame('-4.000', $result['out']->fresh()->quantity);
        $this->assertSame('transfer_in', $result['in']->movement_type);
        $this->assertSame('4.000', $result['in']->fresh()->quantity);
        $this->assertSame($result['out']->note, $result['in']->note);
        $this->assertStringContainsString('TRF-', $result['out']->note);
    }

    public function test_failed_transfer_changes_nothing(): void
    {
        $second = Warehouse::create(['name' => 'Branch store']);
        $this->receive('5');

        try {
            $this->service->transfer($this->product->id, $this->warehouse->id, $second->id, '8', $this->user->id);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException) {
            $this->assertTrue(true);
        }

        $this->assertSame('5.000', $this->stock());
        $this->assertSame(0, InventoryStock::where('warehouse_id', $second->id)->count());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_transfer_to_the_same_warehouse_is_rejected(): void
    {
        $this->receive('5');

        $this->expectException(InvalidArgumentException::class);
        $this->service->transfer($this->product->id, $this->warehouse->id, $this->warehouse->id, '1', $this->user->id);
    }

    public function test_stock_always_equals_the_sum_of_movements(): void
    {
        $this->service->setOpening($this->product->id, $this->warehouse->id, '10', $this->user->id);
        $this->receive('5.250');
        $this->service->deduct($this->product->id, $this->warehouse->id, '3.125', $this->user->id);
        $this->service->adjust($this->product->id, $this->warehouse->id, '-1', 'Loss', $this->user->id);

        $sum = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $this->assertTrue(BigDecimal::of((string) $sum)->isEqualTo($this->stock()));
        $this->assertSame('11.125', $this->stock());
    }

    public function test_a_sale_line_can_only_be_deducted_and_reversed_once(): void
    {
        $this->receive('10');
        $saleItemId = $this->makeSaleItem();

        $this->service->deduct($this->product->id, $this->warehouse->id, '2', $this->user->id, $saleItemId);
        $this->assertSame('8.000', $this->stock());

        try {
            $this->service->deduct($this->product->id, $this->warehouse->id, '2', $this->user->id, $saleItemId);
            $this->fail('Second deduction should be blocked.');
        } catch (DomainException) {
            $this->assertSame('8.000', $this->stock());
        }

        $this->service->reverse($this->product->id, $this->warehouse->id, '2', $this->user->id, $saleItemId);
        $this->assertSame('10.000', $this->stock());

        try {
            $this->service->reverse($this->product->id, $this->warehouse->id, '2', $this->user->id, $saleItemId);
            $this->fail('Second reversal should be blocked.');
        } catch (DomainException) {
            $this->assertSame('10.000', $this->stock());
        }
    }

    public function test_stock_changes_lock_the_row(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $this->receive('1');

        $this->assertTrue(
            collect($queries)->contains(fn(string $sql) => str_contains($sql, 'for update')),
            'Expected a SELECT ... FOR UPDATE query.'
        );
    }
}
