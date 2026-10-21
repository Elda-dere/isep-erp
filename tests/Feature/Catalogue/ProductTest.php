<?php

namespace Tests\Feature\Catalogue;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(['name' => 'Beverages']);
        $unit = Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs']);

        return Product::create(array_merge([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'cost_price' => '10.50',
            'selling_price' => '15.00',
            'reorder_level' => '5.250',
            'is_active' => true,
        ], $overrides));
    }

    public function test_product_belongs_to_a_category_and_a_unit(): void
    {
        $product = $this->makeProduct();

        $this->assertSame('Beverages', $product->category->name);
        $this->assertSame('pcs', $product->unit->symbol);
    }

    public function test_category_lists_its_products(): void
    {
        $product = $this->makeProduct();

        $this->assertCount(1, $product->category->products);
    }

    public function test_prices_and_quantities_keep_exact_decimals(): void
    {
        $product = $this->makeProduct()->fresh();

        $this->assertSame('10.50', $product->cost_price);
        $this->assertSame('15.00', $product->selling_price);
        $this->assertSame('5.250', $product->reorder_level);
    }

    public function test_is_active_is_a_boolean(): void
    {
        $product = $this->makeProduct()->fresh();

        $this->assertTrue($product->is_active);
    }

    public function test_sku_must_be_unique(): void
    {
        $this->makeProduct();

        $this->expectException(QueryException::class);
        $this->makeProduct(['name' => 'Another product']);
    }

    public function test_a_category_in_use_cannot_be_deleted(): void
    {
        $product = $this->makeProduct();

        $this->expectException(QueryException::class);
        $product->category->delete();
    }
}
