<?php

namespace Tests\Feature\Catalogue;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProductRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        $category = Category::firstOrCreate(['name' => 'Beverages']);
        $unit = Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs']);

        return array_merge([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'description' => null,
            'cost_price' => '10.50',
            'selling_price' => '15.00',
            'reorder_level' => '5.250',
            'is_active' => true,
        ], $overrides);
    }

    private function check(array $data): ValidatorContract
    {
        return Validator::make($data, (new ProductRequest())->rules());
    }

    public function test_valid_data_passes(): void
    {
        $this->assertTrue($this->check($this->validData())->passes());
    }

    public function test_sku_is_required(): void
    {
        $validator = $this->check($this->validData(['sku' => '']));

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('sku'));
    }

    public function test_sku_must_be_unique(): void
    {
        $data = $this->validData();
        Product::create($data);

        $validator = $this->check($data);

        $this->assertTrue($validator->errors()->has('sku'));
    }

    public function test_prices_cannot_be_negative(): void
    {
        $validator = $this->check($this->validData(['cost_price' => '-1.00']));

        $this->assertTrue($validator->errors()->has('cost_price'));
    }

    public function test_zero_price_is_allowed(): void
    {
        $validator = $this->check($this->validData(['cost_price' => '0.00', 'selling_price' => '0']));

        $this->assertTrue($validator->passes());
    }

    public function test_price_allows_at_most_two_decimals(): void
    {
        $validator = $this->check($this->validData(['selling_price' => '10.555']));

        $this->assertTrue($validator->errors()->has('selling_price'));
    }

    public function test_reorder_level_allows_at_most_three_decimals(): void
    {
        $validator = $this->check($this->validData(['reorder_level' => '1.2345']));

        $this->assertTrue($validator->errors()->has('reorder_level'));
    }

    public function test_category_and_unit_must_exist(): void
    {
        $validator = $this->check($this->validData(['category_id' => 99999, 'unit_id' => 99999]));

        $this->assertTrue($validator->errors()->has('category_id'));
        $this->assertTrue($validator->errors()->has('unit_id'));
    }

    public function test_selling_below_cost_is_still_valid(): void
    {
        $validator = $this->check($this->validData(['cost_price' => '20.00', 'selling_price' => '15.00']));

        $this->assertTrue($validator->passes());
    }
}
