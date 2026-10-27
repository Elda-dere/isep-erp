<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

defineProps({
    categories: Object, // a Laravel paginator: .data holds the rows, each with products_count
});

const page = usePage();

// Buttons are only a convenience: the server checks products.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('products.manage'));

// The server sends this message when a delete is refused (for example, a category in use).
const deleteError = computed(() => page.props.errors?.delete ?? null);

function remove(category) {
    // Destructive actions ask for confirmation (SRS section 6.1).
    if (!confirm(`Delete the category "${category.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/categories/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Categories" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Categories</h1>
                <p class="mt-1 text-sm text-gray-500">{{ categories.total }} categor{{ categories.total === 1 ? 'y' : 'ies' }}</p>
            </div>
            <Link
                v-if="canManage"
                href="/categories/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New category
            </Link>
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="deleteError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ deleteError }}
        </p>

        <p v-if="categories.data.length === 0" class="mt-8 text-center text-gray-500">No categories yet.</p>

        <!-- Few fields, so one list works on every screen size -->
        <ul v-else class="mt-4 divide-y rounded border bg-white">
            <li v-for="category in categories.data" :key="category.id" class="flex flex-wrap items-center justify-between gap-3 p-3">
                <div>
                    <p class="font-medium">{{ category.name }}</p>
                    <p v-if="category.description" class="text-sm text-gray-500">{{ category.description }}</p>
                    <p class="text-xs text-gray-500">{{ category.products_count }} product(s)</p>
                </div>

                <div v-if="canManage" class="flex gap-2">
                    <Link
                        :href="`/categories/${category.id}/edit`"
                        :aria-label="`Edit ${category.name}`"
                        class="flex min-h-11 items-center rounded border px-4"
                    >
                        Edit
                    </Link>
                    <!-- Only unused categories can be deleted. The server checks this again. -->
                    <button
                        v-if="category.products_count === 0"
                        type="button"
                        :aria-label="`Delete ${category.name}`"
                        class="flex min-h-11 items-center rounded border border-red-300 px-4 text-red-700"
                        @click="remove(category)"
                    >
                        Delete
                    </button>
                    <span v-else class="flex min-h-11 items-center px-2 text-sm text-gray-400">In use</span>
                </div>
            </li>
        </ul>

        <PagerLinks :meta="categories" />
    </div>
</template>