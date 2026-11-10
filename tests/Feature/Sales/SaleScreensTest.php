<?php

namespace Tests\Feature\Sales;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SaleScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Customer $walkIn;
    private Customer $customer;
    private Warehouse $main;
    private Warehouse $branch;
    private Product $water;
    private Product $juice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        // A cashier may create and look at sales, but may NOT change prices.
        $this->cashier = $this->userWith('sales.create', 'sales.view');
        $this->walkIn = Customer::create(['name' => Customer::WALK_IN_NAME]);
        $this->customer = Customer::create(['name' => 'Abebe Kebede']);
        $this->main = Warehouse::create(['name' => 'Main store']);
        $this->branch = Warehouse::create(['name' => 'Branch store']);

        // Water is created first, so it comes first in the product list (names sort the same way).
        $this->water = $this->makeProduct('SKU-001', '2.50');
        $this->juice = $this->makeProduct('SKU-002', '0.50');

        // 100 of each in the main store, none in the branch store.
        $stock = app(StockService::class);
        $stock->receive($this->water->id, $this->main->id, '100', $this->cashier->id);
        $stock->receive($this->juice->id, $this->main->id, '100', $this->cashier->id);
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

    private function makeProduct(string $sku, string $price, bool $active = true): Product
    {
        return Product::create([
            'category_id' => Category::firstOrCreate(['name' => 'Beverages'])->id,
            'unit_id' => Unit::firstOrCreate(['name' => 'Piece', 'symbol' => 'pcs'])->id,
            'sku' => $sku,
            'name' => "Product {$sku}",
            'cost_price' => '1.00',
            'selling_price' => $price,
            'reorder_level' => '5.000',
            'is_active' => $active,
        ]);
    }

    // One line the way the screen sends it. The price is left out unless given.
    private function line(Product $product, string $quantity, ?string $price = null, ?Warehouse $warehouse = null): array
    {
        $line = [
            'product_id' => $product->id,
            'warehouse_id' => ($warehouse ?? $this->main)->id,
            'quantity' => $quantity,
        ];

        return $price === null ? $line : $line + ['unit_price' => $price];
    }

    // The data the screen sends: a customer and some lines (one line of water by default).
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'lines' => [$this->line($this->water, '1')],
        ], $overrides);
    }

    private function stockOf(Product $product): string
    {
        $quantity = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->main->id)
            ->value('quantity');

        return (string) ($quantity ?? '0.000');
    }

    // A sale made through the service, for the detail page test.
    private function existingSale(): Sale
    {
        return app(SaleService::class)->create($this->customer->id, [
            $this->line($this->water, '10'),
            $this->line($this->juice, '0.010'),
        ], $this->cashier->id);
    }

    public function test_users_without_the_right_permission_get_403(): void
    {
        $sale = $this->existingSale();
        // Being able to LOOK at sales is not enough to create one.
        $viewer = $this->userWith('sales.view');
        // Other sales-related permissions are not enough either.
        $other = $this->userWith('customers.manage', 'sales.cancel', 'payments.start');

        $this->actingAs($viewer)->get('/sales/create')->assertForbidden();
        $this->actingAs($viewer)->post('/sales', $this->payload())->assertForbidden();

        $this->actingAs($other)->get('/sales/create')->assertForbidden();
        $this->actingAs($other)->post('/sales', $this->payload())->assertForbidden();
        $this->actingAs($other)->get("/sales/{$sale->id}")->assertForbidden();

        $this->assertSame(1, Sale::count()); // only the sale made above
        $this->assertSame('90.000', $this->stockOf($this->water));
    }

    public function test_the_sale_screen_gets_customers_products_stock_and_the_price_permission(): void
    {
        $this->makeProduct('OLD-001', '1.00', false); // inactive: must not be offered (PRD-04)

        $this->actingAs($this->cashier)->get('/sales/create')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Create')
                ->has('customers', 2)
                ->where('defaultCustomerId', $this->walkIn->id)       // anonymous sales use the Walk-in customer
                ->has('warehouses', 2)
                ->has('products', 2)
                ->where('products.0.sku', 'SKU-001')
                ->where('products.0.selling_price', '2.50')           // exact string, not a float
                ->where('products.0.unit.symbol', 'pcs')
                ->where("stock.{$this->water->id}.{$this->main->id}", '100.000')
                ->where('canOverridePrice', false));

        // A user with the price override permission is told so.
        $boss = $this->userWith('sales.create', 'sales.price_override');

        $this->actingAs($boss)->get('/sales/create')
            ->assertInertia(fn(Assert $page) => $page->where('canOverridePrice', true));
    }

    public function test_a_cashier_creates_a_sale_and_is_sent_to_its_detail_page(): void
    {
        $response = $this->actingAs($this->cashier)->post('/sales', $this->payload([
            'lines' => [$this->line($this->water, '10'), $this->line($this->juice, '0.010')],
        ]));

        $sale = Sale::first();

        $response->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('pending', $sale->status);
        $this->assertSame($this->cashier->id, (int) $sale->created_by);
        $this->assertSame($this->customer->id, (int) $sale->customer_id);
        $this->assertSame('25.01', $sale->total_amount);               // 25.00 + 0.01 (half-up)
        $this->assertCount(2, $sale->items);
        $this->assertSame('2.50', $sale->items[0]->unit_price);        // the catalogue price, chosen by the server

        // The stock went down when the sale was created.
        $this->assertSame('90.000', $this->stockOf($this->water));
        $this->assertSame('99.990', $this->stockOf($this->juice));

        // The sale and its audit row were saved together, with the cashier recorded.
        $rows = DB::table('audit_logs')->where('action', 'sale.created')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->cashier->id, (int) $rows->first()->user_id);
        $this->assertSame($sale->id, (int) $rows->first()->entity_id);
    }

    public function test_the_form_data_is_validated(): void
    {
        // Each case: what to change in the data, and the error key we expect beside the field.
        $cases = [
            [['customer_id' => ''], 'customer_id'],
            [['customer_id' => 99999], 'customer_id'],
            [['lines' => []], 'lines'],
            [['lines' => [$this->line($this->water, '0')]], 'lines.0.quantity'],
            [['lines' => [$this->line($this->water, '-1')]], 'lines.0.quantity'],
            [['lines' => [$this->line($this->water, '1.2345')]], 'lines.0.quantity'],
            [['lines' => [$this->line($this->water, 'abc')]], 'lines.0.quantity'],
            [['lines' => [$this->line($this->water, '1', '-1')]], 'lines.0.unit_price'],
            [['lines' => [$this->line($this->water, '1', '2.505')]], 'lines.0.unit_price'],
            [['lines' => [['product_id' => 99999] + $this->line($this->water, '1')]], 'lines.0.product_id'],
            [['lines' => [['warehouse_id' => 99999] + $this->line($this->water, '1')]], 'lines.0.warehouse_id'],
        ];

        foreach ($cases as [$overrides, $expectedKey]) {
            $this->actingAs($this->cashier)->post('/sales', $this->payload($overrides))
                ->assertSessionHasErrors($expectedKey);
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame('100.000', $this->stockOf($this->water));
    }

    public function test_not_enough_stock_is_refused_with_the_available_amount_and_nothing_is_saved(): void
    {
        $this->actingAs($this->cashier)
            ->post('/sales', $this->payload(['lines' => [$this->line($this->water, '100.001')]]))
            ->assertSessionHasErrors('lines');

        // INV-07: the message says which line, where, and how much IS available.
        $message = session('errors')->first('lines');
        $this->assertStringContainsString('Line 1', $message);
        $this->assertStringContainsString('100.000', $message);
        $this->assertStringContainsString('Main store', $message);

        $this->assertSame(0, Sale::count());
        $this->assertSame('100.000', $this->stockOf($this->water));
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'sale.created')->count());

        // The branch store has no water at all, even though the main store has 100.
        $this->actingAs($this->cashier)
            ->post('/sales', $this->payload(['lines' => [$this->line($this->water, '1', null, $this->branch)]]))
            ->assertSessionHasErrors('lines');
        $this->assertSame(0, Sale::count());
    }

    public function test_an_inactive_product_cannot_be_sold(): void
    {
        $old = $this->makeProduct('OLD-001', '1.00', false);

        $this->actingAs($this->cashier)
            ->post('/sales', $this->payload(['lines' => [$this->line($old, '1')]]))
            ->assertSessionHasErrors('lines');

        $this->assertStringContainsString('OLD-001', session('errors')->first('lines'));
        $this->assertSame(0, Sale::count());
    }

    public function test_only_users_with_the_price_override_permission_can_change_a_price(): void
    {
        $boss = $this->userWith('sales.create', 'sales.view', 'sales.price_override');

        // Without the permission, another price is refused ...
        $this->actingAs($this->cashier)
            ->post('/sales', $this->payload(['lines' => [$this->line($this->water, '2', '2.00')]]))
            ->assertSessionHasErrors('lines');
        $this->assertSame(0, Sale::count());

        // ... but a cashier who sends no price sells at the catalogue price.
        $this->actingAs($this->cashier)
            ->post('/sales', $this->payload(['lines' => [$this->line($this->water, '2')]]))
            ->assertSessionHasNoErrors();
        $this->assertSame('2.50', Sale::first()->items[0]->unit_price);

        // With the permission, the price is accepted, and the change is audit-logged (SAL-04).
        $this->actingAs($boss)
            ->post('/sales', $this->payload(['lines' => [$this->line($this->water, '2', '2.00')]]))
            ->assertSessionHasNoErrors();

        $sale = Sale::latest('id')->first();
        $this->assertSame('2.00', $sale->items[0]->unit_price);
        $this->assertSame('4.00', $sale->total_amount);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'sale.price_overridden')->count());
    }

    public function test_the_detail_page_shows_the_sale_with_exact_values(): void
    {
        $sale = $this->existingSale();

        $this->actingAs($this->cashier)->get("/sales/{$sale->id}")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Show')
                ->where('sale.sale_number', $sale->sale_number)
                ->where('sale.status', 'pending')
                ->where('sale.currency', 'ETB')
                ->where('sale.customer.name', 'Abebe Kebede')
                ->where('sale.created_by', $this->cashier->name)
                ->where('sale.total_amount', '25.01')                  // exact strings
                ->has('sale.lines', 2)
                ->where('sale.lines.0.product.sku', 'SKU-001')
                ->where('sale.lines.0.warehouse', 'Main store')
                ->where('sale.lines.0.quantity', '10.000')
                ->where('sale.lines.0.unit_price', '2.50')
                ->where('sale.lines.0.line_total', '25.00')
                ->where('sale.lines.1.line_total', '0.01'));          // 0.005 rounds half-up
    }

    public function test_opening_a_missing_sale_returns_404(): void
    {
        $this->actingAs($this->cashier)->get('/sales/99999')->assertNotFound();
    }
}
