<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import NavIcon from '../../../Components/NavIcon.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import TextInput from '../../../Components/TextInput.vue';
import { toScaled } from '../../../format.js';

const props = defineProps({
    products: Object, // each product has total_quantity and a "stocks" list (one line per warehouse)
    filters: Object,
    categories: Array,
    warehouses: Array,
});

const page = usePage();

// The button is only a convenience: the server checks inventory.adjust on every request anyway.
const canAdjust = computed(() => page.props.auth?.permissions?.includes('inventory.adjust'));

// Validation messages for URL filters (for example invalid category_id or warehouse_id).
const errors = computed(() => page.props.errors ?? {});
const filterErrors = computed(() => Object.values(errors.value).flat().filter(Boolean));

// The filter boxes start with the values from the URL, so a reload keeps them.
const search = ref(props.filters?.search ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const warehouseId = ref(props.filters?.warehouse_id ?? '');
const isFiltering = ref(false);

const hasActiveFilters = computed(
    () =>
        Boolean(search.value) ||
        (categoryId.value !== '' && categoryId.value !== null && categoryId.value !== undefined) ||
        (warehouseId.value !== '' && warehouseId.value !== null && warehouseId.value !== undefined),
);

// Ask the server for the list again with the chosen filters.
// The filtering itself happens in StockReportController::overview (SQL), not here.
function applyFilters() {
    router.get(
        '/stock',
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            warehouse_id: warehouseId.value || undefined,
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

// Wait 300 ms after the last keystroke, so we do not ask on every letter.
let searchTimer = null;
let skipNextFilterWatch = false;

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});

watch(search, () => {
    if (skipNextFilterWatch) {
        return;
    }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
});

watch([categoryId, warehouseId], () => {
    if (skipNextFilterWatch) {
        return;
    }
    clearTimeout(searchTimer);
    applyFilters();
});

async function clearFilters() {
    clearTimeout(searchTimer);
    searchTimer = null;
    skipNextFilterWatch = true;
    search.value = '';
    categoryId.value = '';
    warehouseId.value = '';
    await nextTick();
    skipNextFilterWatch = false;
    applyFilters();
}

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

// "Low" badge: total at or below the reorder level (INV-09) using exact thousandths (BigInt).
// With a warehouse chosen, the total covers that warehouse only, so the comparison would
// be misleading: we only show the badge when all warehouses are included.
function isLow(product) {
    if (warehouseId.value !== '' && warehouseId.value !== null && warehouseId.value !== undefined) {
        return false;
    }

    const totalThousandths = toScaled(product.total_quantity, 3);
    const reorderThousandths = toScaled(product.reorder_level, 3);

    if (totalThousandths === null || reorderThousandths === null) {
        return false;
    }

    return totalThousandths <= reorderThousandths;
}

const categoryOptions = computed(() =>
    (props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
);

const warehouseOptions = computed(() =>
    (props.warehouses ?? []).map((warehouse) => ({
        value: warehouse.id,
        label: warehouse.name,
    })),
);

const columns = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Product' },
    { key: 'category', label: 'Category' },
    { key: 'total_quantity', label: 'Total', align: 'right' },
    { key: 'stocks', label: 'By warehouse' },
    { key: 'actions', label: 'Actions', align: 'right', srOnly: true },
];
</script>

<template>
    <Head title="Stock overview" />

    <div>
        <PageHeader title="Stock overview" :subtitle="`${products.total} product(s)`">
            <template #actions>
                <AppButton href="/stock/low" variant="secondary">
                    <template #icon>
                        <NavIcon name="low-stock" />
                    </template>
                    Low stock
                </AppButton>
                <AppButton v-if="canAdjust" href="/stock/adjust">
                    <template #icon>
                        <NavIcon name="stock-adjust" />
                    </template>
                    Adjust stock
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <Card padding="sm" class="mb-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <FormField for-id="search" label="Search" label-tone="compact">
                    <TextInput
                        v-model="search"
                        type="search"
                        placeholder="Search name or SKU"
                        :invalid="Boolean(errors.search)"
                    />
                </FormField>

                <FormField for-id="category" label="Category" label-tone="compact">
                    <SelectInput
                        v-model="categoryId"
                        :options="categoryOptions"
                        placeholder="All categories"
                        :invalid="Boolean(errors.category_id)"
                    />
                </FormField>

                <FormField for-id="warehouse" label="Warehouse" label-tone="compact">
                    <SelectInput
                        v-model="warehouseId"
                        :options="warehouseOptions"
                        placeholder="All warehouses"
                        :invalid="Boolean(errors.warehouse_id)"
                    />
                </FormField>
            </div>

            <template v-if="hasActiveFilters || isFiltering" #footer>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p role="status" class="text-xs text-ink-muted">
                        <span v-if="isFiltering">Updating stock overview…</span>
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

        <div :aria-busy="isFiltering ? 'true' : undefined" :class="{ 'opacity-75 transition-opacity': isFiltering }">
            <DataTable
                :items="products.data"
                :columns="columns"
                breakpoint="sm"
            >
                <template #cell-sku="{ item }">
                    <span class="font-mono text-ink">{{ item.sku }}</span>
                </template>

                <template #cell-name="{ item }">
                    <span class="font-medium text-ink">{{ item.name }}</span>
                </template>

                <template #cell-category="{ item }">
                    {{ item.category?.name ?? '—' }}
                </template>

                <template #cell-total_quantity="{ item }">
                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                        <span class="whitespace-nowrap font-medium text-ink">
                            {{ formatQuantity(item.total_quantity) }}
                            <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">
                                {{ item.unit.symbol }}
                            </span>
                        </span>
                        <span
                            v-if="isLow(item)"
                            class="inline-flex items-center whitespace-nowrap rounded-sm border border-red-200 bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-800"
                        >
                            Low
                        </span>
                    </div>
                </template>

                <template #cell-stocks="{ item }">
                    <p v-if="!item.stocks?.length" class="text-xs text-ink-faint">
                        No stock
                    </p>
                    <ul v-else class="space-y-0.5 text-sm text-ink-muted tabular-nums">
                        <li v-for="stock in item.stocks" :key="stock.id">
                            <span>{{ stock.warehouse?.name }}:</span>
                            <span class="ml-1 font-medium text-ink">{{ formatQuantity(stock.quantity) }}</span>
                        </li>
                    </ul>
                </template>

                <template #cell-actions="{ item }">
                    <div class="-my-1.5 flex items-center justify-end">
                        <AppButton
                            :href="`/stock/card/${item.id}`"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Stock card for ${item.name}`"
                        >
                            Stock card
                        </AppButton>
                    </div>
                </template>

                <template #mobile-card="{ item }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">
                                {{ item.name }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-ink-muted">
                                {{ item.sku }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="whitespace-nowrap text-sm font-semibold text-ink tabular-nums">
                                {{ formatQuantity(item.total_quantity) }}
                                <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">
                                    {{ item.unit.symbol }}
                                </span>
                            </p>
                            <span
                                v-if="isLow(item)"
                                class="mt-1 inline-flex items-center whitespace-nowrap rounded-sm border border-red-200 bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-800"
                            >
                                Low
                            </span>
                        </div>
                    </div>

                    <p class="mt-2 text-sm text-ink-muted">
                        {{ item.category?.name ?? '—' }}
                    </p>

                    <div class="mt-2 space-y-0.5 text-sm text-ink-muted tabular-nums">
                        <p v-if="!item.stocks?.length" class="text-xs text-ink-faint">
                            No stock
                        </p>
                        <p v-for="stock in item.stocks" :key="stock.id">
                            {{ stock.warehouse?.name }}:
                            <span class="font-medium text-ink">{{ formatQuantity(stock.quantity) }}</span>
                        </p>
                    </div>

                    <div class="mt-3 border-t border-border-subtle pt-3">
                        <AppButton
                            :href="`/stock/card/${item.id}`"
                            variant="secondary"
                            size="sm"
                            block
                            :aria-label="`Stock card for ${item.name}`"
                        >
                            Stock card
                        </AppButton>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        title="No products found."
                        :description="
                            hasActiveFilters
                                ? 'No products match the selected filters.'
                                : 'No products are available yet.'
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

        <PagerLinks :meta="products" />
    </div>
</template>
