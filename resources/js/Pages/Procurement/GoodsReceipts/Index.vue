<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '../../../Components/Card.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime } from '../../../format.js';

const props = defineProps({
    receipts: {
        type: Object, // Laravel paginator: .data holds rows prepared by controller
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    statuses: {
        type: Array,
        required: true,
    },
});

const status = ref(props.filters?.status ?? '');

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

const statusOptions = computed(() => [
    { value: '', label: 'All statuses' },
    ...props.statuses.map((s) => ({ value: s, label: statusName(s) })),
]);

const subtitle = computed(() => {
    const count = props.receipts?.total ?? props.receipts?.data?.length ?? 0;
    return `${count} goods receipt${count === 1 ? '' : 's'}`;
});

const columns = [
    { key: 'receipt_number', label: 'Receipt' },
    { key: 'order_number', label: 'Order' },
    { key: 'supplier', label: 'Supplier' },
    { key: 'status', label: 'Status' },
    { key: 'lines_count', label: 'Lines', align: 'right' },
    { key: 'received_by', label: 'Received by' },
    { key: 'received_at', label: 'Date' },
];
</script>

<template>
    <Head title="Goods receipts" />

    <div class="space-y-6">
        <PageHeader
            title="Goods receipts"
            :subtitle="subtitle"
        />

        <FlashMessage />

        <!-- Filters card -->
        <Card padding="md">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="w-full sm:max-w-xs">
                    <FormField for-id="filter-status" label="Filter by status">
                        <SelectInput
                            id="filter-status"
                            v-model="status"
                            :options="statusOptions"
                        />
                    </FormField>
                </div>

                <!-- Purchase order filter pill -->
                <div
                    v-if="filters?.purchase_order_id"
                    class="flex flex-wrap items-center gap-2 rounded border border-border-subtle bg-stone/40 px-3 py-2 text-xs text-ink-muted"
                >
                    <span>Filtered by purchase order</span>
                    <Link
                        href="/goods-receipts"
                        class="inline-flex min-h-11 items-center font-medium text-forest hover:underline"
                    >
                        Show all receipts
                    </Link>
                </div>
            </div>
        </Card>

        <!-- Receipts DataTable -->
        <DataTable
            :items="receipts.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-receipt_number="{ item }">
                <Link
                    :href="`/goods-receipts/${item.id}`"
                    class="inline-flex min-h-11 items-center font-mono font-medium text-forest hover:underline focus:outline-none focus:ring-1 focus:ring-forest rounded"
                >
                    {{ item.receipt_number }}
                </Link>
            </template>

            <template #cell-order_number="{ item }">
                <Link
                    :href="`/purchase-orders/${item.order_id}`"
                    class="inline-flex min-h-11 items-center font-mono text-xs text-ink-muted hover:text-forest hover:underline focus:outline-none focus:ring-1 focus:ring-forest rounded"
                >
                    {{ item.order_number }}
                </Link>
            </template>

            <template #cell-supplier="{ item }">
                <span class="text-sm font-medium text-ink">{{ item.supplier || '—' }}</span>
            </template>

            <template #cell-status="{ item }">
                <StatusBadge :status="item.status" />
            </template>

            <template #cell-lines_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.lines_count }}</span>
            </template>

            <template #cell-received_by="{ item }">
                <span class="text-xs text-ink-muted">{{ item.received_by || '—' }}</span>
            </template>

            <template #cell-received_at="{ item }">
                <span class="text-xs text-ink-muted">{{ formatDateTime(item.received_at) }}</span>
            </template>

            <template #mobile-card="{ item }">
                <Link
                    :href="`/goods-receipts/${item.id}`"
                    class="block space-y-2 select-none group"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-mono font-semibold text-forest group-hover:underline">
                            {{ item.receipt_number }}
                        </span>
                        <StatusBadge :status="item.status" />
                    </div>

                    <p class="text-sm font-medium text-ink">
                        <span class="font-mono text-xs text-ink-muted">{{ item.order_number }}</span>
                        <span class="mx-1 text-ink-faint">·</span>
                        <span>{{ item.supplier || '—' }}</span>
                    </p>

                    <div class="flex items-center justify-between pt-1 border-t border-border-subtle text-xs text-ink-muted">
                        <span class="font-mono tabular-nums">
                            {{ item.lines_count }} {{ item.lines_count === 1 ? 'line' : 'lines' }}
                        </span>
                        <span>{{ item.received_by || 'Unknown' }}</span>
                    </div>

                    <p class="text-xs text-ink-faint">
                        {{ formatDateTime(item.received_at) }}
                    </p>
                </Link>
            </template>

            <template #empty>
                <EmptyState
                    icon="goods-receipts"
                    title="No goods receipts found"
                    description="No goods receipts match the selected filters. To receive goods, open a placed purchase order and click 'Receive goods'."
                />
            </template>
        </DataTable>

        <PagerLinks :meta="receipts" />
    </div>
</template>
