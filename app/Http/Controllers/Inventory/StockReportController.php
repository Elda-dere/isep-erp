<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only stock screens. Nothing here changes stock:
 * only StockService may do that.
 */
class StockReportController extends Controller
{
    // INV-08: quantity per product and warehouse, total per product.
    public function overview(Request $request): Response
    {
        // Validate the filters from the URL (?search=...&category_id=...&warehouse_id=...).
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouseId = $filters['warehouse_id'] ?? null;

        // Applies the warehouse filter to the stock rows, if one was chosen.
        $onlyThisWarehouse = fn($query) => $query->when(
            $warehouseId,
            fn($q) => $q->where('warehouse_id', $warehouseId)
        );

        $products = Product::query()
            ->with(['category:id,name', 'unit:id,name,symbol'])
            // The per-warehouse lines shown under each product.
            ->with(['stocks' => fn($query) => $onlyThisWarehouse($query)->with('warehouse:id,name')])
            // total_quantity = sum of the same stock rows (one SQL subquery, no loop in PHP).
            ->withSum(['stocks as total_quantity' => $onlyThisWarehouse], 'quantity')
            // With a warehouse chosen, list only products that have a stock row there.
            ->when($warehouseId, fn($q) => $q->whereHas('stocks', fn($s) => $s->where('warehouse_id', $warehouseId)))
            ->when($filters['category_id'] ?? null, fn($q, $id) => $q->where('category_id', $id))
            ->when($filters['search'] ?? null, function ($q, $term) {
                // Parentheses matter: (name LIKE ... OR sku LIKE ...) AND the other filters.
                $q->where(fn($inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate(25)          // never load every row (NFR-PERF-03)
            ->withQueryString()     // keep the filters in the pagination links
            ->through(function ($product) {
                // A product with no stock rows has no sum: show 0.000, not null.
                $product->total_quantity = $product->total_quantity ?? '0.000';

                return $product;
            });

        return Inertia::render('Inventory/Stock/Overview', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // INV-09: products whose total quantity is at or below the reorder level.
    public function lowStock(): Response
    {
        $products = Product::query()
            ->lowStock() // the scope we added to the Product model
            ->with(['category:id,name', 'unit:id,name,symbol'])
            ->withSum('stocks as total_quantity', 'quantity')
            ->orderBy('name')
            ->paginate(25)
            ->through(function ($product) {
                $product->total_quantity = $product->total_quantity ?? '0.000';

                return $product;
            });

        return Inertia::render('Inventory/Stock/LowStock', [
            'products' => $products,
        ]);
    }

    // INV-10: the ledger of one product, newest first, with a running balance.
    public function card(Request $request, Product $product): Response
    {
        $filters = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'type' => ['nullable', Rule::in(StockMovement::TYPES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Dates are business days in Addis Ababa, but the database stores UTC.
        // So "2026-01-06" means 2026-01-06 00:00 to 23:59 Addis time, converted to UTC.
        $timezone = 'Africa/Addis_Ababa';
        $from = isset($filters['from'])
            ? \Carbon\Carbon::parse($filters['from'], $timezone)->startOfDay()->utc()
            : null;
        $to = isset($filters['to'])
            ? \Carbon\Carbon::parse($filters['to'], $timezone)->endOfDay()->utc()
            : null;

        // Inner query: ALL movements of this product, each row with the running total up to it.
        // SUM(...) OVER (ORDER BY created_at, id) is a MySQL 8 "window function":
        // it adds up the quantities row by row, oldest first. "id" breaks ties.
        // With a warehouse chosen, the balance is for that warehouse only.
        $withBalance = StockMovement::query()
            ->select('stock_movements.*')
            ->selectRaw('SUM(quantity) OVER (ORDER BY created_at, id) AS running_balance')
            ->where('product_id', $product->id)
            ->when($filters['warehouse_id'] ?? null, fn($q, $id) => $q->where('warehouse_id', $id));

        // Outer query: filter AFTER the balance is calculated, so filtering by type or date
        // never changes the balance shown on a row. It is always the true balance.
        $movements = StockMovement::query()
            ->fromSub($withBalance, 'stock_movements')
            ->with(['warehouse:id,name', 'performedBy:id,name'])
            ->when($filters['type'] ?? null, fn($q, $type) => $q->where('movement_type', $type))
            ->when($from, fn($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn($q) => $q->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Inventory/Stock/Card', [
            'product' => $product->load(['category:id,name', 'unit:id,name,symbol']),
            'movements' => $movements,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'types' => StockMovement::TYPES,
        ]);
    }
}
