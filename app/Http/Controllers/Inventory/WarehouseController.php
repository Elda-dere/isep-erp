<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => Warehouse::orderBy('name')->paginate(25),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        // Create and audit in one transaction, so neither exists without the other.
        DB::transaction(function () use ($request) {
            $warehouse = Warehouse::create($request->validated());

            AuditLogger::log('warehouse.created', 'warehouse', $warehouse->id, null, $warehouse->only(['name', 'location']));
        });

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        DB::transaction(function () use ($request, $warehouse) {
            // Same pattern as products: fill in memory, ask which fields really changed, then save.
            $warehouse->fill($request->validated());
            $changed = array_keys($warehouse->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $warehouse->getOriginal($field);
                $new[$field] = $warehouse->{$field};
            }

            $warehouse->save();

            AuditLogger::log('warehouse.updated', 'warehouse', $warehouse->id, $old, $new);
        });

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated.');
    }
}
