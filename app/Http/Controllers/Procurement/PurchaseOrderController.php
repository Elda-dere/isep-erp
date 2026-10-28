<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    // The status values the list can be filtered by (SRS section 5.3).
    private const STATUSES = [
        PurchaseOrder::STATUS_DRAFT,
        PurchaseOrder::STATUS_ORDERED,
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        PurchaseOrder::STATUS_RECEIVED,
        PurchaseOrder::STATUS_CANCELLED,
    ];

    // The service owns the rules. This controller only translates between the web and the service.
    public function __construct(private PurchaseOrderService $orders) {}

    // PRC-12: the list, newest first, with filters.
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Dates are business days in Addis Ababa, but the database stores UTC.
        // So "2026-01-06" means 2026-01-06 00:00 to 23:59 Addis time, converted to UTC.
        $timezone = 'Africa/Addis_Ababa';
        $from = isset($filters['from']) ? Carbon::parse($filters['from'], $timezone)->startOfDay()->utc() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'], $timezone)->endOfDay()->utc() : null;

        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'items']) // eager loading: two extra queries, not one per order
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn($query, $id) => $query->where('supplier_id', $id))
            ->when($from, fn($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)           // never load every row (NFR-PERF-03)
            ->withQueryString()      // keep the filters inside the pagination links
            ->through(fn(PurchaseOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'supplier' => $order->supplier?->name,
                'lines_count' => $order->items->count(),
                // PRC-04: the total is calculated from the lines each time, never stored.
                'total' => $order->total(),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
        ]);
    }

    // The detail page: the order, its lines, and for each line how much has been received.
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['supplier', 'creator', 'items.product.unit']);

        // PRC-12: quantity received per line. Only POSTED receipts count (a draft receipt has not
        // changed any stock). One query for the whole order, grouped by line.
        $received = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.purchase_order_id', $purchaseOrder->id)
            ->where('goods_receipts.status', 'posted')
            ->groupBy('goods_receipt_items.purchase_order_item_id')
            ->selectRaw('goods_receipt_items.purchase_order_item_id as item_id, SUM(goods_receipt_items.quantity_received) as received')
            ->pluck('received', 'item_id');

        $lines = $purchaseOrder->items->map(function ($item) use ($received) {
            // Exact decimals, never floats. A line with no receipts has received 0.
            $receivedQuantity = BigDecimal::of((string) ($received[$item->id] ?? '0'))->toScale(3);
            $outstanding = BigDecimal::of((string) $item->quantity)->minus($receivedQuantity)->toScale(3);

            return [
                'id' => $item->id,
                'product' => [
                    'sku' => $item->product->sku,
                    'name' => $item->product->name,
                    'unit' => $item->product->unit?->symbol,
                ],
                'quantity' => (string) $item->quantity,
                'unit_cost' => (string) $item->unit_cost,
                'line_total' => $item->lineTotal(),
                'received' => (string) $receivedQuantity,
                'outstanding' => (string) $outstanding,
            ];
        })->all();

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'status' => $purchaseOrder->status,
                'supplier' => [
                    'id' => $purchaseOrder->supplier->id,
                    'name' => $purchaseOrder->supplier->name,
                    'phone' => $purchaseOrder->supplier->phone,
                    'email' => $purchaseOrder->supplier->email,
                ],
                'created_by' => $purchaseOrder->creator?->name,
                'created_at' => $purchaseOrder->created_at?->toIso8601String(),
                'ordered_at' => $purchaseOrder->ordered_at?->toIso8601String(),
                'total' => $purchaseOrder->total(),
                'lines' => $lines,
            ],
        ]);
    }

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

        return redirect()->route('purchase_orders.show', $order)
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

        return redirect()->route('purchase_orders.show', $purchaseOrder)->with('success', 'Draft saved.');
    }

    // draft -> ordered. The service changes the status and writes the audit row.
    public function place(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orders->place($purchaseOrder, $request->user()->id);
        } catch (DomainException $e) {
            // The page shows this message in a red box (for example, "Only a draft order can be placed").
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('purchase_orders.show', $purchaseOrder)->with('success', 'Order placed.');
    }

    // PRC-05: the service refuses once goods have been received.
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orders->cancel($purchaseOrder, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('purchase_orders.show', $purchaseOrder)->with('success', 'Order cancelled.');
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
