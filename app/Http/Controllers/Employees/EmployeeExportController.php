<?php

namespace App\Http\Controllers\Employees;

use App\Models\Employee;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeExportController
{
    private const HEADER = [
        'Employee number', 'First name', 'Last name', 'Phone', 'Hire date', 'Position', 'Department', 'Status',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        // The same filters as the employee list (no sorting: the file is always in number order).
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Employee::STATUSES)],
        ]);

        $query = Employee::query()
            ->with('position.department')
            ->when($filters['search'] ?? null, function ($query, $value) {
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
            ->orderBy('employee_number')
            ->orderBy('id');

        // Bulk personal data leaves the system, so the export itself is recorded (AUD, NFR-SEC-07).
        AuditLogger::log('employee.exported', 'employee', null, null, [
            'rows' => $query->count(),
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);

        $filename = 'employees-'.now(config('erp.timezone'))->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // A UTF-8 byte order mark, so Excel shows non-English names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADER, ',', '"', '');

            $query->chunk(500, function ($employees) use ($out) {
                foreach ($employees as $employee) {
                    fputcsv($out, [
                        self::text($employee->employee_number),
                        self::text($employee->first_name),
                        self::text($employee->last_name),
                        self::phone($employee->phone),
                        $employee->hire_date?->format('Y-m-d'),
                        self::text($employee->position->title),
                        self::text($employee->position->department->name),
                        $employee->employment_status,
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A cell starting with = + - @ tab or return could run as a formula in a spreadsheet, so it gets an apostrophe. */
    private static function text(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** Phone numbers are checked on input to hold only digits, + ( ) - and spaces, so they cannot hold a formula. */
    private static function phone(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[0-9+()\-\s]*$/', $value) === 1 ? $value : self::text($value);
    }
}