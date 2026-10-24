<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Shows the one-time "success" message the server sends after a save.
// It reads page.props.flash.success, which HandleInertiaRequests must share.
const page = usePage();
const message = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <!-- role="status" lets screen readers announce the message (WCAG) -->
    <p
        v-if="message"
        role="status"
        class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
    >
        {{ message }}
    </p>
</template>