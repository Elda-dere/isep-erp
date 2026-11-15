<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import NavIcon from '../../../Components/NavIcon.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import { toScaled } from '../../../format.js';

// Products whose total quantity (all warehouses) is at or below the reorder level (INV-09).
// The rule lives in Product::lowStock() on the server: this page only displays the result.
const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
});

const page = usePage();

// The button is only a convenience: the server checks inventory.adjust on every request anyway.
const canAdjust = computed(() => page.props.auth?.permissions?.includes('inventory.adjust'));

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

// Convert BigInt scaled value (thousandths) into formatted string with commas and 3 decimal places.
function formatScaledQuantity(scaled) {
    if (scaled === null || scaled === undefined) {
        return '—';
    }

    const isNegative = scaled < 0n;
    const absScaled = isNegative ? -scaled : scaled;
    const str = absScaled.toString().padStart(4, '0');
    const whole = str.slice(0, -3).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const decimals = str.slice(-3);

    return `${isNegative ? '-' : ''}${whole}.${decimals}`;
}

// Shortage is reorder_level - total_quantity, calculated via exact BigInt thousandths.
function calculateShortage(product) {
    const total = toScaled(product?.total_quantity ?? '0.000', 3);
    const reorder = toScaled(product?.reorder_level ?? '0.000', 3);

    if (total === null || reorder === null) {
        return null;
    }

    const diff = reorder - total;
    return diff > 0n ? diff : 0n;
}

// Zero or negative total stock across all warehouses.
function isOutOfStock(product) {
    const total = toScaled(product?.total_quantity ?? '0.000', 3);
    return total !== null && total <= 0n;
}

const columns = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Product' },
    { key: 'category', label: 'Category' },
    { key: 'total_quantity', label: 'In stock', align: 'right' },
    { key: 'reorder_level', label: 'Reorder level', align: 'right' },
    { key: 'shortage', label: 'Shortage', align: 'right' },
    { key: 'actions', label: 'Actions', align: 'right', srOnly: true },
];
</script>

<template>
    <Head title="Low stock" />

    <div>
        <PageHeader
            title="Low stock"
            :subtitle="`${products.total} product(s) at or below reorder level`"
            back-href="/stock"
            back-label="Stock overview"
        >
            <template v-if="canAdjust" #actions>
                <AppButton href="/stock/adjust">
                    <template #icon>
                        <NavIcon name="stock-adjust" />
                    </template>
                    Adjust stock
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage class="mb-4" />

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
                <span class="text-ink-muted">{{ item.category?.name || '—' }}</span>
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
                        v-if="isOutOfStock(item)"
                        class="inline-flex items-center whitespace-nowrap rounded-sm border border-red-300 bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-900"
                    >
                        Out of stock
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center whitespace-nowrap rounded-sm border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                    >
                        Low stock
                    </span>
                </div>
            </template>

            <template #cell-reorder_level="{ item }">
                <span class="whitespace-nowrap font-medium text-ink">
                    {{ formatQuantity(item.reorder_level) }}
                    <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">
                        {{ item.unit.symbol }}
                    </span>
                </span>
            </template>

            <template #cell-shortage="{ item }">
                <span class="whitespace-nowrap font-medium text-ink">
                    {{ formatScaledQuantity(calculateShortage(item)) }}
                    <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">
                        {{ item.unit.symbol }}
                    </span>
                </span>
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
                        <span
                            v-if="isOutOfStock(item)"
                            class="inline-flex items-center whitespace-nowrap rounded-sm border border-red-300 bg-red-100 px-1.5 py-0.5 text-xs font-semibold text-red-900"
                        >
                            Out of stock
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center whitespace-nowrap rounded-sm border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                        >
                            Low stock
                        </span>
                    </div>
                </div>

                <p class="mt-2 text-sm text-ink-muted">
                    Category: <span class="text-ink">{{ item.category?.name || '—' }}</span>
                </p>

                <div class="mt-3 grid grid-cols-3 gap-2 rounded border border-border-subtle bg-stone/40 p-2.5 text-xs">
                    <div>
                        <p class="text-ink-muted">In stock</p>
                        <p class="mt-0.5 font-semibold text-ink tabular-nums">
                            {{ formatQuantity(item.total_quantity) }}
                            <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">{{ item.unit.symbol }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-ink-muted">Reorder level</p>
                        <p class="mt-0.5 font-semibold text-ink tabular-nums">
                            {{ formatQuantity(item.reorder_level) }}
                            <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">{{ item.unit.symbol }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-ink-muted">Shortage</p>
                        <p class="mt-0.5 font-semibold text-ink tabular-nums">
                            {{ formatScaledQuantity(calculateShortage(item)) }}
                            <span v-if="item.unit?.symbol" class="font-normal text-ink-muted">{{ item.unit.symbol }}</span>
                        </p>
                    </div>
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
                    title="All stock levels healthy"
                    description="There are currently no active products at or below their reorder level."
                    icon="low-stock"
                >
                    <template #actions>
                        <AppButton href="/stock" variant="secondary">
                            <template #icon>
                                <NavIcon name="stock" />
                            </template>
                            Stock overview
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="products" />
    </div>
</template>
