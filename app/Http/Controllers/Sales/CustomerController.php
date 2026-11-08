<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    // The fields we record in the audit log and send to the edit form.
    private const AUDITED_FIELDS = ['name', 'phone', 'email', 'address'];

    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['search'] ?? '');

        $customers = Customer::query()
            ->withCount('sales') // adds "sales_count" to every row with one SQL subquery
            ->when($search !== '', function ($query) use ($search) {
                // Parentheses matter: (name OR phone OR email) is one condition.
                $query->where(fn($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(25)          // never load every row (NFR-PERF-03)
            ->withQueryString()     // keep the search inside the pagination links
            ->through(fn(Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'address' => $customer->address,
                'sales_count' => $customer->sales_count,
                'is_walk_in' => $customer->isWalkIn(),
            ]);

        return Inertia::render('Sales/Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    // Shows the empty form. "customer" is null, which tells the page it is in "create" mode.
    public function create(): Response
    {
        return Inertia::render('Sales/Customers/Form', [
            'customer' => null,
        ]);
    }

    // Shows the same form, filled with the customer's current values.
    public function edit(Customer $customer): Response
    {
        return Inertia::render('Sales/Customers/Form', [
            'customer' => $customer->only(['id', ...self::AUDITED_FIELDS]) + ['is_walk_in' => $customer->isWalkIn()],
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        // Create and audit in one transaction, so neither exists without the other.
        DB::transaction(function () use ($request) {
            $customer = Customer::create($request->validated());

            AuditLogger::log('customer.created', 'customer', $customer->id, null, $customer->only(self::AUDITED_FIELDS));
        });

        return redirect()->route('customers.index')->with('success', 'Customer created.');
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        if ($customer->isWalkIn()) {
            // The Walk-in customer is the record anonymous sales use: its name must stay as it is.
            if (! Customer::isWalkInName($data['name'])) {
                return back()->withErrors(['name' => 'The name of the Walk-in customer cannot be changed.'])->withInput();
            }

            $data['name'] = $customer->name; // even a change of capital letters is ignored
        }

        DB::transaction(function () use ($data, $customer) {
            // fill() sets the new values in memory, and getDirty() tells us what really changed.
            $customer->fill($data);
            $changed = array_keys($customer->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $customer->getOriginal($field);
                $new[$field] = $customer->{$field};
            }

            $customer->save();

            AuditLogger::log('customer.updated', 'customer', $customer->id, $old, $new);
        });

        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->isWalkIn()) {
            return back()->withErrors(['delete' => 'The Walk-in customer is used by anonymous sales and cannot be deleted.']);
        }

        // A customer with sales keeps its history: we check first for a friendly message.
        if ($customer->sales()->exists()) {
            return back()->withErrors(['delete' => 'This customer has sales, so it cannot be deleted.']);
        }

        try {
            DB::transaction(function () use ($customer) {
                $id = $customer->id;
                $old = $customer->only(self::AUDITED_FIELDS);

                $customer->delete();

                AuditLogger::log('customer.deleted', 'customer', $id, $old, null);
            });
        } catch (QueryException) {
            // Safety net: if a sale was created a moment ago, MySQL still refuses (restrictOnDelete).
            // The transaction rolled back, so nothing was deleted and no audit row was written.
            return back()->withErrors(['delete' => 'This customer has sales, so it cannot be deleted.']);
        }

        return redirect()->route('customers.index')->with('success', 'Customer deleted.');
    }
}
