<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StockOpeningRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

    // Shows the page with both forms. It only needs the lists for the two dropdowns.
    // "selected" lets other pages link here with a product already chosen: /stock/adjust?product_id=3
    public function create(Request $request): Response
    {
        return Inertia::render('Inventory/Stock/Adjust', [
            // Inactive products are included on purpose: you may need to correct a discontinued item.
            'products' => Product::with('unit:id,symbol')
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'unit_id']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'selected' => [
                'product_id' => $request->integer('product_id') ?: null,
                'warehouse_id' => $request->integer('warehouse_id') ?: null,
            ],
        ]);
    }

    public function storeOpening(StockOpeningRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->stock->setOpening(
                (int) $data['product_id'],
                (int) $data['warehouse_id'],
                (string) $data['quantity'],
                $request->user()->id,
                $data['note'] ?? null,
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Opening stock recorded.');
    }

    public function storeAdjustment(StockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            // The stock change and its audit row share one transaction (AUD-02).
            // If StockService refuses (for example, not enough stock), it throws, the
            // transaction rolls back, and no audit row is written.
            DB::transaction(function () use ($data, $request) {
                $movement = $this->stock->adjust(
                    (int) $data['product_id'],
                    (int) $data['warehouse_id'],
                    (string) $data['quantity'],
                    $data['note'],
                    $request->user()->id,
                );

                // The mandatory note goes into the new values, as Eldana asked.
                // Quantity is signed: negative removed stock, positive added it.
                AuditLogger::log('stock.adjusted', 'stock_movement', $movement->id, null, [
                    'product_id' => $movement->product_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'quantity' => (string) $movement->quantity,
                    'note' => $data['note'],
                ]);
            });
        } catch (InsufficientStockException | InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Stock adjusted.');
    }
}
