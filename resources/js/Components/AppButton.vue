<script setup>
import { computed, useAttrs } from 'vue';
import { Link } from '@inertiajs/vue3';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'danger', 'ghost'].includes(value),
    },
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['md', 'sm'].includes(value),
    },
    type: {
        type: String,
        default: 'button',
        validator: (value) => ['button', 'submit', 'reset'].includes(value),
    },
    href: {
        type: String,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    block: {
        type: Boolean,
        default: false,
    },
});

const attrs = useAttrs();

const isInteractiveBlocked = computed(() => props.disabled || props.loading);
const isLink = computed(() => Boolean(props.href) && !isInteractiveBlocked.value);
const isDisabledLink = computed(() => Boolean(props.href) && isInteractiveBlocked.value);

const passThroughAttrs = computed(() => {
    const { class: _class, onClick, href: _href, type: _type, disabled: _disabled, ...rest } = attrs;
    return isInteractiveBlocked.value ? rest : { ...rest, ...(onClick ? { onClick } : {}) };
});

const buttonClasses = computed(() => {
    const sizeClasses = props.size === 'sm' ? 'min-h-11 px-3 text-sm' : 'min-h-11 px-4 text-sm';

    const variantMap = {
        primary: [
            'bg-forest text-white shadow-xs',
            !isInteractiveBlocked.value && 'hover:bg-forest-hover active:bg-forest-dark',
        ],
        secondary: [
            'border border-border bg-surface text-ink shadow-xs',
            !isInteractiveBlocked.value && 'hover:bg-stone',
        ],
        danger: [
            'border border-red-300 bg-surface text-red-700',
            !isInteractiveBlocked.value && 'hover:bg-red-50',
        ],
        ghost: [
            'text-ink-muted',
            !isInteractiveBlocked.value && 'hover:bg-stone hover:text-ink',
        ],
    };

    return [
        'inline-flex items-center justify-center gap-2 rounded font-medium text-center transition-colors select-none sm:whitespace-nowrap',
        sizeClasses,
        variantMap[props.variant],
        props.block && 'w-full',
        isInteractiveBlocked.value && 'opacity-50 cursor-not-allowed',
        attrs.class,
    ];
});
</script>

<template>
    <Link
        v-if="isLink"
        :href="href"
        :class="buttonClasses"
        v-bind="passThroughAttrs"
    >
        <slot name="icon" />
        <slot />
    </Link>

    <span
        v-else-if="isDisabledLink"
        role="link"
        aria-disabled="true"
        :aria-busy="loading ? 'true' : undefined"
        :class="buttonClasses"
        v-bind="passThroughAttrs"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 shrink-0 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
        </svg>
        <slot v-else name="icon" />
        <slot />
    </span>

    <button
        v-else
        :type="type"
        :disabled="isInteractiveBlocked"
        :aria-busy="loading ? 'true' : undefined"
        :class="buttonClasses"
        v-bind="passThroughAttrs"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 shrink-0 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
        </svg>
        <slot v-else name="icon" />
        <slot />
    </button>
</template>
