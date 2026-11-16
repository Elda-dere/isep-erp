<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // KPI stats
        $totalProducts  = Product::where('is_active', true)->count();
        $totalSalesToday = Sale::whereDate('created_at', today())->count();
        $revenueToday   = Sale::whereDate('created_at', today())
            ->where('status', Sale::STATUS_PAID)
            ->sum('total_amount');
        $lowStockCount  = Product::lowStock()->count();
        $totalCustomers = Customer::count();
        $pendingSales   = Sale::where('status', Sale::STATUS_PENDING)->count();

        // Inventory table: products with their total stock across all warehouses
        $inventoryItems = Product::with(['category', 'unit'])
            ->where('is_active', true)
            ->withCount('stocks as warehouse_count')
            ->withSum('stocks as total_qty', 'quantity')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(function ($product) {
                // Get warehouse breakdown
                $warehouseStocks = InventoryStock::with('warehouse')
                    ->where('product_id', $product->id)
                    ->where('quantity', '>', 0)
                    ->get();

                $warehouseLabel = $warehouseStocks->count() > 1
                    ? 'multiple ' . $warehouseStocks->count()
                    : ($warehouseStocks->first()?->warehouse?->name ?? '—');

                return [
                    'id'             => $product->id,
                    'sku'            => $product->sku,
                    'name'           => $product->name,
                    'category'       => $product->category?->name ?? '—',
                    'unit'           => $product->unit?->name ?? 'pcs',
                    'qty'            => (float) ($product->total_qty ?? 0),
                    'warehouse'      => $warehouseLabel,
                    'price'          => '$' . number_format((float) $product->selling_price, 2),
                    'last_modified'  => $product->updated_at?->format('g:i A M j, Y') ?? '—',
                    'status'         => (float) ($product->total_qty ?? 0) <= (float) $product->reorder_level ? 'low' : 'in-stock',
                ];
            });

        // Recent sales for the activity panel
        $recentSales = Sale::with('customer')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id'       => $s->id,
                'number'   => $s->sale_number,
                'customer' => $s->customer?->name ?? 'Walk-in',
                'amount'   => '$' . number_format((float) $s->total_amount, 2),
                'status'   => $s->status,
                'date'     => $s->created_at->format('M j, Y'),
            ]);

        return Inertia::render('Dashboard', [
            'userName'      => $request->user()->name,
            'stats'         => [
                'total_products'  => $totalProducts,
                'revenue_today'   => '$' . number_format((float) $revenueToday, 2),
                'sales_today'     => $totalSalesToday,
                'low_stock'       => $lowStockCount,
                'customers'       => $totalCustomers,
                'pending_sales'   => $pendingSales,
            ],
            'inventoryItems' => $inventoryItems,
            'recentSales'    => $recentSales,
        ]);
    }
}
