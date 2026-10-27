<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    // The service owns the rules. This controller only translates between the web and the service.
    public function __construct(private PurchaseOrderService $orders) {}

    // Shows the empty form. "order" is null, which tells the page it is in "create" mode.
    public function create(): Response
    {
        return Inertia::render('Procurement/PurchaseOrders/Form', [
            'order' => null,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'products' => $this->products(),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $order = $this->orders->createDraft((int) $data['supplier_id'], $data['lines'], $request->user()->id);
        } catch (DomainException | InvalidArgumentException $e) {
            // The service refused (for example, an inactive product): show its message above the lines.
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        // Part 3 sends this to the order's detail page instead.
        return redirect()->route('purchase_orders.edit', $order)
            ->with('success', "Draft {$order->order_number} created.");
    }

    // Shows the same form, filled with the draft's current values.
    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        // PRC-03: lines can be edited only while the order is a draft.
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT, 403, 'Only a draft order can be edited.');

        $purchaseOrder->load('items');

        return Inertia::render('Procurement/PurchaseOrders/Form', [
            'order' => [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'supplier_id' => (int) $purchaseOrder->supplier_id,
                // Quantities and costs stay exact strings like "10.000" and "2.50".
                'lines' => $purchaseOrder->items->map(fn($item) => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (string) $item->quantity,
                    'unit_cost' => (string) $item->unit_cost,
                ])->all(),
            ],
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            // Products already on this draft stay in the list, even if they became inactive since.
            'products' => $this->products($purchaseOrder->items->pluck('product_id')->all()),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->orders->updateDraft($purchaseOrder, (int) $data['supplier_id'], $data['lines'], $request->user()->id);
        } catch (DomainException | InvalidArgumentException $e) {
            // For example, "Lines can be edited only while the order is a draft."
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchase_orders.edit', $purchaseOrder)->with('success', 'Draft saved.');
    }

    // Active products for the dropdown (PRD-04), plus any product already on this order.
    private function products(array $alsoIds = [])
    {
        return Product::with('unit:id,symbol')
            ->where(fn($query) => $query->where('is_active', true)->orWhereIn('id', $alsoIds))
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'unit_id', 'cost_price', 'is_active']);
    }
}
