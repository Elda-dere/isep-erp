<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    customers: {
        type: Object, // a Laravel paginator: .data holds rows with sales_count and is_walk_in
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

// The server sends this message when a delete is refused (a customer with sales, or the Walk-in customer).
const deleteError = computed(() => page.props.errors?.delete ?? null);

// The search box starts with the value from the URL, so a reload keeps it.
const search = ref(props.filters?.search ?? '');

// Wait 300 ms after the last keystroke, then ask the server again.
// The searching itself happens in CustomerController::index (SQL), not here.
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/customers',
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});

function remove(customer) {
    // Destructive actions ask for confirmation (SRS section 6.1).
    if (
        !confirm(
            `Delete the customer "${customer.name}"? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(`/customers/${customer.id}`, { preserveScroll: true });
}

// Instead of a Delete button, say why the customer cannot be deleted. The server checks this again.
function lockReason(customer) {
    if (customer.is_walk_in) return 'Default customer';
    if (customer.sales_count > 0) return 'Has sales';
    return null;
}

const subtitle = computed(() => {
    const count = props.customers?.total ?? props.customers?.data?.length ?? 0;
    return `${count} customer${count === 1 ? '' : 's'}`;
});

const columns = [
    { key: 'name', label: 'Customer name' },
    { key: 'phone', label: 'Phone' },
    { key: 'email', label: 'Email' },
    { key: 'sales_count', label: 'Sales', align: 'right' },
    { key: 'actions', label: 'Actions', srOnly: true, align: 'right' },
];
</script>

<template>
    <Head title="Customers" />

    <div class="space-y-6">
        <PageHeader
            title="Customers"
            :subtitle="subtitle"
        >
            <template #actions>
                <AppButton
                    href="/customers/create"
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
                    <span>New customer</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="deleteError"
            :message="deleteError"
            title="Cannot delete customer"
        />

        <Card padding="md">
            <div class="max-w-md">
                <label for="search" class="sr-only">Search name, phone or email</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-muted">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                            <circle cx="9" cy="9" r="6" />
                            <path d="M13.5 13.5L18 18" stroke-linecap="round" />
                        </svg>
                    </div>
                    <TextInput
                        id="search"
                        v-model="search"
                        type="search"
                        placeholder="Search name, phone or email"
                        class="pl-9"
                    />
                </div>
            </div>
        </Card>

        <DataTable
            :items="customers.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-name="{ item }">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-ink">{{ item.name }}</p>
                        <span
                            v-if="item.is_walk_in"
                            class="inline-flex items-center rounded-sm border border-stone-dark bg-stone px-1.5 py-0.5 text-[11px] font-medium text-ink-muted"
                        >
                            Default
                        </span>
                    </div>
                    <p v-if="item.address" class="mt-0.5 text-xs text-ink-muted line-clamp-1">
                        {{ item.address }}
                    </p>
                </div>
            </template>

            <template #cell-phone="{ item }">
                <span class="text-sm text-ink-muted">{{ item.phone || '—' }}</span>
            </template>

            <template #cell-email="{ item }">
                <span class="text-sm text-ink-muted">{{ item.email || '—' }}</span>
            </template>

            <template #cell-sales_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.sales_count }}</span>
            </template>

            <template #cell-actions="{ item }">
                <div class="-my-1.5 flex items-center justify-end gap-1.5">
                    <AppButton
                        :href="`/customers/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="!lockReason(item)"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-red-700 hover:bg-red-50 hover:text-red-800"
                        :aria-label="`Delete ${item.name}`"
                        @click="remove(item)"
                    >
                        Delete
                    </AppButton>

                    <span
                        v-else
                        class="inline-flex min-h-11 items-center px-2 text-xs font-medium text-ink-faint"
                        :title="item.is_walk_in ? 'Default walk-in customer cannot be deleted' : 'Customers with existing sales cannot be deleted'"
                    >
                        {{ lockReason(item) }}
                    </span>
                </div>
            </template>

            <template #mobile-card="{ item }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-ink">
                                {{ item.name }}
                            </p>
                            <span
                                v-if="item.is_walk_in"
                                class="inline-flex items-center rounded-sm border border-stone-dark bg-stone px-1.5 py-0.5 text-[11px] font-medium text-ink-muted"
                            >
                                Default
                            </span>
                        </div>
                        <p v-if="item.address" class="mt-0.5 text-xs text-ink-muted">
                            {{ item.address }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center rounded-sm border border-border bg-stone px-2 py-0.5 text-xs font-medium text-ink tabular-nums">
                            {{ item.sales_count }} {{ item.sales_count === 1 ? 'sale' : 'sales' }}
                        </span>
                    </div>
                </div>

                <div v-if="item.phone || item.email" class="mt-2 space-y-1 text-xs text-ink-muted">
                    <p v-if="item.phone" class="flex items-center gap-1.5">
                        <span class="font-medium text-ink">Phone:</span>
                        <span>{{ item.phone }}</span>
                    </p>
                    <p v-if="item.email" class="flex items-center gap-1.5">
                        <span class="font-medium text-ink">Email:</span>
                        <span>{{ item.email }}</span>
                    </p>
                </div>

                <div class="mt-3 flex items-center justify-end gap-2 border-t border-border-subtle pt-2.5">
                    <AppButton
                        :href="`/customers/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="!lockReason(item)"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-red-700 hover:bg-red-50 hover:text-red-800"
                        :aria-label="`Delete ${item.name}`"
                        @click="remove(item)"
                    >
                        Delete
                    </AppButton>

                    <span
                        v-else
                        class="inline-flex min-h-11 items-center px-2 text-xs font-medium text-ink-faint"
                    >
                        {{ lockReason(item) }}
                    </span>
                </div>
            </template>

            <template #empty>
                <EmptyState
                    title="No customers found."
                    :description="search ? 'No customers match your search criteria.' : 'Create a customer to get started.'"
                    icon="customers"
                >
                    <template v-if="!search" #actions>
                        <AppButton
                            href="/customers/create"
                            variant="primary"
                            size="sm"
                        >
                            New customer
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="customers" />
    </div>
</template>
