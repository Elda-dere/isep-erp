<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
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

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')->with('success', 'Product created.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        // TODO (PRD-07): audit-log cost_price / selling_price changes once the audit writer is merged.
        $product->update($request->validated());

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return redirect()->route('products.index')->with('success', 'Product deactivated.');
    }

    public function activate(Product $product): RedirectResponse
    {
        $product->update(['is_active' => true]);

        return redirect()->route('products.index')->with('success', 'Product activated.');
    }
}
