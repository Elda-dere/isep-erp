<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SaleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SaleController extends Controller
{
    // The status values the list can be filtered by (SRS section 5.3).
    private const STATUSES = [
        Sale::STATUS_PENDING,
        Sale::STATUS_PAID,
        Sale::STATUS_CANCELLED,
    ];

    // The service owns the rules. This controller only translates between the web and the service.
    public function __construct(private SaleService $sales) {}

    // SAL-09: the list, newest first, with filters.
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Dates are business days in Addis Ababa, but the database stores UTC.
        // So "2026-01-06" means 2026-01-06 00:00 to 23:59 Addis time, converted to UTC.
        $timezone = 'Africa/Addis_Ababa';
        $from = isset($filters['from']) ? Carbon::parse($filters['from'], $timezone)->startOfDay()->utc() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'], $timezone)->endOfDay()->utc() : null;

        $sales = Sale::query()
            ->with(['customer:id,name', 'creator:id,name']) // eager loading: no query per row
            ->withCount('items')
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn($query, $id) => $query->where('customer_id', $id))
            ->when($filters['created_by'] ?? null, fn($query, $id) => $query->where('created_by', $id))
            ->when($from, fn($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)           // never load every row (NFR-PERF-03)
            ->withQueryString()      // keep the filters inside the pagination links
            ->through(fn(Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'status' => $sale->status,
                'customer' => $sale->customer?->name,
                'created_by' => $sale->creator?->name,
                'lines_count' => $sale->items_count,
                'total_amount' => (string) $sale->total_amount,   // an exact string like "25.01"
                'currency' => $sale->currency,
                'created_at' => $sale->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $filters,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            // Only people who have made a sale are offered as "cashier".
            'cashiers' => User::whereIn('id', Sale::select('created_by'))->orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
        ]);
    }

    // The cashier's screen. It gets everything it needs in one go, so searching is instant.
    public function create(Request $request): Response
    {
        $customers = Customer::orderBy('name')->get(['id', 'name']);

        // SAL-01: anonymous sales use the seeded Walk-in customer, so it is chosen by default.
        $walkIn = $customers->first(fn(Customer $customer) => Customer::isWalkInName($customer->name));

        // The stock of every product in every warehouse (only where there is some), as
        // { productId: { warehouseId: "100.000" } }. The quantities stay exact strings.
        $stock = InventoryStock::where('quantity', '>', 0)
            ->get(['product_id', 'warehouse_id', 'quantity'])
            ->groupBy('product_id')
            ->map(fn($rows) => $rows->mapWithKeys(fn($row) => [$row->warehouse_id => (string) $row->quantity]))
            ->toArray();

        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'defaultCustomerId' => $walkIn?->id,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            // PRD-04: inactive products cannot be added to new sales, so they are not offered.
            'products' => Product::with('unit:id,symbol')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'unit_id', 'selling_price']),
            'stock' => $stock,
            // SAL-04: only people with this permission may change a unit price.
            'canOverridePrice' => $request->user()->hasPermission('sales.price_override'),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $canOverridePrice = $request->user()->hasPermission('sales.price_override');

        try {
            $sale = $this->sales->create((int) $data['customer_id'], $data['lines'], $request->user()->id, $canOverridePrice);
        } catch (DomainException | InvalidArgumentException $e) {
            // The service refused (not enough stock, an inactive product, a price change without the
            // permission ...): show its message above the lines, and keep what the cashier typed.
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', "Sale {$sale->sale_number} created. Its stock is held while the customer pays.");
    }

    // The detail page (payments will be added here with the Chapa slice).
    public function show(Sale $sale): Response
    {
        $sale->load(['customer', 'creator', 'items.product.unit', 'items.warehouse']);

        return Inertia::render('Sales/Show', [
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'status' => $sale->status,
                'currency' => $sale->currency,
                'total_amount' => (string) $sale->total_amount,        // an exact string like "25.01"
                'created_at' => $sale->created_at?->toIso8601String(),
                'created_by' => $sale->creator?->name,
                'customer' => [
                    'id' => $sale->customer->id,
                    'name' => $sale->customer->name,
                    'phone' => $sale->customer->phone,
                    'email' => $sale->customer->email,
                ],
                'lines' => $sale->items->map(fn($item) => [
                    'id' => $item->id,
                    'product' => [
                        'sku' => $item->product->sku,
                        'name' => $item->product->name,
                        'unit' => $item->product->unit?->symbol,
                    ],
                    'warehouse' => $item->warehouse->name,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->all(),
            ],
        ]);
    }

    // SAL-07 / SAL-11: the service cancels a pending sale and puts its stock back, and refuses a paid
    // or already cancelled sale. Its message is shown in the red box on the detail page.
    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        try {
            $this->sales->cancel($sale, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Sale cancelled. Its stock was put back.');
    }
}
