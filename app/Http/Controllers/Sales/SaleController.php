<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\SaleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SaleController extends Controller
{
    // The service owns the rules. This controller only translates between the web and the service.
    public function __construct(private SaleService $sales) {}

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

    // The detail page: for now read-only (the list, Cancel and payments come next).
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
}
