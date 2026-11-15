<script setup>
import { computed } from 'vue';

const props = defineProps({
    as: {
        type: String,
        default: 'section',
        validator: (value) => ['section', 'div', 'form', 'article'].includes(value),
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    padding: {
        type: String,
        default: 'md',
        validator: (value) => ['none', 'sm', 'md'].includes(value),
    },
});

const bodyPaddingClass = computed(() => {
    if (props.padding === 'none') {
        return '';
    }
    if (props.padding === 'sm') {
        return 'p-3 sm:p-4';
    }
    return 'p-4 sm:p-5';
});
</script>

<template>
    <component
        :is="as"
        class="rounded border border-border bg-surface shadow-xs"
    >
        <div
            v-if="title || description || $slots.header || $slots.actions"
            class="flex flex-col gap-2 border-b border-border-subtle px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h2 v-if="title" class="text-base font-semibold text-ink">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="mt-0.5 text-sm text-ink-muted">
                        {{ description }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div :class="bodyPaddingClass">
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="border-t border-border-subtle bg-stone/40 px-4 py-3 sm:px-5"
        >
            <slot name="footer" />
        </div>
    </component>
</template>
