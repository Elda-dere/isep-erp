<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockTimestampTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_rows_get_their_timestamps_from_php_in_utc(): void
    {
        // Freeze "now" at a known UTC moment. If a timestamp came from MySQL's own
        // default (which may not be UTC), it would show the real current time
        // and this test would fail. So a pass proves PHP sets the value (DR-07).
        Carbon::setTestNow('2026-01-05 22:00:00');

        try {
            $user = User::factory()->create();
            $warehouse = Warehouse::create(['name' => 'Main store']);
            $product = Product::create([
                'category_id' => Category::create(['name' => 'Beverages'])->id,
                'unit_id' => Unit::create(['name' => 'Piece', 'symbol' => 'pcs'])->id,
                'sku' => 'SKU-001',
                'name' => 'Water 1L',
                'cost_price' => '10.00',
                'selling_price' => '15.00',
                'reorder_level' => '5.000',
                'is_active' => true,
            ]);

            $movement = (new StockService())->receive($product->id, $warehouse->id, '5', $user->id);

            // The app itself must run in UTC for stored timestamps.
            $this->assertSame('UTC', config('app.timezone'));
            $this->assertSame('2026-01-05 22:00:00', $movement->fresh()->created_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-01-05 22:00:00', InventoryStock::first()->updated_at->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow(); // always unfreeze the clock, even if an assertion fails
        }
    }
}
