<script setup>
import NavIcon from './NavIcon.vue';

defineProps({
    title: {
        type: String,
        default: 'No records found.',
    },
    description: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="rounded border border-dashed border-border bg-surface text-center"
        :class="compact ? 'px-4 py-6' : 'px-6 py-10'"
    >
        <div
            v-if="icon"
            class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-stone text-ink-muted"
        >
            <NavIcon :name="icon" class-name="h-5 w-5 shrink-0 text-ink-muted" />
        </div>

        <p class="text-sm font-medium text-ink">
            {{ title }}
        </p>

        <p v-if="description || $slots.default" class="mx-auto mt-1 max-w-md text-sm text-ink-muted">
            <slot>{{ description }}</slot>
        </p>

        <div v-if="$slots.actions" class="mt-4 flex flex-wrap items-center justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
