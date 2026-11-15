<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    meta: Object,
});
</script>

<template>
    <nav
        v-if="meta && (meta.prev_page_url || meta.next_page_url || meta.last_page > 1)"
        class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm"
        aria-label="Pagination"
    >
        <div>
            <Link
                v-if="meta.prev_page_url"
                :href="meta.prev_page_url"
                class="inline-flex min-h-11 items-center gap-1.5 rounded border border-border bg-surface px-4 font-medium text-ink hover:bg-stone transition-colors shadow-xs"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M12 15l-5-5 5-5" />
                </svg>
                Previous
            </Link>
            <span
                v-else
                class="inline-flex min-h-11 items-center gap-1.5 rounded border border-border/50 bg-stone/50 px-4 font-medium text-ink-faint cursor-not-allowed"
                aria-disabled="true"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M12 15l-5-5 5-5" />
                </svg>
                Previous
            </span>
        </div>

        <p class="text-xs text-ink-muted font-medium">
            <span v-if="meta.from && meta.to">
                Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
            </span>
            <span v-else>
                Page {{ meta.current_page }} of {{ meta.last_page }}
            </span>
        </p>

        <div>
            <Link
                v-if="meta.next_page_url"
                :href="meta.next_page_url"
                class="inline-flex min-h-11 items-center gap-1.5 rounded border border-border bg-surface px-4 font-medium text-ink hover:bg-stone transition-colors shadow-xs"
            >
                Next
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M8 5l5 5-5 5" />
                </svg>
            </Link>
            <span
                v-else
                class="inline-flex min-h-11 items-center gap-1.5 rounded border border-border/50 bg-stone/50 px-4 font-medium text-ink-faint cursor-not-allowed"
                aria-disabled="true"
            >
                Next
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M8 5l5 5-5 5" />
                </svg>
            </span>
        </div>
    </nav>
</template>
