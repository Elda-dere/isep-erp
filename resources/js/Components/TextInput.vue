<script setup>
import { computed, inject, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    id: {
        type: String,
        default: undefined,
    },
    type: {
        type: String,
        default: 'text',
    },
    invalid: {
        type: Boolean,
        default: undefined,
    },
    mono: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();
const fieldContext = inject(Symbol.for('isep.formField'), null);

const resolvedId = computed(() => props.id ?? fieldContext?.id.value ?? undefined);
const isInvalid = computed(() => props.invalid ?? fieldContext?.invalid.value ?? false);
const resolvedDescribedBy = computed(() => attrs['aria-describedby'] ?? fieldContext?.describedBy.value ?? undefined);

const inputAttrs = computed(() => {
    const {
        class: _class,
        id: _id,
        'aria-describedby': _describedBy,
        'aria-invalid': _invalid,
        ...rest
    } = attrs;
    return rest;
});

const inputClasses = computed(() => [
    'block min-h-11 w-full rounded border bg-surface px-3 text-sm text-ink placeholder:text-ink-faint transition-colors',
    isInvalid.value
        ? 'border-red-500 focus:border-red-600'
        : 'border-border hover:border-ink-faint focus:border-forest',
    props.mono && 'font-mono',
    'disabled:cursor-not-allowed disabled:bg-stone disabled:text-ink-muted',
    attrs.class,
]);
</script>

<template>
    <input
        :id="resolvedId"
        :type="type"
        :value="modelValue ?? ''"
        :disabled="disabled"
        :aria-invalid="isInvalid ? 'true' : attrs['aria-invalid']"
        :aria-describedby="resolvedDescribedBy"
        :class="inputClasses"
        v-bind="inputAttrs"
        @input="emit('update:modelValue', $event.target.value)"
    />
</template>
