<?php

namespace App\Http\Controllers\Employees;

use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController
{
    public function __construct(private EmployeeService $employees) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Employee::STATUSES)],
            'sort' => ['nullable', Rule::in(['employee_number', 'name', 'hire_date'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'employee_number';
        $direction = $filters['direction'] ?? 'asc';

        $employees = Employee::query()
            ->with('position.department')
            ->when($filters['search'] ?? null, function ($query, $value) {
                // % and _ are escaped so a person finds exactly what they typed.
                $like = '%'.addcslashes($value, '%_\\').'%';

                $query->where(function ($q) use ($like) {
                    $q->where('employee_number', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('employment_status', $value))
            ->when($filters['position'] ?? null, fn ($q, $value) => $q->where('position_id', $value))
            ->when($filters['department'] ?? null, fn ($q, $value) => $q->whereHas('position', fn ($p) => $p->where('department_id', $value)))
            ->when(
                $sort === 'name',
                fn ($q) => $q->orderBy('last_name', $direction)->orderBy('first_name', $direction),
                fn ($q) => $q->orderBy($sort, $direction),
            )
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->fullName(),
                'phone' => $employee->phone,
                'hire_date' => $employee->hire_date?->format('Y-m-d'),
                'status' => $employee->employment_status,
                'position' => $employee->position->title,
                'department' => $employee->position->department->name,
                'has_login' => $employee->user_id !== null,
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'department', 'position', 'status', 'sort', 'direction']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('title')->get(['id', 'title', 'department_id']),
            'statuses' => Employee::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            'suggestedNumber' => $this->employees->nextNumber(),
            'positionGroups' => $this->positionGroups(),
            'users' => $this->linkableUsers(null),
            'statuses' => Employee::STATUSES,
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employees->create($request->validated());

        return redirect()->route('employees.index')->with('success', 'Employee created.');
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'phone' => $employee->phone,
                'hire_date' => $employee->hire_date?->format('Y-m-d'),
                'position_id' => $employee->position_id,
                'user_id' => $employee->user_id,
                'employment_status' => $employee->employment_status,
            ],
            'suggestedNumber' => null,
            'positionGroups' => $this->positionGroups(),
            'users' => $this->linkableUsers($employee),
            'statuses' => Employee::STATUSES,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employees->update($employee, $request->validated());

        return redirect()->route('employees.index')->with('success', 'Employee updated.');
    }

    public function status(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Employee::STATUSES)],
        ]);

        $this->employees->changeStatus($employee, $validated['status']);

        return back()->with('success', 'Status updated.');
    }

    /** Positions grouped by department, for a select with groups. */
    private function positionGroups(): array
    {
        return Department::query()
            ->with(['positions' => fn ($query) => $query->orderBy('title')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Department $department) => $department->positions->isNotEmpty())
            ->map(fn (Department $department) => [
                'department' => $department->name,
                'positions' => $department->positions
                    ->map(fn (Position $position) => ['id' => $position->id, 'title' => $position->title])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** Users that are not yet linked to another employee (plus this employee's own login). */
    private function linkableUsers(?Employee $employee)
    {
        $taken = Employee::query()
            ->whereNotNull('user_id')
            ->when($employee, fn ($query) => $query->where('id', '!=', $employee->id))
            ->pluck('user_id');

        return User::query()->whereNotIn('id', $taken)->orderBy('name')->get(['id', 'name', 'email']);
    }
}