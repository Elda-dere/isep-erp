<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Shows the one-time "success" message the server sends after a save.
// It reads page.props.flash.success, which HandleInertiaRequests shares.
const page = usePage();
const message = computed(() => page.props.flash?.success ?? null);
const dismissed = ref(false);

watch(message, () => {
    dismissed.value = false;
});
</script>

<template>
    <!-- role="status" lets screen readers announce the message (WCAG) -->
    <div
        v-if="message && !dismissed"
        role="status"
        class="mb-4 flex items-center justify-between gap-3 rounded border border-sage/40 bg-soft-green pl-4 pr-2 py-2.5 text-sm text-forest shadow-xs"
    >
        <div class="flex items-start gap-2.5 min-w-0">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-forest" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span class="font-medium leading-relaxed break-words">{{ message }}</span>
        </div>
        <button
            type="button"
            class="h-11 w-11 shrink-0 rounded flex items-center justify-center text-forest/70 hover:text-forest hover:bg-forest/10 transition-colors"
            aria-label="Dismiss message"
            @click="dismissed = true"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>
    </div>
</template>
