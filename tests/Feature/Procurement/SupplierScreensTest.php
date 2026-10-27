<?php

namespace Tests\Feature\Procurement;

use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests
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

    private function audit(string $action)
    {
        return DB::table('audit_logs')->where('action', $action)->get();
    }

    // The JSON columns come back as text: turn them into arrays.
    private function values(object $row, string $column): ?array
    {
        return $row->{$column} === null ? null : json_decode($row->{$column}, true);
    }

    public function test_users_without_suppliers_manage_get_403_everywhere(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Trading']);
        // Holding other procurement permissions is not enough.
        $other = $this->userWith('purchase_orders.view', 'purchase_orders.manage', 'products.manage');

        $this->actingAs($other)->get('/suppliers')->assertForbidden();
        $this->actingAs($other)->get('/suppliers/create')->assertForbidden();
        $this->actingAs($other)->get("/suppliers/{$supplier->id}/edit")->assertForbidden();
        $this->actingAs($other)->post('/suppliers', ['name' => 'New'])->assertForbidden();
        $this->actingAs($other)->put("/suppliers/{$supplier->id}", ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($other)->delete("/suppliers/{$supplier->id}")->assertForbidden();

        $this->assertDatabaseMissing('suppliers', ['name' => 'New']);
        $this->assertSame('Acme Trading', $supplier->fresh()->name);
    }

    public function test_the_list_shows_suppliers_with_purchase_order_counts(): void
    {
        $manager = $this->userWith('suppliers.manage');
        $acme = Supplier::create(['name' => 'Acme Trading']);
        Supplier::create(['name' => 'Zeta Supplies']);
        PurchaseOrder::create([
            'supplier_id' => $acme->id,
            'order_number' => 'PO-2026-0001',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->get('/suppliers')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/Suppliers/Index')
                ->has('suppliers.data', 2)
                ->where('suppliers.data.0.name', 'Acme Trading')
                ->where('suppliers.data.0.purchase_orders_count', 1)
                ->where('suppliers.data.1.name', 'Zeta Supplies')
                ->where('suppliers.data.1.purchase_orders_count', 0));
    }

    public function test_creating_a_supplier_writes_an_audit_row(): void
    {
        $manager = $this->userWith('suppliers.manage');

        $this->actingAs($manager)
            ->post('/suppliers', [
                'name' => 'Acme Trading',
                'phone' => '+251911000000',
                'email' => 'sales@acme.test',
                'address' => 'Bole, Addis Ababa',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::where('name', 'Acme Trading')->first();
        $rows = $this->audit('supplier.created');

        $this->assertCount(1, $rows);
        $this->assertSame($manager->id, (int) $rows->first()->user_id);
        $this->assertSame($supplier->id, (int) $rows->first()->entity_id);
        $this->assertSame('sales@acme.test', $this->values($rows->first(), 'new_values')['email']);
    }

    public function test_the_name_is_required_and_the_email_must_be_valid(): void
    {
        $manager = $this->userWith('suppliers.manage');

        $this->actingAs($manager)->post('/suppliers', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($manager)->post('/suppliers', ['name' => 'Bad email', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('suppliers', 0);

        // Phone, email and address are all optional.
        $this->actingAs($manager)->post('/suppliers', ['name' => 'Only a name'])->assertSessionHasNoErrors();
        $this->assertNull(Supplier::where('name', 'Only a name')->first()->email);
    }

    public function test_the_edit_form_shows_the_current_values(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Trading', 'phone' => '0911', 'email' => 'a@acme.test', 'address' => 'Bole']);
        $manager = $this->userWith('suppliers.manage');

        $this->actingAs($manager)->get("/suppliers/{$supplier->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Procurement/Suppliers/Form')
                ->where('supplier.id', $supplier->id)
                ->where('supplier.name', 'Acme Trading')
                ->where('supplier.phone', '0911')
                ->where('supplier.email', 'a@acme.test')
                ->where('supplier.address', 'Bole'));
    }

    public function test_an_update_logs_only_the_changed_fields(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Trading', 'phone' => '0911']);
        $manager = $this->userWith('suppliers.manage');

        $this->actingAs($manager)
            ->put("/suppliers/{$supplier->id}", ['name' => 'Acme Trading', 'phone' => '0922'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('suppliers.index'));

        $rows = $this->audit('supplier.updated');
        $this->assertCount(1, $rows);
        $this->assertSame(['phone' => '0911'], $this->values($rows->first(), 'old_values'));
        $this->assertSame(['phone' => '0922'], $this->values($rows->first(), 'new_values'));
    }

    public function test_a_supplier_without_orders_can_be_deleted_and_the_deletion_is_logged(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Trading']);
        $manager = $this->userWith('suppliers.manage');

        $this->actingAs($manager)->delete("/suppliers/{$supplier->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);

        $rows = $this->audit('supplier.deleted');
        $this->assertCount(1, $rows);
        $this->assertSame('Acme Trading', $this->values($rows->first(), 'old_values')['name']);
    }

    public function test_a_supplier_with_purchase_orders_cannot_be_deleted(): void
    {
        $supplier = Supplier::create(['name' => 'Acme Trading']);
        $manager = $this->userWith('suppliers.manage');
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'order_number' => 'PO-2026-0001',
            'status' => PurchaseOrder::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->delete("/suppliers/{$supplier->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertCount(0, $this->audit('supplier.deleted')); // nothing happened, so nothing is logged
    }
}
