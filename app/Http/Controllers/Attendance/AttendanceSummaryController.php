<?php

namespace App\Http\Controllers\Attendance;

use App\Models\Department;
use App\Services\AttendanceSummaryService;
use App\Services\AuditLogger;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceSummaryController
{
    private const HEADER = [
        'Employee number', 'Name', 'Department', 'Position', 'Status',
        'Days worked', 'Late days', 'Hours worked', 'Missing check-outs', 'Absent days',
    ];

    public function __construct(private AttendanceSummaryService $summary) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $employees = $this->summary->employees($filters['month'], $filters['department'])
            ->paginate(25)
            ->withQueryString();

        // Only the 25 people on this page are worked out.
        $employees->setCollection(collect($this->summary->rows(collect($employees->items()), $filters['month'])));

        return Inertia::render('Attendance/Summary', [
            'rows' => $employees,
            'month' => $filters['month'],
            'currentMonth' => $this->summary->currentMonth(),
            'timezone' => config('erp.timezone'),
            'countedThrough' => $this->summary->lastCountedDay($filters['month']),
            'workingDayNames' => $this->summary->workingDayNames(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['month', 'department']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $month = $filters['month'];
        $query = $this->summary->employees($month, $filters['department']);

        // Bulk personal data leaves the system, so the export itself is recorded.
        AuditLogger::log('attendance.summary_exported', null, null, null, [
            'month' => $month,
            'rows' => (clone $query)->count(),
            'department' => $filters['department'],
        ]);

        return response()->streamDownload(function () use ($query, $month) {
            $out = fopen('php://output', 'w');

            // A UTF-8 byte order mark, so Excel shows non-English names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADER, ',', '"', '');

            $query->chunk(200, function ($employees) use ($out, $month) {
                foreach ($this->summary->rows($employees, $month) as $row) {
                    fputcsv($out, [
                        CsvSafe::cell($row['employee_number']),
                        CsvSafe::cell($row['name']),
                        CsvSafe::cell($row['department']),
                        CsvSafe::cell($row['position']),
                        $row['status'],
                        $row['days'],
                        $row['late'],
                        number_format($row['minutes'] / 60, 2, '.', ''),
                        $row['missing'],
                        // Empty, not a wrong number, when absence cannot be worked out.
                        $row['absent'] ?? '',
                    ], ',', '"', '');
                }
            });

            fclose($out);
        }, 'attendance-summary-'.$month.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{month: string, department: ?int} */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'department' => ['nullable', 'integer'],
        ]);

        $current = $this->summary->currentMonth();
        $month = $data['month'] ?? $current;

        if ($month > $current) {
            throw ValidationException::withMessages(['month' => 'Choose this month or an earlier one.']);
        }

        return ['month' => $month, 'department' => isset($data['department']) ? (int) $data['department'] : null];
    }
}