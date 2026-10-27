<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

defineProps({
    suppliers: Object, // a Laravel paginator: .data holds the rows, each with purchase_orders_count
});

const page = usePage();

// The server sends this message when a delete is refused (a supplier with purchase orders).
const deleteError = computed(() => page.props.errors?.delete ?? null);

function remove(supplier) {
    // Destructive actions ask for confirmation (SRS section 6.1).
    if (!confirm(`Delete the supplier "${supplier.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/suppliers/${supplier.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Suppliers" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Suppliers</h1>
                <p class="mt-1 text-sm text-gray-500">{{ suppliers.total }} supplier(s)</p>
            </div>
            <!-- No permission check on the buttons: this whole screen needs suppliers.manage already. -->
            <Link href="/suppliers/create" class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white">
                New supplier
            </Link>
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="deleteError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ deleteError }}
        </p>

        <p v-if="suppliers.data.length === 0" class="mt-8 text-center text-gray-500">No suppliers yet.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-right">Orders</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="supplier in suppliers.data" :key="supplier.id" class="border-b align-top">
                    <td class="py-3">
                        <p class="font-medium">{{ supplier.name }}</p>
                        <p v-if="supplier.address" class="text-xs text-gray-500">{{ supplier.address }}</p>
                    </td>
                    <td>{{ supplier.phone }}</td>
                    <td>{{ supplier.email }}</td>
                    <td class="text-right">{{ supplier.purchase_orders_count }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <Link
                                :href="`/suppliers/${supplier.id}/edit`"
                                :aria-label="`Edit ${supplier.name}`"
                                class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                            >
                                Edit
                            </Link>
                            <!-- Only suppliers without purchase orders can be deleted. The server checks again. -->
                            <button
                                v-if="supplier.purchase_orders_count === 0"
                                type="button"
                                :aria-label="`Delete ${supplier.name}`"
                                class="flex min-h-11 items-center rounded px-3 text-red-700 hover:bg-red-50"
                                @click="remove(supplier)"
                            >
                                Delete
                            </button>
                            <span v-else class="flex min-h-11 items-center px-2 text-gray-400">In use</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="suppliers.data.length" class="mt-4 space-y-3 sm:hidden">
            <div v-for="supplier in suppliers.data" :key="supplier.id" class="rounded border bg-white p-3">
                <p class="font-medium">{{ supplier.name }}</p>
                <p v-if="supplier.phone" class="text-sm text-gray-600">{{ supplier.phone }}</p>
                <p v-if="supplier.email" class="text-sm text-gray-600">{{ supplier.email }}</p>
                <p v-if="supplier.address" class="text-sm text-gray-500">{{ supplier.address }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ supplier.purchase_orders_count }} purchase order(s)</p>
                <div class="mt-2 flex gap-2">
                    <Link
                        :href="`/suppliers/${supplier.id}/edit`"
                        :aria-label="`Edit ${supplier.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="supplier.purchase_orders_count === 0"
                        type="button"
                        :aria-label="`Delete ${supplier.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border border-red-300 text-red-700"
                        @click="remove(supplier)"
                    >
                        Delete
                    </button>
                    <span v-else class="flex min-h-11 flex-1 items-center justify-center text-sm text-gray-400">In use</span>
                </div>
            </div>
        </div>

        <PagerLinks :meta="suppliers" />
    </div>
</template>