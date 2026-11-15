<script setup>
import { computed, inject, useAttrs } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean, Object],
        default: '',
    },
    id: {
        type: String,
        default: undefined,
    },
    options: {
        type: Array,
        default: null,
    },
    placeholder: {
        type: String,
        default: '',
    },
    placeholderDisabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: undefined,
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

// Proxy through computed v-model so Vue preserves numeric, boolean, and object option _value types
// while normalizing null/undefined to '' so placeholder <option value=""> selects cleanly.
const selectedValue = computed({
    get: () => props.modelValue ?? '',
    set: (value) => emit('update:modelValue', value),
});

const selectAttrs = computed(() => {
    const {
        class: _class,
        id: _id,
        'aria-describedby': _describedBy,
        'aria-invalid': _invalid,
        ...rest
    } = attrs;
    return rest;
});

const selectClasses = computed(() => [
    'block min-h-11 w-full rounded border bg-surface px-3 text-sm text-ink transition-colors',
    isInvalid.value
        ? 'border-red-500 focus:border-red-600'
        : 'border-border hover:border-ink-faint focus:border-forest',
    'disabled:cursor-not-allowed disabled:bg-stone disabled:text-ink-muted',
    attrs.class,
]);
</script>

<template>
    <select
        :id="resolvedId"
        v-model="selectedValue"
        :disabled="disabled"
        :aria-invalid="isInvalid ? 'true' : attrs['aria-invalid']"
        :aria-describedby="resolvedDescribedBy"
        :class="selectClasses"
        v-bind="selectAttrs"
    >
        <option
            v-if="placeholder"
            value=""
            :disabled="placeholderDisabled"
        >
            {{ placeholder }}
        </option>

        <slot>
            <option
                v-for="(option, index) in options ?? []"
                :key="`${index}-${String(option.value)}`"
                :value="option.value"
                :disabled="Boolean(option.disabled)"
            >
                {{ option.label }}
            </option>
        </slot>
    </select>
</template>
