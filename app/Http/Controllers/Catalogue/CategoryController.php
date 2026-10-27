<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    // The fields we record in the audit log and send to the edit form.
    private const AUDITED_FIELDS = ['name', 'description'];

    public function index(): Response
    {
        return Inertia::render('Catalogue/Categories/Index', [
            // withCount adds a "products_count" to every row with one extra SQL subquery (no loop in PHP).
            'categories' => Category::withCount('products')->orderBy('name')->paginate(25),
        ]);
    }

    // Shows the empty form. "category" is null, which tells the page it is in "create" mode.
    public function create(): Response
    {
        return Inertia::render('Catalogue/Categories/Form', [
            'category' => null,
        ]);
    }

    // Shows the same form, filled with the category's current values.
    public function edit(Category $category): Response
    {
        return Inertia::render('Catalogue/Categories/Form', [
            'category' => $category->only(['id', ...self::AUDITED_FIELDS]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        // Create and audit in one transaction, so neither exists without the other.
        DB::transaction(function () use ($request) {
            $category = Category::create($request->validated());

            AuditLogger::log('category.created', 'category', $category->id, null, $category->only(self::AUDITED_FIELDS));
        });

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        DB::transaction(function () use ($request, $category) {
            // fill() sets the new values in memory, and getDirty() tells us what really changed.
            $category->fill($request->validated());
            $changed = array_keys($category->getDirty());

            if ($changed === []) {
                return; // nothing changed: no save and no audit row
            }

            $old = [];
            $new = [];
            foreach ($changed as $field) {
                $old[$field] = $category->getOriginal($field);
                $new[$field] = $category->{$field};
            }

            $category->save();

            AuditLogger::log('category.updated', 'category', $category->id, $old, $new);
        });

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // PRD-01: a category in use cannot be deleted. We check first to give a friendly message.
        if ($category->products()->exists()) {
            return back()->withErrors(['delete' => 'This category is used by products, so it cannot be deleted.']);
        }

        try {
            DB::transaction(function () use ($category) {
                $id = $category->id;
                $old = $category->only(self::AUDITED_FIELDS);

                $category->delete();

                AuditLogger::log('category.deleted', 'category', $id, $old, null);
            });
        } catch (QueryException) {
            // Safety net: if a product was added a moment ago, MySQL still refuses (restrictOnDelete).
            // The transaction rolled back, so nothing was deleted and no audit row was written.
            return back()->withErrors(['delete' => 'This category is used by products, so it cannot be deleted.']);
        }

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
