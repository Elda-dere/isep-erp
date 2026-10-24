<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
});

const page = usePage();

// Buttons are only a convenience: the server checks products.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('products.manage'));

// The filter boxes start with the values from the URL, so a reload keeps them.
const search = ref(props.filters?.search ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const active = ref(props.filters?.active ?? '');

// Ask the server for the list again with the chosen filters.
// The searching itself happens in ProductController::index (SQL), not here.
function applyFilters() {
    router.get(
        '/products',
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            active: active.value === '' ? undefined : active.value,
        },
        { preserveState: true, replace: true },
    );
}

// Wait 300 ms after the last keystroke so we do not ask on every letter.
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});
watch([categoryId, active], applyFilters);

// Display only: the real amounts stay exact strings on the server.
function money(value) {
    return `ETB ${Number(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

// PRD-06: warn when the selling price is lower than the cost price.
function belowCost(product) {
    return Number(product.selling_price) < Number(product.cost_price);
}

// One button flips between deactivate and activate (products are never deleted).
function toggleActive(product) {
    const action = product.is_active ? 'deactivate' : 'activate';
    router.patch(`/products/${product.id}/${action}`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Products" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Products</h1>
                <p class="mt-1 text-sm text-gray-500">{{ products.total }} product(s)</p>
            </div>
            <Link
                v-if="canManage"
                href="/products/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New product
            </Link>
        </div>

        <FlashMessage class="mt-4" />

        <!-- Filters: labels are hidden visually but still read by screen readers -->
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div>
                <label for="search" class="sr-only">Search name or SKU</label>
                <input
                    id="search"
                    v-model="search"
                    type="search"
                    placeholder="Search name or SKU"
                    class="h-11 w-full rounded border border-gray-300 px-3"
                />
            </div>
            <div>
                <label for="category" class="sr-only">Category</label>
                <select id="category" v-model="categoryId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All categories</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </div>
            <div>
                <label for="status" class="sr-only">Status</label>
                <select id="status" v-model="active" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">Active and inactive</option>
                    <option value="1">Active only</option>
                    <option value="0">Inactive only</option>
                </select>
            </div>
        </div>

        <p v-if="products.data.length === 0" class="mt-8 text-center text-gray-500">No products found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">SKU</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th class="text-right">Cost</th>
                    <th class="text-right">Price</th>
                    <th class="pl-4">Status</th>
                    <th v-if="canManage"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in products.data" :key="product.id" class="border-b">
                    <td class="py-3 font-mono">{{ product.sku }}</td>
                    <td>{{ product.name }}</td>
                    <td>{{ product.category?.name }}</td>
                    <td class="text-right">{{ money(product.cost_price) }}</td>
                    <td class="text-right">
                        {{ money(product.selling_price) }}
                        <span v-if="belowCost(product)" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">
                            Below cost
                        </span>
                    </td>
                    <td class="pl-4">
                        <span
                            class="rounded px-2 py-0.5 text-xs"
                            :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'"
                        >
                            {{ product.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td v-if="canManage" class="text-right">
                        <div class="flex justify-end gap-1">
                            <Link
                                :href="`/products/${product.id}/edit`"
                                :aria-label="`Edit ${product.name}`"
                                class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                :aria-label="`${product.is_active ? 'Deactivate' : 'Activate'} ${product.name}`"
                                class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                                @click="toggleActive(product)"
                            >
                                {{ product.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
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
                    <span
                        class="rounded px-2 py-0.5 text-xs"
                        :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'"
                    >
                        {{ product.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ product.category?.name }}</p>
                <p class="mt-1 text-sm">
                    {{ money(product.selling_price) }}
                    <span v-if="belowCost(product)" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">
                        Below cost
                    </span>
                </p>
                <div v-if="canManage" class="mt-2 flex gap-2">
                    <Link
                        :href="`/products/${product.id}/edit`"
                        :aria-label="`Edit ${product.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border"
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        :aria-label="`${product.is_active ? 'Deactivate' : 'Activate'} ${product.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border"
                        @click="toggleActive(product)"
                    >
                        {{ product.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Pagination: 25 products per page (NFR-PERF-03) -->
        <div class="mt-6 flex items-center justify-between text-sm">
            <Link v-if="products.prev_page_url" :href="products.prev_page_url" class="flex min-h-11 items-center rounded border px-4">
                Previous
            </Link>
            <span v-else></span>

            <span class="text-gray-500">Showing {{ products.from ?? 0 }}–{{ products.to ?? 0 }} of {{ products.total }}</span>

            <Link v-if="products.next_page_url" :href="products.next_page_url" class="flex min-h-11 items-center rounded border px-4">
                Next
            </Link>
            <span v-else></span>
        </div>
    </div>
</template>