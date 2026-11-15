<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    backHref: {
        type: String,
        default: null,
    },
    backLabel: {
        type: String,
        default: 'Back',
    },
    monoTitle: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <header class="mb-6">
        <div v-if="backHref" class="mb-2">
            <Link
                :href="backHref"
                class="inline-flex min-h-11 items-center gap-1.5 rounded px-1 -ml-1 text-sm font-medium text-ink-muted hover:text-forest transition-colors"
            >
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12.5 15l-5-5 5-5" />
                </svg>
                <span>{{ backLabel }}</span>
            </Link>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-ink"
                        :class="{ 'font-mono': monoTitle }"
                    >
                        {{ title }}
                    </h1>
                    <slot name="title-meta" />
                </div>

                <p v-if="subtitle || $slots.subtitle" class="mt-1 text-sm text-ink-muted">
                    <slot name="subtitle">{{ subtitle }}</slot>
                </p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
