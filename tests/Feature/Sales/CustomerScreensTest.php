<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // no compiled frontend files needed in tests

        $this->manager = $this->userWith('customers.manage');
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

    // The seeded record is not created by the tests' empty database, so we create it here.
    private function walkIn(): Customer
    {
        return Customer::create(['name' => Customer::WALK_IN_NAME]);
    }

    // Gives the customer one sale, so it is "in use".
    private function sale(Customer $customer): Sale
    {
        return Sale::create([
            'sale_number' => 'SAL-TEST-' . uniqid(),
            'customer_id' => $customer->id,
            'status' => Sale::STATUS_PENDING,
            'total_amount' => '0.00',
            'currency' => 'ETB',
            'created_by' => $this->manager->id,
        ]);
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

    public function test_users_without_customers_manage_get_403_everywhere(): void
    {
        $customer = Customer::create(['name' => 'Abebe Kebede']);
        // Holding sales permissions is not enough to manage customers.
        $other = $this->userWith('sales.view', 'sales.create', 'sales.cancel');

        $this->actingAs($other)->get('/customers')->assertForbidden();
        $this->actingAs($other)->get('/customers/create')->assertForbidden();
        $this->actingAs($other)->get("/customers/{$customer->id}/edit")->assertForbidden();
        $this->actingAs($other)->post('/customers', ['name' => 'New'])->assertForbidden();
        $this->actingAs($other)->put("/customers/{$customer->id}", ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($other)->delete("/customers/{$customer->id}")->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'New']);
        $this->assertSame('Abebe Kebede', $customer->fresh()->name);
    }

    public function test_the_list_shows_customers_by_name_with_sales_counts(): void
    {
        $abebe = Customer::create(['name' => 'Abebe Kebede']);
        $this->walkIn();
        Customer::create(['name' => 'Zeta Shop']);
        $this->sale($abebe);

        $this->actingAs($this->manager)->get('/customers')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Customers/Index')
                ->has('customers.data', 3)
                ->where('customers.data.0.name', 'Abebe Kebede')
                ->where('customers.data.0.sales_count', 1)
                ->where('customers.data.0.is_walk_in', false)
                ->where('customers.data.1.name', 'Walk-in customer')
                ->where('customers.data.1.is_walk_in', true)
                ->where('customers.data.2.name', 'Zeta Shop')
                ->where('customers.data.2.sales_count', 0));
    }

    public function test_the_list_can_be_searched_by_name_phone_or_email(): void
    {
        Customer::create(['name' => 'Abebe Kebede', 'phone' => '0911000111']);
        Customer::create(['name' => 'Zeta Shop', 'email' => 'orders@zeta.test']);

        $this->actingAs($this->manager)->get('/customers?search=abebe')
            ->assertInertia(fn(Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Abebe Kebede')
                ->where('filters.search', 'abebe'));

        $this->actingAs($this->manager)->get('/customers?search=0911')
            ->assertInertia(fn(Assert $page) => $page->has('customers.data', 1));

        $this->actingAs($this->manager)->get('/customers?search=zeta.test')
            ->assertInertia(fn(Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Zeta Shop'));

        $this->actingAs($this->manager)->get('/customers?search=nobody')
            ->assertInertia(fn(Assert $page) => $page->has('customers.data', 0));
    }

    public function test_creating_a_customer_writes_an_audit_row(): void
    {
        $this->actingAs($this->manager)
            ->post('/customers', [
                'name' => 'Abebe Kebede',
                'phone' => '0911000111',
                'email' => 'abebe@example.test',
                'address' => 'Bole, Addis Ababa',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'Abebe Kebede')->first();
        $rows = $this->audit('customer.created');

        $this->assertCount(1, $rows);
        $this->assertSame($this->manager->id, (int) $rows->first()->user_id);
        $this->assertSame($customer->id, (int) $rows->first()->entity_id);
        $this->assertSame('abebe@example.test', $this->values($rows->first(), 'new_values')['email']);
    }

    public function test_the_data_is_validated(): void
    {
        $this->actingAs($this->manager)->post('/customers', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->manager)->post('/customers', ['name' => str_repeat('a', 181)])->assertSessionHasErrors('name');
        $this->actingAs($this->manager)->post('/customers', ['name' => 'Bad email', 'email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->actingAs($this->manager)->post('/customers', ['name' => 'Long phone', 'phone' => str_repeat('1', 31)])->assertSessionHasErrors('phone');
        $this->assertSame(0, Customer::count());

        // Phone, email and address are all optional.
        $this->actingAs($this->manager)->post('/customers', ['name' => 'Only a name'])->assertSessionHasNoErrors();
        $this->assertNull(Customer::where('name', 'Only a name')->first()->email);
    }

    public function test_the_walk_in_name_is_reserved_for_the_seeded_record(): void
    {
        $this->walkIn();

        // A new customer cannot take the name, whatever the capital letters.
        $this->actingAs($this->manager)->post('/customers', ['name' => 'WALK-IN CUSTOMER'])->assertSessionHasErrors('name');
        $this->assertSame(1, Customer::count());

        // Neither can an existing customer be renamed to it.
        $other = Customer::create(['name' => 'Abebe Kebede']);
        $this->actingAs($this->manager)->put("/customers/{$other->id}", ['name' => 'walk-in customer'])->assertSessionHasErrors('name');
        $this->assertSame('Abebe Kebede', $other->fresh()->name);
    }

    public function test_the_edit_form_shows_the_current_values(): void
    {
        $customer = Customer::create(['name' => 'Abebe Kebede', 'phone' => '0911', 'email' => 'a@example.test', 'address' => 'Bole']);
        $walkIn = $this->walkIn();

        $this->actingAs($this->manager)->get("/customers/{$customer->id}/edit")
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Customers/Form')
                ->where('customer.id', $customer->id)
                ->where('customer.name', 'Abebe Kebede')
                ->where('customer.phone', '0911')
                ->where('customer.email', 'a@example.test')
                ->where('customer.address', 'Bole')
                ->where('customer.is_walk_in', false));

        $this->actingAs($this->manager)->get("/customers/{$walkIn->id}/edit")
            ->assertInertia(fn(Assert $page) => $page->where('customer.is_walk_in', true));

        $this->actingAs($this->manager)->get('/customers/create')
            ->assertInertia(fn(Assert $page) => $page
                ->component('Sales/Customers/Form')
                ->where('customer', null));
    }

    public function test_an_update_logs_only_the_changed_fields(): void
    {
        $customer = Customer::create(['name' => 'Abebe Kebede', 'phone' => '0911']);

        $this->actingAs($this->manager)
            ->put("/customers/{$customer->id}", ['name' => 'Abebe Kebede', 'phone' => '0922'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $rows = $this->audit('customer.updated');
        $this->assertCount(1, $rows);
        $this->assertSame(['phone' => '0911'], $this->values($rows->first(), 'old_values'));
        $this->assertSame(['phone' => '0922'], $this->values($rows->first(), 'new_values'));

        // Saving again without changes writes nothing.
        $this->actingAs($this->manager)->put("/customers/{$customer->id}", ['name' => 'Abebe Kebede', 'phone' => '0922']);
        $this->assertCount(1, $this->audit('customer.updated'));
    }

    public function test_the_walk_in_customer_cannot_be_renamed_but_its_other_fields_can_change(): void
    {
        $walkIn = $this->walkIn();

        $this->actingAs($this->manager)->put("/customers/{$walkIn->id}", ['name' => 'Someone else'])
            ->assertSessionHasErrors('name');
        $this->assertSame('Walk-in customer', $walkIn->fresh()->name);

        // The same name, written differently, is accepted but the stored name does not change.
        $this->actingAs($this->manager)
            ->put("/customers/{$walkIn->id}", ['name' => 'walk-in customer', 'phone' => '0911'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Walk-in customer', $walkIn->fresh()->name);
        $this->assertSame('0911', $walkIn->fresh()->phone);
    }

    public function test_a_customer_without_sales_can_be_deleted_and_the_deletion_is_logged(): void
    {
        $customer = Customer::create(['name' => 'Abebe Kebede']);

        $this->actingAs($this->manager)->delete("/customers/{$customer->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);

        $rows = $this->audit('customer.deleted');
        $this->assertCount(1, $rows);
        $this->assertSame('Abebe Kebede', $this->values($rows->first(), 'old_values')['name']);
    }

    public function test_a_customer_with_sales_cannot_be_deleted(): void
    {
        $customer = Customer::create(['name' => 'Abebe Kebede']);
        $this->sale($customer);

        $this->actingAs($this->manager)->delete("/customers/{$customer->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertCount(0, $this->audit('customer.deleted')); // nothing happened, so nothing is logged
    }

    public function test_the_walk_in_customer_cannot_be_deleted(): void
    {
        $walkIn = $this->walkIn(); // it has no sales, yet it must stay

        $this->actingAs($this->manager)->delete("/customers/{$walkIn->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('customers', ['id' => $walkIn->id]);
        $this->assertCount(0, $this->audit('customer.deleted'));
    }
}
