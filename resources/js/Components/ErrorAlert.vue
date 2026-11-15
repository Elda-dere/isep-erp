<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: String,
        default: '',
    },
    messages: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'danger',
        validator: (value) => ['danger', 'warning'].includes(value),
    },
    dismissible: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['dismiss']);

const normalizedMessages = computed(() => {
    const items = [];
    if (props.message) {
        items.push(props.message);
    }
    for (const item of props.messages) {
        if (item && !items.includes(item)) {
            items.push(item);
        }
    }
    return items;
});

const containerClasses = computed(() => {
    if (props.tone === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }
    return 'border-red-200 bg-red-50 text-red-800';
});

const iconColorClass = computed(() => (props.tone === 'warning' ? 'text-amber-700' : 'text-red-700'));
const dismissButtonClass = computed(() =>
    props.tone === 'warning'
        ? 'text-amber-800/70 hover:text-amber-900 hover:bg-amber-900/10'
        : 'text-red-800/70 hover:text-red-900 hover:bg-red-900/10',
);
</script>

<template>
    <div
        v-if="normalizedMessages.length > 0 || title || $slots.default"
        role="alert"
        class="flex items-start justify-between gap-3 rounded border px-4 py-3 text-sm shadow-xs"
        :class="containerClasses"
    >
        <div class="flex min-w-0 items-start gap-2.5">
            <svg
                class="mt-0.5 h-4 w-4 shrink-0"
                :class="iconColorClass"
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="1.75"
                aria-hidden="true"
            >
                <circle cx="10" cy="10" r="7.25" />
                <path stroke-linecap="round" d="M10 6.5v4.25" />
                <circle cx="10" cy="13.5" r="0.85" fill="currentColor" stroke="none" />
            </svg>

            <div class="min-w-0 leading-relaxed break-words">
                <p v-if="title" class="font-semibold">
                    {{ title }}
                </p>

                <slot>
                    <p v-if="normalizedMessages.length === 1" :class="{ 'mt-0.5': title }">
                        {{ normalizedMessages[0] }}
                    </p>
                    <ul
                        v-else-if="normalizedMessages.length > 1"
                        class="list-disc space-y-1 pl-4"
                        :class="{ 'mt-1': title }"
                    >
                        <li v-for="(item, index) in normalizedMessages" :key="index">
                            {{ item }}
                        </li>
                    </ul>
                </slot>
            </div>
        </div>

        <button
            v-if="dismissible"
            type="button"
            class="flex h-11 w-11 -my-1.5 -mr-2 shrink-0 items-center justify-center rounded transition-colors"
            :class="dismissButtonClass"
            aria-label="Dismiss alert"
            @click="emit('dismiss')"
        >
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 5l10 10M15 5L5 15" />
            </svg>
        </button>
    </div>
</template>
