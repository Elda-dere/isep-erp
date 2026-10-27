<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderScreensTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $service;
    private User $manager;
    private Supplier $supplier;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->service = new PurchaseOrderService();
        $this->manager = $this->userWith('purchase_orders.manage');
        $this->supplier = Supplier::create(['name' => 'Acme Trading']);
        $this->water = $this->makeProduct('SKU-001');
        $this->juice = $this->makeProduct('SKU-002');
    }

    // Builds a user whose role has exactly the permissions named.
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

    // The data the form sends: a supplier and two lines.
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->water->id, 'quantity' => '10', 'unit_cost' => '2.50'],
                ['product_id' => $this->juice->id, 'quantity' => '0.010', 'unit_cost' => '0.50'],
            ],
        ], $overrides);
    }

    // A draft created through the service, for the edit and update tests.
    private function draft(): PurchaseOrder
    {
        return $this->service->createDraft($this->supplier->id, $this->payload()['lines'], $this->manager->id);
    }

    public function test_users_without_manage_get_403_everywhere(): void
    {
        $order = $this->draft();
        // Being able to VIEW orders is not enough to open the form or save.
        $viewer = $this->userWith('purchase_orders.view');

        $this->actingAs($viewer)->get('/purchase-orders/create')->assertForbidden();
        $this->actingAs($viewer)->post('/purchase-orders', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->get("/purchase-orders/{$order->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/purchase-orders/{$order->id}", $this->payload())->assertForbidden();

        $this->assertSame(1, PurchaseOrder::count()); // only the draft made above
    }

    public function test_the_create_form_lists_suppliers_and_active_products_only(): void
    {
        $this->makeProduct('OLD-001', false); // inactive: must not be offered (PRD-04)

        $this->actingAs($this->manager)->get('/purchase-orders/create')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/PurchaseOrders/Form')
                ->where('order', null)
                ->has('suppliers', 1)
                ->has('products', 2)
                ->where('products.0.sku', 'SKU-001')
                ->where('products.0.cost_price', '10.00')   // exact string, not a float
                ->where('products.0.unit.symbol', 'pcs'));
    }

    public function test_a_manager_creates_a_draft_and_is_sent_to_its_edit_page(): void
    {
        $this->actingAs($this->manager)->post('/purchase-orders', $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $order = PurchaseOrder::first();

        $this->assertSame('draft', $order->status);
        $this->assertSame($this->manager->id, (int) $order->created_by);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $order->order_number);
        $this->assertCount(2, $order->items);
        $this->assertSame('10.000', $order->items[0]->quantity);
        $this->assertSame('2.50', $order->items[0]->unit_cost);
        $this->assertSame('25.01', $order->total());
    }

    public function test_creating_an_order_is_audit_logged_with_the_user(): void
    {
        $this->actingAs($this->manager)->post('/purchase-orders', $this->payload());

        $order = PurchaseOrder::first();
        $rows = DB::table('audit_logs')->where('action', 'purchase_order.created')->get();

        $this->assertCount(1, $rows);
        $this->assertSame($this->manager->id, (int) $rows->first()->user_id);
        $this->assertSame($order->id, (int) $rows->first()->entity_id);
        $this->assertSame($order->order_number, json_decode($rows->first()->new_values, true)['order_number']);
    }

    public function test_the_form_data_is_validated(): void
    {
        $bad = [
            'supplier_id' => $this->payload(['supplier_id' => ''])['supplier_id'] === null ? [] : ['supplier_id' => ''],
        ];

        // Each case: the data to send and the error key we expect beside the field.
        $cases = [
            [['supplier_id' => ''], 'supplier_id'],
            [['lines' => []], 'lines'],
            [['lines' => [['product_id' => 99999, 'quantity' => '1', 'unit_cost' => '1']]], 'lines.0.product_id'],
            [['lines' => [['product_id' => $this->water->id, 'quantity' => '0', 'unit_cost' => '1']]], 'lines.0.quantity'],
            [['lines' => [['product_id' => $this->water->id, 'quantity' => '1.2345', 'unit_cost' => '1']]], 'lines.0.quantity'],
            [['lines' => [['product_id' => $this->water->id, 'quantity' => 'abc', 'unit_cost' => '1']]], 'lines.0.quantity'],
            [['lines' => [['product_id' => $this->water->id, 'quantity' => '1', 'unit_cost' => '-1']]], 'lines.0.unit_cost'],
            [['lines' => [['product_id' => $this->water->id, 'quantity' => '1', 'unit_cost' => '1.005']]], 'lines.0.unit_cost'],
        ];

        foreach ($cases as [$overrides, $expectedKey]) {
            $this->actingAs($this->manager)->post('/purchase-orders', $this->payload($overrides))
                ->assertSessionHasErrors($expectedKey);
        }

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_an_inactive_product_is_refused_and_nothing_is_saved(): void
    {
        $inactive = $this->makeProduct('OLD-001', false);

        $this->actingAs($this->manager)->post('/purchase-orders', $this->payload([
            'lines' => [['product_id' => $inactive->id, 'quantity' => '1', 'unit_cost' => '1']],
        ]))->assertSessionHasErrors('lines'); // the service's message, shown above the lines

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'purchase_order.created')->count());
    }

    public function test_the_edit_form_shows_the_drafts_exact_values(): void
    {
        $order = $this->draft();

        $this->actingAs($this->manager)->get("/purchase-orders/{$order->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/PurchaseOrders/Form')
                ->where('order.id', $order->id)
                ->where('order.order_number', $order->order_number)
                ->where('order.supplier_id', $this->supplier->id)
                ->has('order.lines', 2)
                ->where('order.lines.0.product_id', $this->water->id)
                ->where('order.lines.0.quantity', '10.000')
                ->where('order.lines.0.unit_cost', '2.50')
                ->where('order.lines.1.quantity', '0.010'));
    }

    public function test_a_placed_order_cannot_be_opened_for_editing(): void
    {
        $order = $this->service->place($this->draft(), $this->manager->id);

        $this->actingAs($this->manager)->get("/purchase-orders/{$order->id}/edit")->assertForbidden();
    }

    public function test_a_draft_can_be_updated(): void
    {
        $order = $this->draft();
        $other = Supplier::create(['name' => 'Other Supplier']);

        $this->actingAs($this->manager)->put("/purchase-orders/{$order->id}", [
            'supplier_id' => $other->id,
            'lines' => [['product_id' => $this->juice->id, 'quantity' => '3', 'unit_cost' => '4.00']],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('purchase_orders.edit', $order));

        $fresh = $order->fresh();
        $this->assertSame($other->id, (int) $fresh->supplier_id);
        $this->assertCount(1, $fresh->items);
        $this->assertSame('12.00', $fresh->total());
        $this->assertSame($order->order_number, $fresh->order_number); // the number never changes
    }

    public function test_a_placed_order_cannot_be_updated(): void
    {
        $order = $this->service->place($this->draft(), $this->manager->id);

        $this->actingAs($this->manager)->put("/purchase-orders/{$order->id}", [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->juice->id, 'quantity' => '3', 'unit_cost' => '4.00']],
        ])->assertSessionHasErrors('lines');

        $this->assertCount(2, $order->fresh()->items); // the lines are untouched
    }

    public function test_opening_a_missing_order_returns_404(): void
    {
        $this->actingAs($this->manager)->get('/purchase-orders/99999/edit')->assertNotFound();
    }
}
