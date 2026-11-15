<script setup>
import { computed, provide, useId } from 'vue';

const props = defineProps({
    label: {
        type: String,
        default: '',
    },
    forId: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    srOnlyLabel: {
        type: Boolean,
        default: false,
    },
    labelTone: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'compact'].includes(value),
    },
});

const autoId = useId();

const fieldId = computed(() => props.forId || `field-${autoId}`);
const hintId = computed(() => (props.hint ? `${fieldId.value}-hint` : null));
const errorId = computed(() => (props.error ? `${fieldId.value}-error` : null));
const hasError = computed(() => Boolean(props.error));

const describedBy = computed(() => {
    const ids = [hintId.value, errorId.value].filter(Boolean);
    return ids.length ? ids.join(' ') : undefined;
});

const labelClasses = computed(() => {
    if (props.srOnlyLabel) {
        return 'sr-only';
    }
    if (props.labelTone === 'compact') {
        return 'mb-1 block text-xs font-medium text-ink-muted';
    }
    return 'mb-1 block text-sm font-medium text-ink';
});

provide(Symbol.for('isep.formField'), {
    id: fieldId,
    invalid: hasError,
    describedBy,
});
</script>

<template>
    <div>
        <label
            v-if="label || $slots.label"
            :for="fieldId"
            :class="labelClasses"
        >
            <slot name="label">{{ label }}</slot>
            <span v-if="required" class="ml-0.5 text-red-700" aria-hidden="true">*</span>
        </label>

        <slot :id="fieldId" :invalid="hasError" :described-by="describedBy" />

        <p
            v-if="hint"
            :id="hintId"
            class="mt-1 text-xs text-ink-muted"
        >
            {{ hint }}
        </p>

        <p
            v-if="error"
            :id="errorId"
            role="alert"
            class="mt-1 text-sm text-red-700"
        >
            {{ error }}
        </p>

        <slot name="after" />
    </div>
</template>
