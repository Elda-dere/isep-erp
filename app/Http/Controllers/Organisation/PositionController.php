<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Requests\Organisation\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use App\Services\OrganisationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController
{
    public function __construct(private OrganisationService $organisation) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'department' => ['nullable', 'integer'],
        ]);

        return Inertia::render('Positions/Index', [
            'positions' => Position::query()
                ->with('department:id,name')
                ->withCount('employees')
                ->when($filters['department'] ?? null, fn ($query, $value) => $query->where('department_id', $value))
                ->orderBy('title')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Position $position) => [
                    'id' => $position->id,
                    'title' => $position->title,
                    'department_id' => $position->department_id,
                    'department' => $position->department->name,
                    'employees_count' => $position->employees_count,
                ]),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['department']),
        ]);
    }

    public function store(PositionRequest $request): RedirectResponse
    {
        $this->organisation->createPosition($request->validated());

        return back()->with('success', 'Position created.');
    }

    public function update(PositionRequest $request, Position $position): RedirectResponse
    {
        $this->organisation->updatePosition($position, $request->validated());

        return back()->with('success', 'Position updated.');
    }

    public function destroy(Position $position): RedirectResponse
    {
        $this->organisation->deletePosition($position);

        return back()->with('success', 'Position deleted.');
    }
}