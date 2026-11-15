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
    units: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canManage = computed(() => Boolean(page.props.auth?.permissions?.includes('products.manage')));
const deleteError = computed(() => page.props.errors?.delete ?? null);

const columns = computed(() => {
    const list = [
        { key: 'name', label: 'Unit name' },
        { key: 'symbol', label: 'Symbol' },
        { key: 'products_count', label: 'Products', align: 'right' },
    ];

    if (canManage.value) {
        list.push({ key: 'actions', label: 'Actions', srOnly: true, align: 'right' });
    }

    return list;
});

const subtitle = computed(() => {
    const count = props.units?.total ?? props.units?.data?.length ?? 0;
    return `${count} unit${count === 1 ? '' : 's'} of measure`;
});

function remove(unit) {
    if (!confirm(`Delete the unit "${unit.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/units/${unit.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Units" />

    <div class="space-y-6">
        <PageHeader
            title="Units of measure"
            :subtitle="subtitle"
        >
            <template v-if="canManage" #actions>
                <AppButton
                    href="/units/create"
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
                    <span>New unit</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="deleteError"
            :message="deleteError"
            title="Cannot delete unit"
        />

        <DataTable
            :items="units.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-name="{ item }">
                <span class="font-medium text-ink">{{ item.name }}</span>
            </template>

            <template #cell-symbol="{ item }">
                <span class="font-mono text-sm font-medium text-ink bg-stone/60 px-2 py-0.5 rounded border border-border-subtle">
                    {{ item.symbol }}
                </span>
            </template>

            <template #cell-products_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.products_count }}</span>
            </template>

            <template #cell-actions="{ item }">
                <div v-if="canManage" class="-my-1.5 flex items-center justify-end gap-1.5">
                    <AppButton
                        :href="`/units/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="item.products_count === 0"
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
                        title="Units assigned to products cannot be deleted"
                    >
                        In use
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
                            <span class="font-mono text-xs font-medium text-ink bg-stone/60 px-1.5 py-0.5 rounded border border-border-subtle">
                                {{ item.symbol }}
                            </span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center rounded-sm border border-border bg-stone px-2 py-0.5 text-xs font-medium text-ink tabular-nums">
                            {{ item.products_count }} {{ item.products_count === 1 ? 'product' : 'products' }}
                        </span>
                    </div>
                </div>

                <div v-if="canManage" class="mt-3 flex items-center justify-end gap-2 border-t border-border-subtle pt-2.5">
                    <AppButton
                        :href="`/units/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>

                    <AppButton
                        v-if="item.products_count === 0"
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
                    icon="units"
                    title="No units yet"
                    description="No units of measure have been configured in the catalogue."
                >
                    <template v-if="canManage" #actions>
                        <AppButton
                            href="/units/create"
                            variant="primary"
                        >
                            New unit
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="units" />
    </div>
</template>
