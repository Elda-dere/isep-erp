<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../format.js';

const props = defineProps({
    payments: Object, // a Laravel paginator: .data holds rows the controller already prepared
    filters: Object,
    statuses: Array,
    providers: Array,
});

// Validation messages for the filters (for example "to" earlier than "from").
const errors = computed(() => usePage().props.errors ?? {});

// The filter boxes start with the values from the URL, so a reload keeps them.
const status = ref(props.filters?.status ?? '');
const provider = ref(props.filters?.provider ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

// The filtering itself happens in PaymentController::index (SQL), not here.
watch([status, provider, from, to], () => {
    router.get(
        '/payments',
        {
            status: status.value || undefined,
            provider: provider.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
});

const statusName = (value) => value.charAt(0).toUpperCase() + value.slice(1);
</script>

<template>
    <Head title="Payments" />

    <div>
        <h1 class="text-2xl font-semibold">Payments</h1>
        <p class="mt-1 text-sm text-gray-500">{{ payments.total }} payment(s)</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div>
                <label for="status" class="block text-xs text-gray-500">Status</label>
                <select id="status" v-model="status" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All statuses</option>
                    <option v-for="option in statuses" :key="option" :value="option">{{ statusName(option) }}</option>
                </select>
            </div>
            <div>
                <label for="provider" class="block text-xs text-gray-500">Provider</label>
                <select id="provider" v-model="provider" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All providers</option>
                    <option v-for="option in providers" :key="option" :value="option">{{ statusName(option) }}</option>
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
        <p v-if="errors.to || errors.from || errors.status || errors.provider" class="mt-2 text-sm text-red-700" role="alert">
            {{ errors.to || errors.from || errors.status || errors.provider }}
        </p>

        <p v-if="payments.data.length === 0" class="mt-8 text-center text-gray-500">No payments found.</p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Sale</th>
                    <th>Status</th>
                    <th class="text-right">Amount</th>
                    <th class="pl-4">Created</th>
                    <th>Paid</th>
                    <th><span class="sr-only">Details</span></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="payment in payments.data" :key="payment.id" class="border-b">
                    <td class="py-3 font-mono">{{ payment.sale_number }}</td>
                    <td><StatusBadge :status="payment.status" /></td>
                    <td class="text-right">{{ formatMoney(payment.amount, payment.currency) }}</td>
                    <td class="pl-4">{{ formatDateTime(payment.created_at) }}</td>
                    <td>{{ payment.paid_at ? formatDateTime(payment.paid_at) : '—' }}</td>
                    <td class="text-right">
                        <Link :href="`/payments/${payment.id}`" :aria-label="`Details of the payment for ${payment.sale_number}`" class="flex min-h-11 items-center justify-end px-3 underline">
                            Details
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="payments.data.length" class="mt-4 space-y-3 sm:hidden">
            <Link v-for="payment in payments.data" :key="payment.id" :href="`/payments/${payment.id}`" class="block rounded border bg-white p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-mono font-medium">{{ payment.sale_number }}</p>
                    <StatusBadge :status="payment.status" />
                </div>
                <p class="mt-1 text-sm">{{ formatMoney(payment.amount, payment.currency) }}</p>
                <p class="text-xs text-gray-500">{{ formatDateTime(payment.paid_at ?? payment.created_at) }}</p>
            </Link>
        </div>

        <PagerLinks :meta="payments" />
    </div>
</template>