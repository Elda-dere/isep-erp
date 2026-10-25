<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Requests\Organisation\DepartmentRequest;
use App\Models\Department;
use App\Services\OrganisationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController
{
    public function __construct(private OrganisationService $organisation) {}

    public function index(): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::query()
                ->withCount('positions')
                ->orderBy('name')
                ->paginate(25)
                ->through(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'description' => $department->description,
                    'positions_count' => $department->positions_count,
                ]),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $this->organisation->createDepartment($request->validated());

        return back()->with('success', 'Department created.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->organisation->updateDepartment($department, $request->validated());

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->organisation->deleteDepartment($department);

        return back()->with('success', 'Department deleted.');
    }
}