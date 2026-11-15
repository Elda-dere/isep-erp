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
import StatusBadge from '../../../Components/StatusBadge.vue';
import TextInput from '../../../Components/TextInput.vue';
import { formatMoney, toScaled } from '../../../format.js';

const props = defineProps({
    products: Object,
    filters: Object,
    categories: Array,
});

const page = usePage();

// Buttons are only a convenience: the server checks products.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('products.manage'));
const pageErrors = computed(() => Object.values(page.props.errors ?? {}).flat().filter(Boolean));

// The filter boxes start with the values from the URL, so a reload keeps them.
const search = ref(props.filters?.search ?? '');
const categoryId = ref(props.filters?.category_id ?? '');
const active = ref(props.filters?.active ?? '');
const isFiltering = ref(false);
const togglingId = ref(null);

const hasActiveFilters = computed(
    () =>
        Boolean(search.value) ||
        (categoryId.value !== '' && categoryId.value !== null && categoryId.value !== undefined) ||
        (active.value !== '' && active.value !== null && active.value !== undefined),
);

// Ask the server for the list again with the chosen filters.
// The searching itself happens in ProductController::index (SQL), not here.
function applyFilters() {
    router.get(
        '/products',
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
            active: active.value === '' ? undefined : active.value,
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

// Wait 300 ms after the last keystroke so we do not ask on every letter.
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

watch([categoryId, active], () => {
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
    active.value = '';
    await nextTick();
    skipNextFilterWatch = false;
    applyFilters();
}

// PRD-06: warn when the selling price is lower than the cost price (exact integer cents).
function belowCost(product) {
    const sellingCents = toScaled(product.selling_price, 2);
    const costCents = toScaled(product.cost_price, 2);

    if (sellingCents === null || costCents === null) {
        return false;
    }

    return sellingCents < costCents;
}

// One button flips between deactivate and activate (products are never deleted).
function toggleActive(product) {
    if (togglingId.value !== null) {
        return;
    }

    const action = product.is_active ? 'deactivate' : 'activate';

    router.patch(
        `/products/${product.id}/${action}`,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                togglingId.value = product.id;
            },
            onFinish: () => {
                togglingId.value = null;
            },
        },
    );
}

const categoryOptions = computed(() =>
    (props.categories ?? []).map((category) => ({
        value: category.id,
        label: category.name,
    })),
);

const statusOptions = [
    { value: '1', label: 'Active only' },
    { value: '0', label: 'Inactive only' },
];

const columns = computed(() => {
    const base = [
        { key: 'sku', label: 'SKU' },
        { key: 'name', label: 'Name' },
        { key: 'category', label: 'Category' },
        { key: 'cost_price', label: 'Cost', align: 'right' },
        { key: 'selling_price', label: 'Price', align: 'right' },
        { key: 'status', label: 'Status' },
    ];

    if (canManage.value) {
        base.push({ key: 'actions', label: 'Actions', align: 'right', srOnly: true });
    }

    return base;
});
</script>

<template>
    <Head title="Products" />

    <div>
        <PageHeader title="Products" :subtitle="`${products.total} product(s)`">
            <template v-if="canManage" #actions>
                <AppButton href="/products/create">
                    <template #icon>
                        <NavIcon name="new-sale" />
                    </template>
                    New product
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
                    />
                </FormField>

                <FormField for-id="category" label="Category" label-tone="compact">
                    <SelectInput
                        v-model="categoryId"
                        :options="categoryOptions"
                        placeholder="All categories"
                    />
                </FormField>

                <FormField for-id="status" label="Status" label-tone="compact">
                    <SelectInput
                        v-model="active"
                        :options="statusOptions"
                        placeholder="Active and inactive"
                    />
                </FormField>
            </div>

            <template v-if="hasActiveFilters || isFiltering" #footer>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p role="status" class="text-xs text-ink-muted">
                        <span v-if="isFiltering">Updating products list…</span>
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
            v-if="pageErrors.length"
            :messages="pageErrors"
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
                    <span v-if="item.unit?.symbol" class="ml-1.5 text-xs font-normal text-ink-muted">
                        ({{ item.unit.symbol }})
                    </span>
                </template>

                <template #cell-category="{ item }">
                    {{ item.category?.name ?? '—' }}
                </template>

                <template #cell-cost_price="{ item }">
                    <span class="whitespace-nowrap text-ink-muted">
                        {{ formatMoney(item.cost_price) }}
                    </span>
                </template>

                <template #cell-selling_price="{ item }">
                    <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                        <span class="whitespace-nowrap font-medium text-ink">
                            {{ formatMoney(item.selling_price) }}
                        </span>
                        <span
                            v-if="belowCost(item)"
                            class="inline-flex items-center whitespace-nowrap rounded-sm border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                        >
                            Below cost
                        </span>
                    </div>
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge :status="item.is_active ? 'active' : 'inactive'" class="whitespace-nowrap" />
                </template>

                <template v-if="canManage" #cell-actions="{ item }">
                    <div class="-my-1.5 flex items-center justify-end gap-1">
                        <AppButton
                            :href="`/products/${item.id}/edit`"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Edit ${item.name}`"
                        >
                            Edit
                        </AppButton>
                        <AppButton
                            variant="ghost"
                            size="sm"
                            :aria-label="`${item.is_active ? 'Deactivate' : 'Activate'} ${item.name}`"
                            :disabled="togglingId !== null"
                            :loading="togglingId === item.id"
                            @click="toggleActive(item)"
                        >
                            {{ item.is_active ? 'Deactivate' : 'Activate' }}
                        </AppButton>
                    </div>
                </template>

                <template #mobile-card="{ item }">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">
                                {{ item.name }}
                                <span v-if="item.unit?.symbol" class="ml-1 text-xs font-normal text-ink-muted">
                                    ({{ item.unit.symbol }})
                                </span>
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-ink-muted">
                                {{ item.sku }}
                            </p>
                        </div>
                        <StatusBadge :status="item.is_active ? 'active' : 'inactive'" class="shrink-0 whitespace-nowrap" />
                    </div>

                    <p class="mt-2 text-sm text-ink-muted">
                        {{ item.category?.name ?? '—' }}
                    </p>

                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm tabular-nums">
                        <span class="font-medium text-ink">
                            {{ formatMoney(item.selling_price) }}
                        </span>
                        <span class="text-xs text-ink-muted">
                            Cost: {{ formatMoney(item.cost_price) }}
                        </span>
                        <span
                            v-if="belowCost(item)"
                            class="inline-flex items-center whitespace-nowrap rounded-sm border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                        >
                            Below cost
                        </span>
                    </div>

                    <div v-if="canManage" class="mt-3 flex gap-2 border-t border-border-subtle pt-3">
                        <AppButton
                            :href="`/products/${item.id}/edit`"
                            variant="secondary"
                            size="sm"
                            block
                            :aria-label="`Edit ${item.name}`"
                        >
                            Edit
                        </AppButton>
                        <AppButton
                            variant="secondary"
                            size="sm"
                            block
                            :aria-label="`${item.is_active ? 'Deactivate' : 'Activate'} ${item.name}`"
                            :disabled="togglingId !== null"
                            :loading="togglingId === item.id"
                            @click="toggleActive(item)"
                        >
                            {{ item.is_active ? 'Deactivate' : 'Activate' }}
                        </AppButton>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        title="No products found."
                        :description="
                            hasActiveFilters
                                ? 'No products match the selected filters.'
                                : canManage
                                    ? 'Create a product to get started.'
                                    : ''
                        "
                        icon="products"
                    >
                        <template v-if="hasActiveFilters || canManage" #actions>
                            <AppButton
                                v-if="hasActiveFilters"
                                variant="secondary"
                                size="sm"
                                @click="clearFilters"
                            >
                                Clear filters
                            </AppButton>
                            <AppButton v-else-if="canManage" href="/products/create" size="sm">
                                New product
                            </AppButton>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>
        </div>

        <PagerLinks :meta="products" />
    </div>
</template>
