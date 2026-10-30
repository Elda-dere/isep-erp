<?php

namespace App\Http\Controllers\Organisation;

use App\Models\Department;
use App\Models\Employee;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController
{
    public function index(): Response
    {
        $departments = Department::query()
            ->with(['positions' => fn ($query) => $query
                ->orderBy('title')
                // Headcount counts everyone who is not terminated.
                ->withCount(['employees as headcount' => fn ($employees) => $employees
                    ->where('employment_status', '!=', 'terminated')])])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
                'headcount' => (int) $department->positions->sum('headcount'),
                'positions' => $department->positions
                    ->map(fn ($position) => [
                        'id' => $position->id,
                        'title' => $position->title,
                        'headcount' => (int) $position->headcount,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $byStatus = Employee::query()
            ->selectRaw('employment_status, count(*) as total')
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        return Inertia::render('Organisation/Overview', [
            'departments' => $departments,
            'headcount' => (int) collect($departments)->sum('headcount'),
            // All four statuses are always listed, even when nobody has one.
            'statuses' => collect(Employee::STATUSES)
                ->map(fn (string $status) => ['status' => $status, 'total' => (int) ($byStatus[$status] ?? 0)])
                ->all(),
        ]);
    }
}