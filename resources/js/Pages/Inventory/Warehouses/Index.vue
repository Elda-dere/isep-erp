<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PagerLinks from '../../../Components/PagerLinks.vue';

defineProps({
    warehouses: Object, // a Laravel paginator: .data holds the rows
});

const page = usePage();

// The button is only a convenience: the server checks warehouses.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('warehouses.manage'));
</script>

<template>
    <Head title="Warehouses" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Warehouses</h1>
                <p class="mt-1 text-sm text-gray-500">{{ warehouses.total }} warehouse(s)</p>
            </div>
            <Link
                v-if="canManage"
                href="/warehouses/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New warehouse
            </Link>
        </div>

        <FlashMessage class="mt-4" />

        <p v-if="warehouses.data.length === 0" class="mt-8 text-center text-gray-500">No warehouses yet.</p>

        <!-- Only two fields, so one list works on every screen size (no table/cards switch needed) -->
        <ul v-else class="mt-4 divide-y rounded border bg-white">
            <li v-for="warehouse in warehouses.data" :key="warehouse.id" class="flex items-center justify-between gap-3 p-3">
                <div>
                    <p class="font-medium">{{ warehouse.name }}</p>
                    <p class="text-sm text-gray-500">{{ warehouse.location || 'No location' }}</p>
                </div>
                <Link
                    v-if="canManage"
                    :href="`/warehouses/${warehouse.id}/edit`"
                    :aria-label="`Edit ${warehouse.name}`"
                    class="flex min-h-11 items-center rounded border px-4"
                >
                    Edit
                </Link>
            </li>
        </ul>

        <PagerLinks :meta="warehouses" />
    </div>
</template>