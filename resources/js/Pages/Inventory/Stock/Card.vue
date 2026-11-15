<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { router, usePage } from '@inertiajs/vue3';
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
import TextInput from '../../../Components/TextInput.vue';
import { formatDateTime } from '../../../format.js';

const props = defineProps({
    product: Object,
    movements: Object, // each row has quantity (signed) and running_balance, calculated by MySQL
    filters: Object,
    warehouses: Array,
    types: Array,
});

const page = usePage();

// Validation messages for the filters (for example "to" earlier than "from", invalid warehouse_id, or invalid type).
const errors = computed(() => page.props.errors ?? {});
const filterErrors = computed(() => Object.values(errors.value).flat().filter(Boolean));

const warehouseId = ref(props.filters?.warehouse_id ?? '');
const type = ref(props.filters?.type ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');
const isFiltering = ref(false);

const hasActiveFilters = computed(
    () =>
        (warehouseId.value !== '' && warehouseId.value !== null && warehouseId.value !== undefined) ||
        (type.value !== '' && type.value !== null && type.value !== undefined) ||
        Boolean(from.value) ||
        Boolean(to.value),
);

function applyFilters() {
    router.get(
        `/stock/card/${props.product.id}`,
        {
            warehouse_id: warehouseId.value || undefined,
            type: type.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
            onStart: () => {
                isFiltering.value = true;
            },
            onFinish: () => {
                isFiltering.value = false;
            },
        },
    );
}

let skipNextFilterWatch = false;

watch([warehouseId, type, from, to], () => {
    if (skipNextFilterWatch) {
        return;
    }
    applyFilters();
});

async function clearFilters() {
    skipNextFilterWatch = true;
    warehouseId.value = '';
    type.value = '';
    from.value = '';
    to.value = '';
    await nextTick();
    skipNextFilterWatch = false;
    applyFilters();
}

// Friendly names for the stored movement types (INV-04).
const typeLabels = {
    opening: 'Opening stock',
    receipt: 'Goods receipt',
    sale: 'Sale',
    sale_reversal: 'Sale reversal',
    adjustment: 'Adjustment',
    transfer_in: 'Transfer in',
    transfer_out: 'Transfer out',
};

const warehouseOptions = computed(() =>
    (props.warehouses ?? []).map((warehouse) => ({
        value: warehouse.id,
        label: warehouse.name,
    })),
);

const typeOptions = computed(() =>
    (props.types ?? []).map((option) => ({
        value: option,
        label: typeLabels[option] ?? option,
    })),
);

// Display only: format exact decimal strings with thousands separators and 3 decimal places (never Number()).
function formatQuantity(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const text = String(value).trim();
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(text);
    if (!match) {
        return text;
    }

    const [, sign, whole, rawDecimals = ''] = match;
    const withCommas = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimals = rawDecimals.length <= 3 ? rawDecimals.padEnd(3, '0') : rawDecimals.slice(0, 3);

    return `${sign}${withCommas}.${decimals}`;
}

// Quantities are exact strings. A leading "-" means stock went out.
function isOut(movement) {
    return String(movement?.quantity ?? '').startsWith('-');
}

function formatSignedQuantity(movement) {
    if (!movement?.quantity && movement?.quantity !== 0) {
        return '—';
    }
    const formatted = formatQuantity(movement.quantity);
    if (formatted === '—') {
        return '—';
    }
    return isOut(movement) ? formatted : `+${formatted}`;
}

// The document that caused the movement, if any. Links to those documents come with
// the procurement and sales screens.
function linkedDocument(movement) {
    if (movement?.goods_receipt_item_id) return `Receipt line #${movement.goods_receipt_item_id}`;
    if (movement?.sale_item_id) return `Sale line #${movement.sale_item_id}`;
    return '';
}

const columns = [
    { key: 'created_at', label: 'Date' },
    { key: 'movement_type', label: 'Type' },
    { key: 'warehouse', label: 'Warehouse' },
    { key: 'quantity', label: 'Quantity', align: 'right' },
    { key: 'running_balance', label: 'Balance', align: 'right' },
    { key: 'performed_by', label: 'By' },
    { key: 'document', label: 'Document' },
];
</script>

<template>
    <Head :title="`Stock card: ${product.name}`" />

    <div>
        <PageHeader
            :title="`Stock card: ${product.name}`"
            back-href="/stock"
            back-label="Stock overview"
        >
            <template #subtitle>
                <span class="font-mono text-ink">{{ product.sku }}</span>
                <span class="mx-1.5 text-ink-faint">·</span>
                <span>{{ product.category?.name ?? '—' }}</span>
                <span v-if="product.unit?.symbol">
                    <span class="mx-1.5 text-ink-faint">·</span>
                    <span>unit: {{ product.unit.symbol }}</span>
                </span>
            </template>
        </PageHeader>

        <FlashMessage />

        <!-- Filters -->
        <Card padding="sm" class="mb-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <FormField for-id="warehouse" label="Warehouse" label-tone="compact">
                    <SelectInput
                        v-model="warehouseId"
                        :options="warehouseOptions"
                        placeholder="All warehouses"
                        :invalid="Boolean(errors.warehouse_id)"
                    />
                </FormField>

                <FormField for-id="type" label="Type" label-tone="compact">
                    <SelectInput
                        v-model="type"
                        :options="typeOptions"
                        placeholder="All types"
                        :invalid="Boolean(errors.type)"
                    />
                </FormField>

                <FormField for-id="from" label="From" label-tone="compact">
                    <TextInput
                        v-model="from"
                        type="date"
                        :invalid="Boolean(errors.from)"
                    />
                </FormField>

                <FormField for-id="to" label="To" label-tone="compact">
                    <TextInput
                        v-model="to"
                        type="date"
                        :invalid="Boolean(errors.to)"
                    />
                </FormField>
            </div>

            <template v-if="hasActiveFilters || isFiltering" #footer>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p role="status" class="text-xs text-ink-muted">
                        <span v-if="isFiltering">Updating stock card…</span>
                        <span v-else>Active filters applied</span>
                    </p>
                    <AppButton
                        v-if="hasActiveFilters"
                        variant="secondary"
                        size="sm"
                        :disabled="isFiltering"
                        @click="clearFilters"
                    >
                        Clear filters
                    </AppButton>
                </div>
            </template>
        </Card>

        <ErrorAlert
            v-if="filterErrors.length"
            :messages="filterErrors"
            class="mb-4"
        />

        <p class="mb-4 text-xs text-ink-muted leading-relaxed">
            The balance on each line is the real running balance, even when a filter hides other lines.
            With a warehouse chosen, it is that warehouse's balance.
        </p>

        <div :aria-busy="isFiltering ? 'true' : undefined" :class="{ 'opacity-75 transition-opacity': isFiltering }">
            <DataTable
                :items="movements.data"
                :columns="columns"
                breakpoint="sm"
            >
                <template #cell-created_at="{ item }">
                    <span class="whitespace-nowrap text-ink-muted">
                        {{ formatDateTime(item.created_at) }}
                    </span>
                </template>

                <template #cell-movement_type="{ item }">
                    <div>
                        <span class="font-medium text-ink">
                            {{ typeLabels[item.movement_type] ?? item.movement_type }}
                        </span>
                        <p v-if="item.note" class="mt-0.5 text-xs text-ink-muted">
                            {{ item.note }}
                        </p>
                    </div>
                </template>

                <template #cell-warehouse="{ item }">
                    <span class="text-ink">
                        {{ item.warehouse?.name ?? '—' }}
                    </span>
                </template>

                <template #cell-quantity="{ item }">
                    <span
                        class="font-medium tabular-nums"
                        :class="isOut(item) ? 'text-red-700' : 'text-forest'"
                    >
                        {{ formatSignedQuantity(item) }}
                    </span>
                </template>

                <template #cell-running_balance="{ item }">
                    <span class="font-medium text-ink tabular-nums">
                        {{ formatQuantity(item.running_balance) }}
                    </span>
                </template>

                <template #cell-performed_by="{ item }">
                    <span class="text-ink-muted">
                        {{ item.performed_by?.name ?? '—' }}
                    </span>
                </template>

                <template #cell-document="{ item }">
                    <span class="text-ink-muted">
                        {{ linkedDocument(item) || '—' }}
                    </span>
                </template>

                <template #mobile-card="{ item }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">
                                {{ typeLabels[item.movement_type] ?? item.movement_type }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                {{ formatDateTime(item.created_at) }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-right text-sm font-semibold tabular-nums"
                            :class="isOut(item) ? 'text-red-700' : 'text-forest'"
                        >
                            {{ formatSignedQuantity(item) }}
                        </p>
                    </div>

                    <p class="mt-2 text-sm text-ink-muted">
                        <span>{{ item.warehouse?.name ?? '—' }}</span>
                        <span class="mx-1.5 text-ink-faint">·</span>
                        <span class="text-ink">balance {{ formatQuantity(item.running_balance) }}</span>
                        <span v-if="product.unit?.symbol" class="text-ink-muted"> {{ product.unit.symbol }}</span>
                    </p>

                    <p v-if="item.note" class="mt-1.5 text-sm text-ink-muted">
                        {{ item.note }}
                    </p>

                    <p class="mt-1.5 text-xs text-ink-muted">
                        <span>{{ item.performed_by?.name ?? '—' }}</span>
                        <template v-if="linkedDocument(item)">
                            <span class="mx-1 text-ink-faint">·</span>
                            <span>{{ linkedDocument(item) }}</span>
                        </template>
                    </p>
                </template>

                <template #empty>
                    <EmptyState
                        title="No movements found."
                        :description="
                            hasActiveFilters
                                ? 'No stock movements match the selected filters.'
                                : 'No stock movements have been recorded for this product yet.'
                        "
                        icon="stock"
                    >
                        <template v-if="hasActiveFilters" #actions>
                            <AppButton
                                variant="secondary"
                                size="sm"
                                @click="clearFilters"
                            >
                                Clear filters
                            </AppButton>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>
        </div>

        <PagerLinks :meta="movements" />
    </div>
</template>
