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
    categories: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const canManage = computed(() => Boolean(page.props.auth?.permissions?.includes('products.manage')));
const deleteError = computed(() => page.props.errors?.delete ?? null);

const columns = computed(() => {
    const list = [
        { key: 'name', label: 'Category name' },
        { key: 'description', label: 'Description' },
        { key: 'products_count', label: 'Products', align: 'right' },
    ];

    if (canManage.value) {
        list.push({ key: 'actions', label: 'Actions', srOnly: true, align: 'right' });
    }

    return list;
});

const subtitle = computed(() => {
    const count = props.categories?.total ?? props.categories?.data?.length ?? 0;
    return `${count} categor${count === 1 ? 'y' : 'ies'}`;
});

function remove(category) {
    if (!confirm(`Delete the category "${category.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Categories" />

    <div class="space-y-6">
        <PageHeader
            title="Categories"
            :subtitle="subtitle"
        >
            <template v-if="canManage" #actions>
                <AppButton
                    href="/categories/create"
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
                    <span>New category</span>
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="deleteError"
            :message="deleteError"
            title="Cannot delete category"
        />

        <DataTable
            :items="categories.data"
            :columns="columns"
            breakpoint="sm"
        >
            <template #cell-name="{ item }">
                <span class="font-medium text-ink">{{ item.name }}</span>
            </template>

            <template #cell-description="{ item }">
                <span class="text-sm text-ink-muted">{{ item.description || '—' }}</span>
            </template>

            <template #cell-products_count="{ item }">
                <span class="font-mono text-sm tabular-nums text-ink">{{ item.products_count }}</span>
            </template>

            <template #cell-actions="{ item }">
                <div v-if="canManage" class="-my-1.5 flex items-center justify-end gap-1.5">
                    <AppButton
                        :href="`/categories/${item.id}/edit`"
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
                        title="Categories assigned to products cannot be deleted"
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
                        <p v-if="item.description" class="mt-1 text-sm text-ink-muted">
                            {{ item.description }}
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center rounded-sm border border-border bg-stone px-2 py-0.5 text-xs font-medium text-ink tabular-nums">
                            {{ item.products_count }} {{ item.products_count === 1 ? 'product' : 'products' }}
                        </span>
                    </div>
                </div>

                <div v-if="canManage" class="mt-3 flex items-center justify-end gap-2 border-t border-border-subtle pt-2.5">
                    <AppButton
                        :href="`/categories/${item.id}/edit`"
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
                    icon="categories"
                    title="No categories yet"
                    description="No product categories have been added to the catalogue."
                >
                    <template v-if="canManage" #actions>
                        <AppButton
                            href="/categories/create"
                            variant="primary"
                        >
                            New category
                        </AppButton>
                    </template>
                </EmptyState>
            </template>
        </DataTable>

        <PagerLinks :meta="categories" />
    </div>
</template>
