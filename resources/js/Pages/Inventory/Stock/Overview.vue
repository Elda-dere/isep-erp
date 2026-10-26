<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../../Components/PagerLinks.vue';

const props = defineProps({
    products: Object, // each product has total_quantity and a "stocks" list (one line per warehouse)
    filters: Object,
    categories: Array,
    warehouses: Array,
});
const page = usePage();

// The button is only a convenience: the server checks inventory.adjust on every request anyway.
const canAdjust = computed(() => page.props.auth?.permissions?.includes('inventory.adjust'));


// The filter boxes start with the values from the URL, so a reload keeps them.
const search = ref(props.filters?.search ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const warehouseId = ref(props.filters?.warehouse_id ?? '');

// Ask the server for the list again with the chosen filters.
// The filtering itself happens in StockReportController::overview (SQL), not here.
function applyFilters() {
    router.get(
        '/stock',
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            warehouse_id: warehouseId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

// Wait 300 ms after the last keystroke, so we do not ask on every letter.
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});
watch([categoryId, warehouseId], applyFilters);

// "Low" badge: total at or below the reorder level (INV-09).
// With a warehouse chosen, the total covers that warehouse only, so the comparison would
// be misleading: we only show the badge when all warehouses are included.
function isLow(product) {
    return !warehouseId.value && Number(product.total_quantity) <= Number(product.reorder_level);
}
</script>

<template>
    <Head title="Stock overview" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Stock overview</h1>
                <p class="mt-1 text-sm text-gray-500">{{ products.total }} product(s)</p>
            </div>
                        <div class="flex gap-2">
                <Link href="/stock/low" class="flex min-h-11 items-center rounded border px-4">Low stock</Link>
                <Link
                    v-if="canAdjust"
                    href="/stock/adjust"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
                >
                    Adjust stock
                </Link>
            </div>
        </div>

        <!-- Filters: labels are hidden visually but still read by screen readers -->
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div>
                <label for="search" class="sr-only">Search name or SKU</label>
                <input id="search" v-model="search" type="search" placeholder="Search name or SKU" class="h-11 w-full rounded border border-gray-300 px-3" />
            </div>
            <div>
                <label for="category" class="sr-only">Category</label>
                <select id="category" v-model="categoryId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
            </div>
            <div>
                <label for="warehouse" class="sr-only">Warehouse</label>
                <select id="warehouse" v-model="warehouseId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All warehouses</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                </select>
            </div>
        </div>

        <p v-if="products.data.length === 0" class="mt-8 text-center text-gray-500">No products found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-right">Total</th>
                    <th class="pl-6">By warehouse</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in products.data" :key="product.id" class="border-b align-top">
                    <td class="py-3 font-mono">{{ product.sku }}</td>
                    <td>{{ product.name }}</td>
                    <td>{{ product.category?.name }}</td>
                    <td class="text-right font-medium">
                        {{ product.total_quantity }} {{ product.unit?.symbol }}
                        <span v-if="isLow(product)" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Low</span>
                    </td>
                    <td class="pl-6">
                        <p v-if="product.stocks.length === 0" class="text-gray-400">No stock</p>
                        <p v-for="stock in product.stocks" :key="stock.id" class="text-gray-600">
                            {{ stock.warehouse?.name }}: {{ stock.quantity }}
                        </p>
                    </td>
                    <td class="text-right">
                        <Link
                            :href="`/stock/card/${product.id}`"
                            :aria-label="`Stock card for ${product.name}`"
                            class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                        >
                            Stock card
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="products.data.length" class="mt-4 space-y-3 sm:hidden">
            <div v-for="product in products.data" :key="product.id" class="rounded border bg-white p-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ product.name }}</p>
                        <p class="font-mono text-xs text-gray-500">{{ product.sku }}</p>
                    </div>
                    <p class="text-right font-medium">
                        {{ product.total_quantity }} {{ product.unit?.symbol }}
                        <span v-if="isLow(product)" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Low</span>
                    </p>
                </div>
                <p v-for="stock in product.stocks" :key="stock.id" class="mt-1 text-sm text-gray-600">
                    {{ stock.warehouse?.name }}: {{ stock.quantity }}
                </p>
                <Link :href="`/stock/card/${product.id}`" class="mt-2 flex min-h-11 items-center justify-center rounded border">
                    Stock card
                </Link>
            </div>
        </div>

        <PagerLinks :meta="products" />
    </div>
</template>