<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StockOpeningRequest;
use App\Services\AuditLogger;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockController extends Controller
{
    public function __construct(private StockService $stock) {}

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
