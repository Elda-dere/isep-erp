<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModelsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $category = Category::firstOrCreate(['name' => 'Beverages']);
        $unit = Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs']);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-' . uniqid(),
            'name' => 'Water 1L',
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ]);
    }

    public function test_a_warehouse_can_be_created(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store', 'location' => 'Addis Ababa']);

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Main store']);
    }

    public function test_stock_quantity_keeps_exact_decimals(): void
    {
        $stock = InventoryStock::create([
            'product_id' => $this->makeProduct()->id,
            'warehouse_id' => Warehouse::create(['name' => 'Main store'])->id,
            'quantity' => '12.345',
        ]);

        $this->assertSame('12.345', $stock->fresh()->quantity);
    }

    public function test_only_one_stock_row_per_product_and_warehouse(): void
    {
        $product = $this->makeProduct();
        $warehouse = Warehouse::create(['name' => 'Main store']);
        InventoryStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1.000']);

        $this->expectException(QueryException::class);
        InventoryStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => '2.000']);
    }

    public function test_a_warehouse_with_stock_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main store']);
        InventoryStock::create([
            'product_id' => $this->makeProduct()->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => '1.000',
        ]);

        $this->expectException(QueryException::class);
        $warehouse->delete();
    }

    public function test_stock_movements_store_signed_quantities(): void
    {
        $product = $this->makeProduct();
        $warehouse = Warehouse::create(['name' => 'Main store']);
        $user = User::factory()->create();

        $in = StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => StockMovement::TYPE_OPENING,
            'quantity' => '10.000',
            'performed_by' => $user->id,
        ]);
        $out = StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => '-3.000',
            'performed_by' => $user->id,
            'note' => 'Damaged',
        ]);

        $this->assertSame('10.000', $in->fresh()->quantity);
        $this->assertSame('-3.000', $out->fresh()->quantity);
        $this->assertNull($in->fresh()->goods_receipt_item_id);
        $this->assertNull($in->fresh()->sale_item_id);
    }

    public function test_movement_relationships_work(): void
    {
        $product = $this->makeProduct();
        $warehouse = Warehouse::create(['name' => 'Main store']);
        $user = User::factory()->create();
        $movement = StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'movement_type' => StockMovement::TYPE_OPENING,
            'quantity' => '5.000',
            'performed_by' => $user->id,
        ]);

        $this->assertSame($user->id, $movement->performedBy->id);
        $this->assertSame($product->id, $movement->product->id);
        $this->assertSame($warehouse->id, $movement->warehouse->id);
    }
}
