<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function stock(): ?string
    {
        $quantity = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');

        return $quantity === null ? null : (string) $quantity;
    }

    private function data(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => '10',
            'note' => 'Counted on shelf',
        ], $overrides);
    }

    public function test_users_without_adjust_permission_get_403(): void
    {
        $viewer = $this->userWith('inventory.view');

        $this->actingAs($viewer)->post('/stock/opening', $this->data())->assertForbidden();
        $this->actingAs($viewer)->post('/stock/adjustments', $this->data())->assertForbidden();

        $this->assertSame(0, StockMovement::count());
        $this->assertNull($this->stock());
    }

    public function test_opening_stock_is_recorded_through_the_service(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/opening', $this->data())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('10.000', $this->stock());
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'opening',
            'quantity' => '10.000',
            'performed_by' => $user->id,
        ]);
    }

    public function test_opening_stock_cannot_be_entered_twice(): void
    {
        $user = $this->userWith('inventory.adjust');
        $this->actingAs($user)->post('/stock/opening', $this->data());

        $this->actingAs($user)->post('/stock/opening', $this->data(['quantity' => '99']))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.000', $this->stock());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_opening_stock_must_be_positive(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/opening', $this->data(['quantity' => '0']))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_adjustment_can_remove_stock_and_stores_the_note(): void
    {
        $user = $this->userWith('inventory.adjust');
        $this->actingAs($user)->post('/stock/opening', $this->data(['quantity' => '10']));

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['quantity' => '-3.5', 'note' => 'Damaged']))
            ->assertSessionHasNoErrors();

        $this->assertSame('6.500', $this->stock());
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'adjustment',
            'quantity' => '-3.500',
            'note' => 'Damaged',
            'performed_by' => $user->id,
        ]);
    }

    public function test_adjustment_can_add_stock(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['quantity' => '2', 'note' => 'Found']))
            ->assertSessionHasNoErrors();

        $this->assertSame('2.000', $this->stock());
    }

    public function test_adjustment_requires_a_note(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['note' => '']))
            ->assertSessionHasErrors('note');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_adjustment_cannot_take_stock_below_zero(): void
    {
        $user = $this->userWith('inventory.adjust');
        $this->actingAs($user)->post('/stock/opening', $this->data(['quantity' => '2']));

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['quantity' => '-2.5']))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('2.000', $this->stock());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_zero_adjustment_is_rejected(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['quantity' => '0']))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_more_than_three_decimals_is_rejected(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['quantity' => '1.2345']))
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_unknown_product_or_warehouse_is_rejected(): void
    {
        $user = $this->userWith('inventory.adjust');

        $this->actingAs($user)->post('/stock/adjustments', $this->data(['product_id' => 99999, 'warehouse_id' => 99999]))
            ->assertSessionHasErrors(['product_id', 'warehouse_id']);

        $this->assertSame(0, StockMovement::count());
    }
}
