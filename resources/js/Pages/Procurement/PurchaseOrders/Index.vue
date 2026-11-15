<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import TextInput from '../../../Components/TextInput.vue';
import { formatDateTime, formatMoney } from '../../../format.js';

const props = defineProps({
    orders: {
        type: Object, // Laravel paginator: .data holds rows prepared by controller
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    suppliers: {
        type: Array,
        required: true,
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const canManage = computed(() => Boolean(page.props.auth?.permissions?.includes('purchase_orders.manage')));
const errors = computed(() => page.props.errors ?? {});

const status = ref(props.filters?.status ?? '');
const supplierId = ref(props.filters?.supplier_id ? String(props.filters.supplier_id) : '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

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

function statusName(value) {
    const text = value.replace(/_/g, ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
}

const statusOptions = computed(() => [
    { value: '', label: 'All statuses' },
    ...props.statuses.map((s) => ({ value: s, label: statusName(s) })),
]);

const supplierOptions = computed(() => [
    { value: '', label: 'All suppliers' },
    ...props.suppliers.map((s) => ({ value: String(s.id), label: s.name })),
]);

const columns = [
    { key: 'order_number', label: 'Order' },
    { key: 'supplier', label: 'Supplier' },
    { key: 'status', label: 'Status' },
    { key: 'lines_count', label: 'Lines', align: 'right' },
    { key: 'total', label: 'Total', align: 'right' },
    { key: 'created_at', label: 'Created' },
];

const subtitle = computed(() => {
    const count = props.orders?.total ?? props.orders?.data?.length ?? 0;
    return `${count} purchase order${count === 1 ? '' : 's'}`;
});
</script>

<template>
    <Head title="Purchase orders" />

    <div class="space-y-6">
        <PageHeader
            title="Purchase orders"
            :subtitle="subtitle"
        >
            <template v-if="canManage" #actions>
                <AppButton
                    href="/purchase-orders/create"
                    variant="primary"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M10 4v12M4 10h12" />
                    </svg>
                    <span>New purchase order</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <!-- Filters card -->
        <Card padding="md">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <FormField for-id="filter-status" label="Status">
                    <SelectInput
                        id="filter-status"
                        v-model="status"
                        :options="statusOptions"
                    />
                </FormField>

                <FormField for-id="filter-supplier" label="Supplier">
                    <SelectInput
                        id="filter-supplier"
                        v-model="supplierId"
                        :options="supplierOptions"
                    />
                </FormField>

                <FormField for-id="filter-from" label="From date" :error="errors.from">
                    <TextInput
                        id="filter-from"
                        v-model="from"
                        type="date"
                    />
                </FormField>

                <FormField for-id="filter-to" label="To date" :error="errors.to">
                    <TextInput
                        id="filter-to"
                        v-model="to"
                        type="date"
                    />
                </FormField>
            </div>

            <div v-if="errors.to || errors.from || errors.status" class="mt-3">
                <ErrorAlert
                    :message="errors.to || errors.from || errors.status"
                    title="Invalid filter parameters"
                />
            </div>
        </Card>

        <!-- Orders DataTable -->
        <DataTable
            :items="orders.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-order_number="{ item }">
                <Link
                    :href="`/purchase-orders/${item.id}`"
                    class="inline-flex min-h-11 items-center font-mono font-medium text-forest hover:underline focus:outline-none focus:ring-1 focus:ring-forest rounded"
                >
                    {{ item.order_number }}
                </Link>
            </template>

            <template #cell-supplier="{ item }">
                <span class="text-ink font-medium">{{ item.supplier || '—' }}</span>
            </template>

            <template #cell-status="{ item }">
                <StatusBadge :status="item.status" />
            </template>

            <template #cell-lines_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.lines_count }}</span>
            </template>

            <template #cell-total="{ item }">
                <span class="font-mono text-sm tabular-nums font-medium text-ink">{{ formatMoney(item.total) }}</span>
            </template>

            <template #cell-created_at="{ item }">
                <span class="text-xs text-ink-muted">{{ formatDateTime(item.created_at) }}</span>
            </template>

            <template #mobile-card="{ item }">
                <Link
                    :href="`/purchase-orders/${item.id}`"
                    class="block space-y-2 select-none group"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-mono font-semibold text-forest group-hover:underline">
                            {{ item.order_number }}
                        </span>
                        <StatusBadge :status="item.status" />
                    </div>

                    <p class="text-sm font-medium text-ink">
                        {{ item.supplier || '—' }}
                    </p>

                    <div class="flex items-center justify-between pt-1 border-t border-border-subtle text-xs">
                        <span class="font-mono text-sm font-semibold text-ink tabular-nums">
                            {{ formatMoney(item.total) }}
                        </span>
                        <span class="font-mono text-ink-muted tabular-nums">
                            {{ item.lines_count }} {{ item.lines_count === 1 ? 'line' : 'lines' }}
                        </span>
                    </div>

                    <p class="text-xs text-ink-faint">
                        {{ formatDateTime(item.created_at) }}
                    </p>
                </Link>
            </template>

            <template #empty>
                <EmptyState
                    icon="purchase-orders"
                    title="No purchase orders found"
                    description="No purchase orders match the selected filters or have been created yet."
                >
                    <template v-if="canManage" #actions>
                        <AppButton
                            href="/purchase-orders/create"
                            variant="primary"
                        >
                            New purchase order
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="orders" />
    </div>
</template>
