<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PagerLinks from '../../../Components/PagerLinks.vue';

// Products whose total quantity (all warehouses) is at or below the reorder level (INV-09).
// The rule lives in Product::lowStock() on the server: this page only shows the result.
defineProps({
    products: Object,
});
</script>

<template>
    <Head title="Low stock" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Low stock</h1>
                <p class="mt-1 text-sm text-gray-500">{{ products.total }} product(s) need attention</p>
            </div>
            <Link href="/stock" class="flex min-h-11 items-center rounded border px-4">Stock overview</Link>
        </div>

        <p v-if="products.data.length === 0" class="mt-8 text-center text-gray-500">
            Nothing is below its reorder level.
        </p>

        <!-- Table: from 640 px upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-right">In stock</th>
                    <th class="text-right">Reorder level</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="product in products.data" :key="product.id" class="border-b">
                    <td class="py-3 font-mono">{{ product.sku }}</td>
                    <td>{{ product.name }}</td>
                    <td>{{ product.category?.name }}</td>
                    <td class="text-right font-medium text-red-800">{{ product.total_quantity }} {{ product.unit?.symbol }}</td>
                    <td class="text-right">{{ product.reorder_level }}</td>
                    <td class="text-right">
                        <Link :href="`/stock/card/${product.id}`" :aria-label="`Stock card for ${product.name}`" class="flex min-h-11 items-center justify-end rounded px-3 hover:bg-gray-100">
                            Stock card
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px -->
        <div v-if="products.data.length" class="mt-4 space-y-3 sm:hidden">
            <div v-for="product in products.data" :key="product.id" class="rounded border bg-white p-3">
                <p class="font-medium">{{ product.name }}</p>
                <p class="font-mono text-xs text-gray-500">{{ product.sku }}</p>
                <p class="mt-2 text-sm">
                    <span class="font-medium text-red-800">{{ product.total_quantity }} {{ product.unit?.symbol }}</span>
                    in stock, reorder at {{ product.reorder_level }}
                </p>
                <Link :href="`/stock/card/${product.id}`" class="mt-2 flex min-h-11 items-center justify-center rounded border">
                    Stock card
                </Link>
            </div>
        </div>

        <PagerLinks :meta="products" />
    </div>
</template>