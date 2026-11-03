<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime } from '../../../format.js';

const props = defineProps({
    receipts: Object, // a Laravel paginator: .data holds rows the controller already prepared
    filters: Object,
    statuses: Array,
});

// The filter box starts with the value from the URL, so a reload keeps it.
const status = ref(props.filters?.status ?? '');

// Ask the server for the list again with the chosen filter.
// The filtering itself happens in GoodsReceiptController::index (SQL), not here.
// The purchase order filter (?purchase_order_id=) is kept when the status changes.
function applyFilters() {
    router.get(
        '/goods-receipts',
        {
            status: status.value || undefined,
            purchase_order_id: props.filters?.purchase_order_id || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(status, applyFilters);

function statusName(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
</script>

<template>
    <Head title="Goods receipts" />

    <div>
        <h1 class="text-2xl font-semibold">Goods receipts</h1>
        <p class="mt-1 text-sm text-gray-500">{{ receipts.total }} receipt(s)</p>
        <p class="mt-1 text-sm text-gray-500">To receive goods, open a placed purchase order and press "Receive goods".</p>

        <FlashMessage class="mt-4" />

        <div v-if="filters?.purchase_order_id" class="mt-4 flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded bg-gray-100 px-2 py-1">Showing the receipts of one purchase order</span>
            <Link href="/goods-receipts" class="flex min-h-11 items-center underline">Show all receipts</Link>
        </div>

        <div class="mt-4 max-w-xs">
            <label for="status" class="block text-xs text-gray-500">Status</label>
            <select id="status" v-model="status" class="h-11 w-full rounded border border-gray-300 px-3">
                <option value="">All statuses</option>
                <option v-for="option in statuses" :key="option" :value="option">{{ statusName(option) }}</option>
            </select>
        </div>

        <p v-if="receipts.data.length === 0" class="mt-8 text-center text-gray-500">No receipts found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Receipt</th>
                    <th>Order</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th class="text-right">Lines</th>
                    <th class="pl-4">Received by</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="receipt in receipts.data" :key="receipt.id" class="border-b">
                    <td class="py-1">
                        <Link :href="`/goods-receipts/${receipt.id}`" class="flex min-h-11 items-center font-mono underline">
                            {{ receipt.receipt_number }}
                        </Link>
                    </td>
                    <td class="font-mono">{{ receipt.order_number }}</td>
                    <td>{{ receipt.supplier }}</td>
                    <td><StatusBadge :status="receipt.status" /></td>
                    <td class="text-right">{{ receipt.lines_count }}</td>
                    <td class="pl-4">{{ receipt.received_by }}</td>
                    <td>{{ formatDateTime(receipt.received_at) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="receipts.data.length" class="mt-4 space-y-3 sm:hidden">
            <Link
                v-for="receipt in receipts.data"
                :key="receipt.id"
                :href="`/goods-receipts/${receipt.id}`"
                class="block rounded border bg-white p-3"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="font-mono font-medium">{{ receipt.receipt_number }}</p>
                    <StatusBadge :status="receipt.status" />
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ receipt.order_number }} · {{ receipt.supplier }}</p>
                <p class="mt-1 text-sm">{{ receipt.lines_count }} line(s)</p>
                <p class="text-xs text-gray-500">{{ receipt.received_by }} · {{ formatDateTime(receipt.received_at) }}</p>
            </Link>
        </div>

        <PagerLinks :meta="receipts" />
    </div>
</template>