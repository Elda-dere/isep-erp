<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    meta: Object,
});
</script>

<template>
    <nav class="mt-4 flex items-center justify-between" aria-label="Pagination">
        <Link v-if="meta.prev_page_url" :href="meta.prev_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Previous</Link>
        <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Previous</span>

        <span class="text-sm">Page {{ meta.current_page }} of {{ meta.last_page }}</span>

        <Link v-if="meta.next_page_url" :href="meta.next_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Next</Link>
        <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Next</span>
    </nav>
</template>