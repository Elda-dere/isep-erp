<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

defineProps({
    units: Object, // a Laravel paginator: .data holds the rows, each with products_count
});

const page = usePage();

// Buttons are only a convenience: the server checks products.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('products.manage'));

// The server sends this message when a delete is refused (for example, a unit in use).
const deleteError = computed(() => page.props.errors?.delete ?? null);

function remove(unit) {
    // Destructive actions ask for confirmation (SRS section 6.1).
    if (!confirm(`Delete the unit "${unit.name}"? This cannot be undone.`)) {
        return;
    }

    router.delete(`/units/${unit.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Units" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Units of measure</h1>
                <p class="mt-1 text-sm text-gray-500">{{ units.total }} unit(s)</p>
            </div>
            <Link
                v-if="canManage"
                href="/units/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New unit
            </Link>
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="deleteError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ deleteError }}
        </p>

        <p v-if="units.data.length === 0" class="mt-8 text-center text-gray-500">No units yet.</p>

        <ul v-else class="mt-4 divide-y rounded border bg-white">
            <li v-for="unit in units.data" :key="unit.id" class="flex flex-wrap items-center justify-between gap-3 p-3">
                <div>
                    <p class="font-medium">{{ unit.name }} <span class="font-mono text-sm text-gray-500">({{ unit.symbol }})</span></p>
                    <p class="text-xs text-gray-500">{{ unit.products_count }} product(s)</p>
                </div>

                <div v-if="canManage" class="flex gap-2">
                    <Link
                        :href="`/units/${unit.id}/edit`"
                        :aria-label="`Edit ${unit.name}`"
                        class="flex min-h-11 items-center rounded border px-4"
                    >
                        Edit
                    </Link>
                    <!-- Only unused units can be deleted. The server checks this again. -->
                    <button
                        v-if="unit.products_count === 0"
                        type="button"
                        :aria-label="`Delete ${unit.name}`"
                        class="flex min-h-11 items-center rounded border border-red-300 px-4 text-red-700"
                        @click="remove(unit)"
                    >
                        Delete
                    </button>
                    <span v-else class="flex min-h-11 items-center px-2 text-sm text-gray-400">In use</span>
                </div>
            </li>
        </ul>

        <PagerLinks :meta="units" />
    </div>
</template>