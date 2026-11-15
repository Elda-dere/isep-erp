<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

const props = defineProps({
    suppliers: {
        type: Object, // Laravel paginator: .data holds rows, each with purchase_orders_count
        required: true,
    },
});

const page = usePage();
const deleteError = computed(() => page.props.errors?.delete ?? null);

const columns = [
    { key: 'name', label: 'Supplier name' },
    { key: 'phone', label: 'Phone' },
    { key: 'email', label: 'Email' },
    { key: 'purchase_orders_count', label: 'Orders', align: 'right' },
    { key: 'actions', label: 'Actions', srOnly: true, align: 'right' },
];

const subtitle = computed(() => {
    const count = props.suppliers?.total ?? props.suppliers?.data?.length ?? 0;
    return `${count} supplier${count === 1 ? '' : 's'}`;
});

function remove(supplier) {
    if (!confirm(`Delete the supplier "${supplier.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/suppliers/${supplier.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Suppliers" />

    <div class="space-y-6">
        <PageHeader
            title="Suppliers"
            :subtitle="subtitle"
        >
            <template #actions>
                <AppButton
                    href="/suppliers/create"
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
                    <span>New supplier</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="deleteError"
            :message="deleteError"
            title="Cannot delete supplier"
        />

        <DataTable
            :items="suppliers.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-name="{ item }">
                <div class="min-w-0">
                    <p class="font-medium text-ink">{{ item.name }}</p>
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

            <template #cell-purchase_orders_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.purchase_orders_count }}</span>
            </template>

            <template #cell-actions="{ item }">
                <div class="-my-1.5 flex items-center justify-end gap-1.5">
                    <AppButton
                        :href="`/suppliers/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="item.purchase_orders_count === 0"
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
                        title="Suppliers with purchase orders cannot be deleted"
                    >
                        In use
                    </span>
                </div>
            </template>

            <template #mobile-card="{ item }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {{ item.name }}
                        </p>
                        <p v-if="item.address" class="mt-0.5 text-xs text-ink-muted">
                            {{ item.address }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center rounded-sm border border-border bg-stone px-2 py-0.5 text-xs font-medium text-ink tabular-nums">
                            {{ item.purchase_orders_count }} {{ item.purchase_orders_count === 1 ? 'order' : 'orders' }}
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
                        :href="`/suppliers/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="item.purchase_orders_count === 0"
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
                        In use
                    </span>
                </div>
            </template>

            <template #empty>
                <EmptyState
                    icon="suppliers"
                    title="No suppliers yet"
                    description="No procurement suppliers have been added to the system."
                >
                    <template #actions>
                        <AppButton
                            href="/suppliers/create"
                            variant="primary"
                        >
                            New supplier
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="suppliers" />
    </div>
</template>
