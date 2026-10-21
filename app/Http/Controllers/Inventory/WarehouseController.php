<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
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
        // TODO (AUD-01): audit-log warehouse creation once the audit writer is merged.
        Warehouse::create($request->validated());

        return redirect()->route('warehouses.index')->with('success', 'Warehouse created.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        // TODO (AUD-01): audit-log warehouse changes once the audit writer is merged.
        $warehouse->update($request->validated());

        return redirect()->route('warehouses.index')->with('success', 'Warehouse updated.');
    }
}
