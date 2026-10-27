<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    // The fields we record in the audit log and send to the edit form.
    private const AUDITED_FIELDS = ['name', 'phone', 'email', 'address'];

    public function index(): Response
    {
        return Inertia::render('Procurement/Suppliers/Index', [
            // withCount adds "purchase_orders_count" to every row with one SQL subquery.
            'suppliers' => Supplier::withCount('purchaseOrders')->orderBy('name')->paginate(25),
        ]);
    }

    // Shows the empty form. "supplier" is null, which tells the page it is in "create" mode.
    public function create(): Response
    {
        return Inertia::render('Procurement/Suppliers/Form', [
            'supplier' => null,
        ]);
    }

    // Shows the same form, filled with the supplier's current values.
    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Procurement/Suppliers/Form', [
            'supplier' => $supplier->only(['id', ...self::AUDITED_FIELDS]),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        // Create and audit in one transaction, so neither exists without the other.
        DB::transaction(function () use ($request) {
            $supplier = Supplier::create($request->validated());

            AuditLogger::log('supplier.created', 'supplier', $supplier->id, null, $supplier->only(self::AUDITED_FIELDS));
        });

        return redirect()->route('suppliers.index')->with('success', 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier) {
            // fill() sets the new values in memory, and getDirty() tells us what really changed.
            $supplier->fill($request->validated());
            $changed = array_keys($supplier->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $supplier->getOriginal($field);
                $new[$field] = $supplier->{$field};
            }

            $supplier->save();

            AuditLogger::log('supplier.updated', 'supplier', $supplier->id, $old, $new);
        });

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        // PRC-01: a supplier with purchase orders cannot be deleted. We check first for a friendly message.
        if ($supplier->purchaseOrders()->exists()) {
            return back()->withErrors(['delete' => 'This supplier has purchase orders, so it cannot be deleted.']);
        }

        try {
            DB::transaction(function () use ($supplier) {
                $id = $supplier->id;
                $old = $supplier->only(self::AUDITED_FIELDS);

                $supplier->delete();

                AuditLogger::log('supplier.deleted', 'supplier', $id, $old, null);
            });
        } catch (QueryException) {
            // Safety net: if an order was created a moment ago, MySQL still refuses (restrictOnDelete).
            // The transaction rolled back, so nothing was deleted and no audit row was written.
            return back()->withErrors(['delete' => 'This supplier has purchase orders, so it cannot be deleted.']);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }
}
