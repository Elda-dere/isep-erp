<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../Components/FlashMessage.vue';
import PagerLinks from '../../Components/PagerLinks.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../format.js';

const props = defineProps({
    sales: Object, // a Laravel paginator: .data holds rows the controller already prepared
    filters: Object,
    customers: Array,
    cashiers: Array,
    statuses: Array,
});

const page = usePage();

// The button is only a convenience: the server checks sales.create on every request anyway.
const canCreate = computed(() => page.props.auth?.permissions?.includes('sales.create'));

// Validation messages for the filters (for example "to" earlier than "from").
const errors = computed(() => page.props.errors ?? {});

// The filter boxes start with the values from the URL, so a reload keeps them.
const status = ref(props.filters?.status ?? '');
const customerId = ref(props.filters?.customer_id ?? '');
const createdBy = ref(props.filters?.created_by ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

// Ask the server for the list again with the chosen filters.
// The filtering itself happens in SaleController::index (SQL), not here.
function applyFilters() {
    router.get(
        '/sales',
        {
            status: status.value || undefined,
            customer_id: customerId.value || undefined,
            created_by: createdBy.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([status, customerId, createdBy, from, to], applyFilters);

const statusNames = { pending: 'Pending payment', paid: 'Paid', cancelled: 'Cancelled' };
</script>

<template>
    <Head title="Sales" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Sales</h1>
                <p class="mt-1 text-sm text-gray-500">{{ sales.total }} sale(s)</p>
            </div>
            <Link v-if="canCreate" href="/sales/create" class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white">
                New sale
            </Link>
        </div>

        <FlashMessage class="mt-4" />

        <!-- Filters. Labels are visible because the date boxes need them. -->
        <div class="mt-4 grid gap-3 sm:grid-cols-5">
            <div>
                <label for="status" class="block text-xs text-gray-500">Status</label>
                <select id="status" v-model="status" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All statuses</option>
                    <option v-for="option in statuses" :key="option" :value="option">{{ statusNames[option] ?? option }}</option>
                </select>
            </div>
            <div>
                <label for="customer" class="block text-xs text-gray-500">Customer</label>
                <select id="customer" v-model="customerId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All customers</option>
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                </select>
            </div>
            <div>
                <label for="cashier" class="block text-xs text-gray-500">Cashier</label>
                <select id="cashier" v-model="createdBy" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All cashiers</option>
                    <option v-for="cashier in cashiers" :key="cashier.id" :value="cashier.id">{{ cashier.name }}</option>
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

        <p v-if="sales.data.length === 0" class="mt-8 text-center text-gray-500">No sales found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Sale</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th class="text-right">Lines</th>
                    <th class="text-right">Total</th>
                    <th class="pl-4">Cashier</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="sale in sales.data" :key="sale.id" class="border-b">
                    <td class="py-1">
                        <Link :href="`/sales/${sale.id}`" class="flex min-h-11 items-center font-mono underline">
                            {{ sale.sale_number }}
                        </Link>
                    </td>
                    <td>{{ sale.customer }}</td>
                    <td><StatusBadge :status="sale.status" /></td>
                    <td class="text-right">{{ sale.lines_count }}</td>
                    <td class="text-right">{{ formatMoney(sale.total_amount, sale.currency) }}</td>
                    <td class="pl-4">{{ sale.created_by }}</td>
                    <td>{{ formatDateTime(sale.created_at) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="sales.data.length" class="mt-4 space-y-3 sm:hidden">
            <Link v-for="sale in sales.data" :key="sale.id" :href="`/sales/${sale.id}`" class="block rounded border bg-white p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-mono font-medium">{{ sale.sale_number }}</p>
                    <StatusBadge :status="sale.status" />
                </div>
                <p class="mt-1 text-sm text-gray-600">{{ sale.customer }}</p>
                <p class="mt-1 text-sm">{{ formatMoney(sale.total_amount, sale.currency) }} · {{ sale.lines_count }} line(s)</p>
                <p class="text-xs text-gray-500">{{ sale.created_by }} · {{ formatDateTime(sale.created_at) }}</p>
            </Link>
        </div>

        <PagerLinks :meta="sales" />
    </div>
</template>