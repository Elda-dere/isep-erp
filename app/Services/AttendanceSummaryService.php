<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceSummaryService
{
    /** The current month in business time, written like 2026-10. */
    public function currentMonth(): string
    {
        return now()->setTimezone(config('erp.timezone'))->format('Y-m');
    }

    /**
     * ISO weekday numbers that count as working days (1 = Monday ... 7 = Sunday).
     * A missing or unreadable setting falls back to Monday to Friday.
     *
     * @return array<int, int>
     */
    public function workingDays(): array
    {
        $days = collect(explode(',', (string) config('attendance.working_days', '1,2,3,4,5')))
            ->map(fn ($day) => (int) trim($day))
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        return $days === [] ? [1, 2, 3, 4, 5] : $days;
    }

    /** Short names of the working days, for the page, for example Mon, Tue. */
    public function workingDayNames(): array
    {
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return array_map(fn (int $day) => $names[$day], $this->workingDays());
    }

    /**
     * The last day that counts towards absence: the end of the month, or yesterday for a month
     * that is still running (today is not over yet). Null when no day of the month counts yet.
     */
    public function lastCountedDay(string $month): ?string
    {
        $zone = config('erp.timezone');
        $start = Carbon::parse($month.'-01', $zone)->startOfDay();

        $last = $start->copy()->endOfMonth()->toDateString();
        $yesterday = now()->setTimezone($zone)->subDay()->toDateString();
        $counted = $last < $yesterday ? $last : $yesterday;

        return $counted < $start->toDateString() ? null : $counted;
    }

    /** Employees listed for the month: active people hired by its end, plus anyone with a record in it. */
    public function employees(string $month, ?int $department): Builder
    {
        $start = Carbon::parse($month.'-01', config('erp.timezone'))->startOfDay();
        $first = $start->toDateString();
        $last = $start->copy()->endOfMonth()->toDateString();

        return Employee::query()
            ->where(function ($query) use ($first, $last) {
                $query
                    ->where(fn ($q) => $q->where('employment_status', 'active')->where('hire_date', '<=', $last))
                    ->orWhereIn('id', AttendanceRecord::query()
                        ->whereBetween('attendance_date', [$first, $last])
                        ->select('employee_id'));
            })
            ->when($department, fn ($q, $value) => $q
                ->whereHas('position', fn ($p) => $p->where('department_id', $value)))
            ->with('position.department')
            ->orderBy('employee_number')
            ->orderBy('id');
    }

    /** Hours and minutes, for example 8h 05m. */
    public function hours(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * One summary row for every employee in the collection.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, mixed>>
     */
    public function rows(Collection $employees, string $month): array
    {
        $zone = config('erp.timezone');
        $start = Carbon::parse($month.'-01', $zone)->startOfDay();
        $first = $start->toDateString();
        $last = $start->copy()->endOfMonth()->toDateString();
        $today = now()->setTimezone($zone)->toDateString();
        $lastCounted = $this->lastCountedDay($month);
        $working = $this->workingDays();
        $ids = $employees->pluck('id')->all();

        // Missing check-outs leave out today, so someone who is still at work is not flagged.
        $stats = AttendanceRecord::query()
            ->whereBetween('attendance_date', [$first, $last])
            ->whereIn('employee_id', $ids)
            ->selectRaw(
                'employee_id,
                 COUNT(*) AS days,
                 SUM(status = ?) AS late,
                 SUM(check_out_at IS NULL AND attendance_date < ?) AS missing,
                 COALESCE(SUM(CASE WHEN check_out_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) ELSE 0 END), 0) AS seconds',
                ['late', $today],
            )
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $recordedDates = AttendanceRecord::query()
            ->whereBetween('attendance_date', [$first, $last])
            ->whereIn('employee_id', $ids)
            ->get(['employee_id', 'attendance_date'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => array_flip($rows->map(fn ($row) => $row->attendance_date->format('Y-m-d'))->all()));

        return $employees->map(function (Employee $employee) use ($stats, $recordedDates, $first, $lastCounted, $working, $zone) {
            $stat = $stats->get($employee->id);
            $minutes = intdiv((int) ($stat->seconds ?? 0), 60);

            // Absence is only worked out for active employees: the schema keeps no history of leave or termination.
            $absent = null;

            if ($employee->employment_status === 'active') {
                $absent = 0;

                if ($lastCounted !== null) {
                    $hired = $employee->hire_date->format('Y-m-d');
                    $from = $hired > $first ? $hired : $first;
                    $recorded = $recordedDates->get($employee->id, []);

                    for ($day = Carbon::parse($from, $zone); $day->toDateString() <= $lastCounted; $day->addDay()) {
                        if (in_array($day->dayOfWeekIso, $working, true) && ! isset($recorded[$day->toDateString()])) {
                            $absent++;
                        }
                    }
                }
            }

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->fullName(),
                'department' => $employee->position->department->name,
                'position' => $employee->position->title,
                'status' => $employee->employment_status,
                'days' => (int) ($stat->days ?? 0),
                'late' => (int) ($stat->late ?? 0),
                'minutes' => $minutes,
                'worked' => $this->hours($minutes),
                'missing' => (int) ($stat->missing ?? 0),
                'absent' => $absent,
            ];
        })->values()->all();
    }
}