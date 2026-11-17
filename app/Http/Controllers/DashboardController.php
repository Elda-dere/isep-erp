<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Support\AttendanceFormat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Determine if user has managerial/administrative visibility
        $hasAdminAccess = $user->hasPermission('inventory.view')
            || $user->hasPermission('sales.view')
            || $user->hasPermission('employees.view')
            || $user->roles()->whereIn('name', ['Administrator', 'Manager', 'HR Manager'])->exists();

        // ─────────────────────────────────────────────────────────────
        // 1. EMPLOYEE SELF-SERVICE DASHBOARD
        // ─────────────────────────────────────────────────────────────
        if (! $hasAdminAccess) {
            $employee = Employee::query()
                ->with(['position.department'])
                ->where('user_id', $user->id)
                ->first();

            $zone = config('erp.timezone');
            $todayDate = now()->setTimezone($zone)->toDateString();

            $todayRecord = null;
            $recentRecords = [];
            $stats = [
                'days_present' => 0,
                'days_late' => 0,
                'hours_worked' => '0h 00m',
                'on_time_rate' => '100%',
            ];

            if ($employee) {
                $todayRecord = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->where('attendance_date', $todayDate)
                    ->first();

                // Records for the current month
                $monthStart = now()->setTimezone($zone)->startOfMonth()->toDateString();
                $monthRecords = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->where('attendance_date', '>=', $monthStart)
                    ->get();

                $totalMinutes = 0;
                $lateCount = 0;
                foreach ($monthRecords as $rec) {
                    if ($rec->status === 'late') {
                        $lateCount++;
                    }
                    if ($rec->check_in_at && $rec->check_out_at) {
                        $mins = max(0, intdiv($rec->check_out_at->getTimestamp() - $rec->check_in_at->getTimestamp(), 60));
                        $totalMinutes += $mins;
                    }
                }

                $presentCount = $monthRecords->count();
                $onTimeRate = $presentCount > 0 ? round((($presentCount - $lateCount) / $presentCount) * 100) : 100;

                $stats = [
                    'days_present' => $presentCount,
                    'days_late' => $lateCount,
                    'hours_worked' => sprintf('%dh %02dm', intdiv($totalMinutes, 60), $totalMinutes % 60),
                    'on_time_rate' => $onTimeRate . '%',
                ];

                $recentRecords = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->orderByDesc('attendance_date')
                    ->limit(7)
                    ->get()
                    ->map(fn (AttendanceRecord $r) => [
                        'id' => $r->id,
                        'date' => $r->attendance_date->format('M j, Y'),
                        'day' => $r->attendance_date->format('D'),
                        'check_in' => AttendanceFormat::time($r->check_in_at) ?? '—',
                        'check_out' => AttendanceFormat::time($r->check_out_at) ?? '—',
                        'worked' => AttendanceFormat::worked($r->check_in_at, $r->check_out_at) ?? '—',
                        'status' => $r->status,
                    ]);
            }

            return Inertia::render('Dashboard', [
                'role' => 'employee',
                'userName' => $user->name,
                'employee' => $employee ? [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->fullName(),
                    'department' => $employee->position?->department?->name ?? 'Staff',
                    'position' => $employee->position?->title ?? 'Employee',
                    'status' => $employee->employment_status,
                ] : null,
                'today' => [
                    'date' => now()->setTimezone($zone)->format('l, F j, Y'),
                    'state' => $todayRecord === null ? 'not_in' : ($todayRecord->check_out_at === null ? 'in' : 'out'),
                    'late' => $todayRecord?->status === 'late',
                    'check_in' => AttendanceFormat::time($todayRecord?->check_in_at),
                    'check_out' => AttendanceFormat::time($todayRecord?->check_out_at),
                    'worked' => AttendanceFormat::worked($todayRecord?->check_in_at, $todayRecord?->check_out_at),
                ],
                'employeeStats' => $stats,
                'recentAttendance' => $recentRecords,
            ]);
        }

        // ─────────────────────────────────────────────────────────────
        // 2. ADMINISTRATOR / MANAGEMENT DASHBOARD
        // ─────────────────────────────────────────────────────────────
        $totalProducts = Product::where('is_active', true)->count();
        $totalSalesToday = Sale::whereDate('created_at', today())->count();
        $revenueToday = Sale::whereDate('created_at', today())
            ->where('status', Sale::STATUS_PAID)
            ->sum('total_amount');
        $lowStockCount = Product::lowStock()->count();
        $totalCustomers = Customer::count();
        $pendingSales = Sale::where('status', Sale::STATUS_PENDING)->count();

        $inventoryItems = Product::with(['category', 'unit'])
            ->where('is_active', true)
            ->withCount('stocks as warehouse_count')
            ->withSum('stocks as total_qty', 'quantity')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function ($product) {
                $warehouseStocks = InventoryStock::with('warehouse')
                    ->where('product_id', $product->id)
                    ->where('quantity', '>', 0)
                    ->get();

                $warehouseLabel = $warehouseStocks->count() > 1
                    ? 'multiple ' . $warehouseStocks->count()
                    : ($warehouseStocks->first()?->warehouse?->name ?? '—');

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? '—',
                    'unit' => $product->unit?->name ?? 'pcs',
                    'qty' => (float) ($product->total_qty ?? 0),
                    'warehouse' => $warehouseLabel,
                    'price' => '$' . number_format((float) $product->selling_price, 2),
                    'last_modified' => $product->updated_at?->format('g:i A M j, Y') ?? '—',
                    'status' => (float) ($product->total_qty ?? 0) <= (float) $product->reorder_level ? 'low' : 'in-stock',
                ];
            });

        $recentSales = Sale::with('customer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'number' => $s->sale_number,
                'customer' => $s->customer?->name ?? 'Walk-in',
                'amount' => '$' . number_format((float) $s->total_amount, 2),
                'status' => $s->status,
                'date' => $s->created_at->format('M j, Y'),
            ]);

        return Inertia::render('Dashboard', [
            'role' => 'admin',
            'userName' => $user->name,
            'stats' => [
                'total_products' => $totalProducts,
                'revenue_today' => '$' . number_format((float) $revenueToday, 2),
                'sales_today' => $totalSalesToday,
                'low_stock' => $lowStockCount,
                'customers' => $totalCustomers,
                'pending_sales' => $pendingSales,
            ],
            'inventoryItems' => $inventoryItems,
            'recentSales' => $recentSales,
        ]);
    }
}
