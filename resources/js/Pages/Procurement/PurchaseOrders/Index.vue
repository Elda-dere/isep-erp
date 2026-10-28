<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../../format.js';

const props = defineProps({
    orders: Object, // a Laravel paginator: .data holds rows the controller already prepared
    filters: Object,
    suppliers: Array,
    statuses: Array,
});

const page = usePage();

// The button is only a convenience: the server checks purchase_orders.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('purchase_orders.manage'));

// Validation messages for the filters (for example "to" earlier than "from").
const errors = computed(() => page.props.errors ?? {});

// The filter boxes start with the values from the URL, so a reload keeps them.
const status = ref(props.filters?.status ?? '');
const supplierId = ref(props.filters?.supplier_id ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

// Ask the server for the list again with the chosen filters.
// The filtering itself happens in PurchaseOrderController::index (SQL), not here.
function applyFilters() {
    router.get(
        '/purchase-orders',
        {
            status: status.value || undefined,
            supplier_id: supplierId.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([status, supplierId, from, to], applyFilters);

// "partially_received" is shown as "Partially received" in the dropdown.
function statusName(value) {
    const text = value.replace('_', ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
}
</script>

<template>
    <Head title="Purchase orders" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Purchase orders</h1>
                <p class="mt-1 text-sm text-gray-500">{{ orders.total }} order(s)</p>
            </div>
            <Link
                v-if="canManage"
                href="/purchase-orders/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New purchase order
            </Link>
        </div>

        <FlashMessage class="mt-4" />

        <!-- Filters. Labels are visible because the date boxes need them. -->
        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div>
                <label for="status" class="block text-xs text-gray-500">Status</label>
                <select id="status" v-model="status" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All statuses</option>
                    <option v-for="option in statuses" :key="option" :value="option">{{ statusName(option) }}</option>
                </select>
            </div>
            <div>
                <label for="supplier" class="block text-xs text-gray-500">Supplier</label>
                <select id="supplier" v-model="supplierId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All suppliers</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs text-gray-500">From</label>
                <input id="from" v-model="from" type="date" class="h-11 w-full rounded border border-gray-300 px-3" />
            </div>
            <div>
                <label for="to" class="block text-xs text-gray-500">To</label>
                <input id="to" v-model="to" type="date" class="h-11 w-full rounded border border-gray-300 px-3" />
            </div>
        </div>
        <p v-if="errors.to || errors.from || errors.status" class="mt-2 text-sm text-red-700" role="alert">
            {{ errors.to || errors.from || errors.status }}
        </p>

        <p v-if="orders.data.length === 0" class="mt-8 text-center text-gray-500">No purchase orders found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Order</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th class="text-right">Lines</th>
                    <th class="text-right">Total</th>
                    <th class="pl-4">Created</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="order in orders.data" :key="order.id" class="border-b">
                    <td class="py-1">
                        <Link :href="`/purchase-orders/${order.id}`" class="flex min-h-11 items-center font-mono underline">
                            {{ order.order_number }}
                        </Link>
                    </td>
                    <td>{{ order.supplier }}</td>
                    <td><StatusBadge :status="order.status" /></td>
                    <td class="text-right">{{ order.lines_count }}</td>
                    <td class="text-right">{{ formatMoney(order.total) }}</td>
                    <td class="pl-4">{{ formatDateTime(order.created_at) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="orders.data.length" class="mt-4 space-y-3 sm:hidden">
            <Link
                v-for="order in orders.data"
                :key="order.id"
                :href="`/purchase-orders/${order.id}`"
                class="block rounded border bg-white p-3"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="font-mono font-medium">{{ order.order_number }}</p>
                    <StatusBadge :status="order.status" />
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ order.supplier }}</p>
                <p class="mt-1 text-sm">{{ formatMoney(order.total) }} · {{ order.lines_count }} line(s)</p>
                <p class="text-xs text-gray-500">{{ formatDateTime(order.created_at) }}</p>
            </Link>
        </div>

        <PagerLinks :meta="orders" />
    </div>
</template>