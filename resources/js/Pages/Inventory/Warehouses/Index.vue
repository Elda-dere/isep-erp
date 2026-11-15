<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

const props = defineProps({
    warehouses: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canManage = computed(() => Boolean(page.props.auth?.permissions?.includes('warehouses.manage')));

const columns = computed(() => {
    const list = [
        { key: 'name', label: 'Warehouse name' },
        { key: 'location', label: 'Location' },
    ];

    if (canManage.value) {
        list.push({ key: 'actions', label: 'Actions', srOnly: true, align: 'right' });
    }

    return list;
});

const subtitle = computed(() => {
    const count = props.warehouses?.total ?? props.warehouses?.data?.length ?? 0;
    return count === 1 ? '1 warehouse location' : `${count} warehouse locations`;
});
</script>

<template>
    <Head title="Warehouses" />

    <div class="space-y-6">
        <PageHeader
            title="Warehouses"
            :subtitle="subtitle"
        >
            <template v-if="canManage" #actions>
                <AppButton
                    href="/warehouses/create"
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
                    <span>New warehouse</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <DataTable
            :items="warehouses.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-name="{ item }">
                <span class="font-medium text-ink">{{ item.name }}</span>
            </template>

            <template #cell-location="{ item }">
                <span class="text-ink-muted">{{ item.location || '—' }}</span>
            </template>

            <template #cell-actions="{ item }">
                <div v-if="canManage" class="-my-1.5 flex items-center justify-end">
                    <AppButton
                        :href="`/warehouses/${item.id}/edit`"
                        variant="ghost"
                        size="sm"
                        :aria-label="`Edit ${item.name}`"
                    >
                        Edit
                    </AppButton>
                </div>
            </template>

            <template #mobile-card="{ item }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink">
                            {{ item.name }}
                        </p>
                        <p class="mt-1 text-sm text-ink-muted">
                            <span class="font-medium text-ink-faint">Location:</span>
                            <span class="ml-1 text-ink">{{ item.location || '—' }}</span>
                        </p>
                    </div>

                    <div v-if="canManage" class="shrink-0">
                        <AppButton
                            :href="`/warehouses/${item.id}/edit`"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Edit ${item.name}`"
                        >
                            Edit
                        </AppButton>
                    </div>
                </div>
            </template>

            <template #empty>
                <EmptyState
                    icon="warehouses"
                    title="No warehouses yet"
                    description="No warehouse locations have been configured in the system."
                >
                    <template v-if="canManage" #actions>
                        <AppButton
                            href="/warehouses/create"
                            variant="primary"
                        >
                            New warehouse
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="warehouses" />
    </div>
</template>
