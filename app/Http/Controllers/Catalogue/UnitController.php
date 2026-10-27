<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    // The fields we record in the audit log and send to the edit form.
    private const AUDITED_FIELDS = ['name', 'symbol'];

    public function index(): Response
    {
        return Inertia::render('Catalogue/Units/Index', [
            'units' => Unit::withCount('products')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Catalogue/Units/Form', [
            'unit' => null,
        ]);
    }

    public function edit(Unit $unit): Response
    {
        return Inertia::render('Catalogue/Units/Form', [
            'unit' => $unit->only(['id', ...self::AUDITED_FIELDS]),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $unit = Unit::create($request->validated());

            AuditLogger::log('unit.created', 'unit', $unit->id, null, $unit->only(self::AUDITED_FIELDS));
        });

        return redirect()->route('units.index')->with('success', 'Unit created.');
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($request, $unit) {
            $unit->fill($request->validated());
            $changed = array_keys($unit->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $unit->getOriginal($field);
                $new[$field] = $unit->{$field};
            }

            $unit->save();

            AuditLogger::log('unit.updated', 'unit', $unit->id, $old, $new);
        });

        return redirect()->route('units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        // PRD-02: a unit in use cannot be deleted.
        if ($unit->products()->exists()) {
            return back()->withErrors(['delete' => 'This unit is used by products, so it cannot be deleted.']);
        }

        try {
            DB::transaction(function () use ($unit) {
                $id = $unit->id;
                $old = $unit->only(self::AUDITED_FIELDS);

                $unit->delete();

                AuditLogger::log('unit.deleted', 'unit', $id, $old, null);
            });
        } catch (QueryException) {
            // Safety net: MySQL still refuses if a product started using the unit a moment ago.
            return back()->withErrors(['delete' => 'This unit is used by products, so it cannot be deleted.']);
        }

        return redirect()->route('units.index')->with('success', 'Unit deleted.');
    }
}
