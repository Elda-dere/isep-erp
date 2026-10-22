<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Requests\StockOpeningRequest;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\RedirectResponse;
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

        // TODO (AUD-02): audit-log stock adjustments once the audit writer is merged.
        try {
            $this->stock->adjust(
                (int) $data['product_id'],
                (int) $data['warehouse_id'],
                (string) $data['quantity'],
                $data['note'],
                $request->user()->id,
            );
        } catch (InsufficientStockException | InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Stock adjusted.');
    }
}
