<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // One user who may manage products and warehouses and adjust stock.
        $this->admin = $this->userWith('products.manage', 'warehouses.manage', 'inventory.adjust');
        $this->warehouse = Warehouse::create(['name' => 'Main store']);
        // Created directly (not through the controller), so it writes no audit row.
        $this->product = Product::create($this->payload());
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => 'SKU-001',
            'name' => 'Water 1L',
            'description' => null,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
            'reorder_level' => '5.000',
            'is_active' => true,
        ], $overrides);
    }

    // All audit rows with this action.
    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->get();
    }

    // The JSON columns come back as text: turn them into arrays.
    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_creating_a_product_is_logged_with_the_user_and_new_values(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->payload(['sku' => 'NEW-1']))
            ->assertSessionHasNoErrors();

        $rows = $this->audit('product.created');
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $new = $this->values($row, 'new_values');

        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertSame('product', $row->entity_type);
        $this->assertSame((int) Product::where('sku', 'NEW-1')->value('id'), (int) $row->entity_id);
        $this->assertSame('NEW-1', $new['sku']);
        $this->assertSame('15.00', $new['selling_price']); // exact decimal string, not a float
    }

    public function test_a_price_change_is_logged_with_old_and_new_values(): void
    {
        $this->actingAs($this->admin)
            ->put("/products/{$this->product->id}", $this->payload(['selling_price' => '18.50']))
            ->assertSessionHasNoErrors();

        $price = $this->audit('product.price_changed');
        $this->assertCount(1, $price);
        $this->assertSame(['selling_price' => '15.00'], $this->values($price->first(), 'old_values'));
        $this->assertSame(['selling_price' => '18.50'], $this->values($price->first(), 'new_values'));

        // The general update entry exists too, and lists only the field that changed.
        $updated = $this->audit('product.updated');
        $this->assertCount(1, $updated);
        $this->assertSame(['selling_price' => '18.50'], $this->values($updated->first(), 'new_values'));
    }

    public function test_a_change_that_is_not_a_price_does_not_write_a_price_entry(): void
    {
        $this->actingAs($this->admin)
            ->put("/products/{$this->product->id}", $this->payload(['name' => 'Water 2L']))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $this->audit('product.updated'));
        $this->assertCount(0, $this->audit('product.price_changed'));
        $this->assertSame(['name' => 'Water 2L'], $this->values($this->audit('product.updated')->first(), 'new_values'));
    }

    public function test_saving_a_product_without_changes_writes_nothing(): void
    {
        $this->actingAs($this->admin)
            ->put("/products/{$this->product->id}", $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('products.index'));

        $this->assertCount(0, $this->audit('product.updated'));
        $this->assertCount(0, $this->audit('product.price_changed'));
    }

    public function test_deactivating_and_activating_a_product_are_logged_once_each(): void
    {
        $this->actingAs($this->admin)->patch("/products/{$this->product->id}/deactivate");
        $this->actingAs($this->admin)->patch("/products/{$this->product->id}/deactivate"); // repeated: no new entry
        $this->assertCount(1, $this->audit('product.deactivated'));

        $this->actingAs($this->admin)->patch("/products/{$this->product->id}/activate");
        $this->assertCount(1, $this->audit('product.activated'));

        $this->assertTrue($this->product->fresh()->is_active);
    }

    public function test_warehouse_create_and_update_are_logged(): void
    {
        $this->actingAs($this->admin)->post('/warehouses', ['name' => 'Branch', 'location' => 'Bole'])
            ->assertSessionHasNoErrors();

        $created = $this->audit('warehouse.created');
        $this->assertCount(1, $created);
        $this->assertSame('Branch', $this->values($created->first(), 'new_values')['name']);

        $branch = Warehouse::where('name', 'Branch')->first();
        $this->actingAs($this->admin)->put("/warehouses/{$branch->id}", ['name' => 'Branch 2', 'location' => 'Bole'])
            ->assertSessionHasNoErrors();

        // Only the name changed, so only the name is in the entry.
        $updated = $this->audit('warehouse.updated');
        $this->assertCount(1, $updated);
        $this->assertSame(['name' => 'Branch'], $this->values($updated->first(), 'old_values'));
        $this->assertSame(['name' => 'Branch 2'], $this->values($updated->first(), 'new_values'));
    }

    public function test_a_stock_adjustment_is_logged_with_its_note(): void
    {
        (new StockService())->setOpening($this->product->id, $this->warehouse->id, '10', $this->admin->id);

        $this->actingAs($this->admin)->post('/stock/adjustments', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => '-3.5',
            'note' => 'Damaged',
        ])->assertSessionHasNoErrors();

        $rows = $this->audit('stock.adjusted');
        $this->assertCount(1, $rows);

        $row = $rows->first();
        $new = $this->values($row, 'new_values');
        $movementId = StockMovement::where('movement_type', 'adjustment')->value('id');

        $this->assertSame('stock_movement', $row->entity_type);
        $this->assertSame((int) $movementId, (int) $row->entity_id);
        $this->assertSame('-3.500', $new['quantity']);
        $this->assertSame('Damaged', $new['note']);
    }

    public function test_a_rejected_adjustment_writes_no_audit_row_and_no_movement(): void
    {
        (new StockService())->setOpening($this->product->id, $this->warehouse->id, '2', $this->admin->id);

        $this->actingAs($this->admin)->post('/stock/adjustments', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => '-5',
            'note' => 'Too much',
        ])->assertSessionHasErrors('quantity');

        $this->assertCount(0, $this->audit('stock.adjusted'));
        $this->assertSame(1, StockMovement::count()); // only the opening movement
    }
}
