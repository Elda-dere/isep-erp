<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class GoodsReceiptController extends Controller
{
    // The status values the list can be filtered by (SRS section 5.3).
    private const STATUSES = [
        GoodsReceipt::STATUS_DRAFT,
        GoodsReceipt::STATUS_POSTED,
        GoodsReceipt::STATUS_CANCELLED,
    ];

    // The services own the rules. This controller only translates between the web and the services.
    public function __construct(
        private GoodsReceiptService $receipts,
        private PurchaseOrderService $orders,
    ) {
    }

    // The list, newest first, with filters.
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ]);

        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier:id,name', 'receiver:id,name']) // eager loading: no query per row
            ->withCount('items')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['purchase_order_id'] ?? null, fn ($query, $id) => $query->where('purchase_order_id', $id))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)           // never load every row (NFR-PERF-03)
            ->withQueryString()      // keep the filters inside the pagination links
            ->through(fn (GoodsReceipt $receipt) => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'status' => $receipt->status,
                'order_id' => $receipt->purchase_order_id,
                'order_number' => $receipt->purchaseOrder?->order_number,
                'supplier' => $receipt->purchaseOrder?->supplier?->name,
                'lines_count' => $receipt->items_count,
                'received_by' => $receipt->receiver?->name,
                'received_at' => $receipt->received_at?->toIso8601String(),
            ]);

        return Inertia::render('Procurement/GoodsReceipts/Index', [
            'receipts' => $receipts,
            'filters' => $filters,
            'statuses' => self::STATUSES,
        ]);
    }

    // The detail page: the receipt, its lines, and (once posted) the warehouse each line went into.
    public function show(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load(['purchaseOrder.supplier', 'receiver', 'items.product.unit']);

        // PRC-08: the warehouse is not stored on the receipt, only on the stock movements it created.
        $movements = StockMovement::query()
            ->where('movement_type', StockMovement::TYPE_RECEIPT)
            ->whereIn('goods_receipt_item_id', $goodsReceipt->items->pluck('id'))
            ->with('warehouse:id,name')
            ->get()
            ->keyBy('goods_receipt_item_id');

        return Inertia::render('Procurement/GoodsReceipts/Show', [
            'receipt' => [
                'id' => $goodsReceipt->id,
                'receipt_number' => $goodsReceipt->receipt_number,
                'status' => $goodsReceipt->status,
                'received_at' => $goodsReceipt->received_at?->toIso8601String(),
                'received_by' => $goodsReceipt->receiver?->name,
                'order' => [
                    'id' => $goodsReceipt->purchaseOrder->id,
                    'order_number' => $goodsReceipt->purchaseOrder->order_number,
                    'status' => $goodsReceipt->purchaseOrder->status,
                    'supplier' => $goodsReceipt->purchaseOrder->supplier?->name,
                ],
                'lines' => $goodsReceipt->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product' => [
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                        'unit' => $item->product->unit?->symbol,
                    ],
                    'quantity' => (string) $item->quantity_received, // an exact string like "4.000"
                    // null until the receipt is posted
                    'warehouse' => $movements->get($item->id)?->warehouse?->name,
                ])->all(),
            ],
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // The empty form for a placed order.
    public function create(PurchaseOrder $purchaseOrder): Response
    {
        // Goods can be received only against a placed order that is not finished.
        abort_unless(
            in_array($purchaseOrder->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true),
            403,
            'Goods can be received only against a placed order.'
        );

        return $this->form($purchaseOrder);
    }

    public function store(GoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $lines = $request->receivedLines();

        if ($lines === []) {
            return back()->withErrors(['lines' => 'Enter a quantity for at least one line.'])->withInput();
        }

        try {
            $receipt = $this->receipts->createDraft($purchaseOrder, $lines, $request->user()->id);
        } catch (DomainException|InvalidArgumentException $e) {
            // The service refused (for example, over-receipt): show its message above the lines.
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('goods_receipts.show', $receipt)
            ->with('success', "Draft {$receipt->receipt_number} created.");
    }

    // The same form, filled with the draft's quantities.
    public function edit(GoodsReceipt $goodsReceipt): Response
    {
        // A posted receipt cannot be edited (PRC-09): mistakes are fixed with a stock adjustment.
        abort_unless($goodsReceipt->status === GoodsReceipt::STATUS_DRAFT, 403, 'Only a draft receipt can be edited.');

        return $this->form($goodsReceipt->purchaseOrder, $goodsReceipt);
    }

    public function update(GoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $lines = $request->receivedLines();

        if ($lines === []) {
            return back()->withErrors(['lines' => 'Enter a quantity for at least one line.'])->withInput();
        }

        try {
            $this->receipts->updateDraft($goodsReceipt, $lines, $request->user()->id);
        } catch (DomainException|InvalidArgumentException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('goods_receipts.show', $goodsReceipt)->with('success', 'Draft saved.');
    }

    // Posting: the stock goes up, the receipt becomes posted, and the order status follows. All in the service.
    public function post(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        try {
            $this->receipts->post($goodsReceipt, (int) $data['warehouse_id'], $request->user()->id);
        } catch (DomainException|InvalidArgumentException $e) {
            // The page shows this message in a red box (for example, "only 4.000 is still outstanding").
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('goods_receipts.show', $goodsReceipt)
            ->with('success', 'Receipt posted. The stock has been updated.');
    }

    public function cancel(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        try {
            $this->receipts->cancel($goodsReceipt, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('goods_receipts.show', $goodsReceipt)->with('success', 'Receipt cancelled.');
    }

    // The form for both create and edit: one entry per order line, with what was ordered, what has
    // been received (posted receipts only) and what is still outstanding.
    private function form(PurchaseOrder $order, ?GoodsReceipt $receipt = null): Response
    {
        $order->load(['supplier:id,name', 'items.product.unit']);
        $received = $this->orders->receivedQuantities($order);

        // When editing, the boxes start with the draft's own quantities, keyed by order line.
        $draft = $receipt ? $receipt->items()->get()->pluck('quantity_received', 'purchase_order_item_id') : collect();

        $lines = $order->items->map(function ($item) use ($received, $draft, $receipt) {
            // Exact decimals, never floats.
            $ordered = BigDecimal::of((string) $item->quantity)->toScale(3);
            $already = $received[$item->id] ?? BigDecimal::zero()->toScale(3);
            $outstanding = $ordered->minus($already);

            return [
                'purchase_order_item_id' => $item->id,
                'product' => [
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'unit' => $item->product->unit?->symbol,
                ],
                'ordered' => (string) $ordered,
                'received' => (string) $already,
                'outstanding' => (string) $outstanding,
                // Creating: everything still outstanding. Editing: what the draft says (empty if the line is not on it).
                'quantity' => $receipt
                    ? (string) $draft->get($item->id, '')
                    : ($outstanding->isPositive() ? (string) $outstanding : ''),
            ];
        })->all();

        return Inertia::render('Procurement/GoodsReceipts/Form', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'supplier' => $order->supplier?->name,
            ],
            'receipt' => $receipt ? ['id' => $receipt->id, 'receipt_number' => $receipt->receipt_number] : null,
            'lines' => $lines,
        ]);
    }
}