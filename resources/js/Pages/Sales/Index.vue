<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import Card from '../../Components/Card.vue';
import DataTable from '../../Components/DataTable.vue';
import EmptyState from '../../Components/EmptyState.vue';
import ErrorAlert from '../../Components/ErrorAlert.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import FormField from '../../Components/FormField.vue';
import NavIcon from '../../Components/NavIcon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PagerLinks from '../../Components/PagerLinks.vue';
import SelectInput from '../../Components/SelectInput.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import TextInput from '../../Components/TextInput.vue';
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
const filterErrors = computed(() =>
    [
        errors.value.status,
        errors.value.customer_id,
        errors.value.created_by,
        errors.value.from,
        errors.value.to,
    ].filter(Boolean),
);

// The filter boxes start with the values from the URL, so a reload keeps them.
const status = ref(props.filters?.status ?? '');
const customerId = ref(props.filters?.customer_id ?? '');
const createdBy = ref(props.filters?.created_by ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');
const isFiltering = ref(false);

const hasActiveFilters = computed(() =>
    Boolean(status.value || customerId.value || createdBy.value || from.value || to.value),
);

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

function clearFilters() {
    status.value = '';
    customerId.value = '';
    createdBy.value = '';
    from.value = '';
    to.value = '';
}

watch([status, customerId, createdBy, from, to], applyFilters);

const statusNames = { pending: 'Pending payment', paid: 'Paid', cancelled: 'Cancelled' };

const statusOptions = computed(() =>
    (props.statuses ?? []).map((option) => ({
        value: option,
        label: statusNames[option] ?? option,
    })),
);

const customerOptions = computed(() =>
    (props.customers ?? []).map((customer) => ({
        value: customer.id,
        label: customer.name,
    })),
);

const cashierOptions = computed(() =>
    (props.cashiers ?? []).map((cashier) => ({
        value: cashier.id,
        label: cashier.name,
    })),
);

const columns = [
    { key: 'sale_number', label: 'Sale' },
    { key: 'customer', label: 'Customer' },
    { key: 'status', label: 'Status' },
    { key: 'lines_count', label: 'Lines', align: 'right' },
    { key: 'total_amount', label: 'Total', align: 'right' },
    { key: 'created_by', label: 'Cashier' },
    { key: 'created_at', label: 'Date' },
];
</script>

<template>
    <Head title="Sales" />

    <div>
        <PageHeader title="Sales" :subtitle="`${sales.total} sale(s)`">
            <template v-if="canCreate" #actions>
                <AppButton href="/sales/create">
                    <template #icon>
                        <NavIcon name="new-sale" />
                    </template>
                    New sale
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <!-- Filters. Labels are visible because the date boxes need them. -->
        <Card padding="sm" class="mb-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <FormField for-id="status" label="Status" label-tone="compact">
                    <SelectInput
                        v-model="status"
                        :options="statusOptions"
                        placeholder="All statuses"
                        :invalid="Boolean(errors.status)"
                    />
                </FormField>

                <FormField for-id="customer" label="Customer" label-tone="compact">
                    <SelectInput
                        v-model="customerId"
                        :options="customerOptions"
                        placeholder="All customers"
                        :invalid="Boolean(errors.customer_id)"
                    />
                </FormField>

                <FormField for-id="cashier" label="Cashier" label-tone="compact">
                    <SelectInput
                        v-model="createdBy"
                        :options="cashierOptions"
                        placeholder="All cashiers"
                        :invalid="Boolean(errors.created_by)"
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
                        <span v-if="isFiltering">Updating sales list…</span>
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
                :items="sales.data"
                :columns="columns"
                breakpoint="sm"
                :mobile-href="(sale) => `/sales/${sale.id}`"
            >
                <template #cell-sale_number="{ item }">
                    <Link
                        :href="`/sales/${item.id}`"
                        class="-my-2 inline-flex min-h-11 items-center font-mono font-medium text-forest underline decoration-forest/30 underline-offset-2 hover:text-forest-hover hover:decoration-forest"
                    >
                        {{ item.sale_number }}
                    </Link>
                </template>

                <template #cell-customer="{ item }">
                    {{ item.customer ?? '—' }}
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge :status="item.status" />
                </template>

                <template #cell-total_amount="{ item }">
                    <span class="font-medium text-ink">
                        {{ formatMoney(item.total_amount, item.currency) }}
                    </span>
                </template>

                <template #cell-created_by="{ item }">
                    <span class="text-ink-muted">
                        {{ item.created_by ?? '—' }}
                    </span>
                </template>

                <template #cell-created_at="{ item }">
                    <span class="whitespace-nowrap text-ink-muted">
                        {{ formatDateTime(item.created_at) }}
                    </span>
                </template>

                <template #mobile-card="{ item }">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-mono text-sm font-semibold text-forest">
                            {{ item.sale_number }}
                        </p>
                        <StatusBadge :status="item.status" />
                    </div>
                    <p class="mt-1.5 text-sm font-medium text-ink">
                        {{ item.customer ?? '—' }}
                    </p>
                    <p class="mt-1 text-sm font-medium text-ink tabular-nums">
                        {{ formatMoney(item.total_amount, item.currency) }}
                        <span class="font-normal text-ink-muted">
                            · {{ item.lines_count }} line(s)
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-ink-muted">
                        {{ item.created_by ?? '—' }} · {{ formatDateTime(item.created_at) }}
                    </p>
                </template>

                <template #empty>
                    <EmptyState
                        title="No sales found."
                        :description="
                            hasActiveFilters
                                ? 'No sales match the selected filters.'
                                : canCreate
                                    ? 'Create a sale to get started.'
                                    : ''
                        "
                        icon="sales"
                    >
                        <template v-if="hasActiveFilters || canCreate" #actions>
                            <AppButton
                                v-if="hasActiveFilters"
                                variant="secondary"
                                size="sm"
                                @click="clearFilters"
                            >
                                Clear filters
                            </AppButton>
                            <AppButton v-else-if="canCreate" href="/sales/create" size="sm">
                                New sale
                            </AppButton>
                        </template>
                    </EmptyState>
                </template>
            </DataTable>
        </div>

        <PagerLinks :meta="sales" />
    </div>
</template>
