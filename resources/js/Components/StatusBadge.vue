<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: String,
});

// Label and colours for every status the app shows. Later slices add theirs here.
// The label is always written out: colour alone must never carry the meaning.
const styles = {
    draft: ['Draft', 'bg-gray-200 text-gray-700'],
    ordered: ['Ordered', 'bg-blue-100 text-blue-800'],
    partially_received: ['Partially received', 'bg-amber-100 text-amber-800'],
    received: ['Received', 'bg-green-100 text-green-800'],
    cancelled: ['Cancelled', 'bg-red-100 text-red-800'],
};

const label = computed(() => styles[props.status]?.[0] ?? props.status);
const classes = computed(() => styles[props.status]?.[1] ?? 'bg-gray-200 text-gray-700');
</script>

<template>
    <span class="rounded px-2 py-0.5 text-xs font-medium" :class="classes">{{ label }}</span>
</template>