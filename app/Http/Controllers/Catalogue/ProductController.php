<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    // The fields we record when a product is created, and send to the edit form.
    private const AUDITED_FIELDS = [
        'category_id',
        'unit_id',
        'sku',
        'name',
        'description',
        'cost_price',
        'selling_price',
        'reorder_level',
        'is_active',
    ];

    // PRD-07: changes to these get their own audit entry, with old and new values.
    private const PRICE_FIELDS = ['cost_price', 'selling_price'];

    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category:id,name', 'unit:id,name,symbol'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = trim($request->string('search'));
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('category_id'), fn($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('active'), fn($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Catalogue/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'category_id', 'active']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    // Shows the empty form. The dropdowns need the categories and units.
    public function create(): Response
    {
        return Inertia::render('Catalogue/Products/Form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }

    // Shows the same form, filled with the product's current values.
    // only() applies the model casts, so prices arrive as exact strings like "10.00".
    public function edit(Product $product): Response
    {
        return Inertia::render('Catalogue/Products/Form', [
            'product' => $product->only(['id', ...self::AUDITED_FIELDS]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        // One transaction: if the audit row cannot be written, the product is not created either.
        DB::transaction(function () use ($request) {
            $product = Product::create($request->validated());

            AuditLogger::log('product.created', 'product', $product->id, null, $product->only(self::AUDITED_FIELDS));
        });

        return redirect()->route('products.index')->with('success', 'Product created.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            // fill() puts the new values on the model in memory WITHOUT saving yet,
            // so getDirty() can tell us exactly which fields really changed.
            $product->fill($request->validated());
            $changed = array_keys($product->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            // Collect old and new values for the changed fields only.
            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $product->getOriginal($field);
                $new[$field] = $product->{$field};
            }

            $product->save();

            AuditLogger::log('product.updated', 'product', $product->id, $old, $new);

            // PRD-07: a price change is also logged on its own, with only the price fields.
            $priceFields = array_values(array_intersect(self::PRICE_FIELDS, $changed));
            if ($priceFields !== []) {
                AuditLogger::log(
                    'product.price_changed',
                    'product',
                    $product->id,
                    Arr::only($old, $priceFields),
                    Arr::only($new, $priceFields),
                );
            }
        });

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product) {
            // Only act (and only log) when the state really changes.
            if ($product->is_active) {
                $product->update(['is_active' => false]);

                AuditLogger::log('product.deactivated', 'product', $product->id, ['is_active' => true], ['is_active' => false]);
            }
        });

        return redirect()->route('products.index')->with('success', 'Product deactivated.');
    }

    public function activate(Product $product): RedirectResponse
    {
        DB::transaction(function () use ($product) {
            if (! $product->is_active) {
                $product->update(['is_active' => true]);

                AuditLogger::log('product.activated', 'product', $product->id, ['is_active' => false], ['is_active' => true]);
            }
        });

        return redirect()->route('products.index')->with('success', 'Product activated.');
    }
}
