<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: String,
});

// Label and colours for every status the app shows.
// The label is always written out: colour alone must never carry the meaning (WCAG).
const styles = {
    // Orders & Procurement
    draft: ['Draft', 'bg-stone text-ink-muted border border-border'],
    posted: ['Posted', 'bg-soft-green text-forest border border-forest/20'],
    ordered: ['Ordered', 'bg-blue-50 text-blue-800 border border-blue-200'],
    partially_received: ['Partially received', 'bg-amber-50 text-amber-800 border border-amber-200'],
    received: ['Received', 'bg-soft-green text-forest border border-forest/20'],
    cancelled: ['Cancelled', 'bg-red-50 text-red-800 border border-red-200'],

    // Sales & Payments
    pending: ['Pending payment', 'bg-amber-50 text-amber-800 border border-amber-200'],
    paid: ['Paid', 'bg-soft-green text-forest border border-forest/20'],
    success: ['Success', 'bg-soft-green text-forest border border-forest/20'],
    failed: ['Failed', 'bg-red-50 text-red-800 border border-red-200'],
    expired: ['Expired', 'bg-stone text-ink-muted border border-border'],

    // Employees & Organisation
    active: ['Active', 'bg-soft-green text-forest border border-forest/20'],
    inactive: ['Inactive', 'bg-stone text-ink-muted border border-border'],
    on_leave: ['On leave', 'bg-amber-50 text-amber-800 border border-amber-200'],
    suspended: ['Suspended', 'bg-red-50 text-red-800 border border-red-200'],
    terminated: ['Terminated', 'bg-stone text-ink-muted border border-border'],

    // Attendance
    present: ['Present', 'bg-soft-green text-forest border border-forest/20'],
    late: ['Late', 'bg-amber-50 text-amber-800 border border-amber-200'],
    success: ['Paid', 'bg-green-100 text-green-800'],
    failed: ['Failed', 'bg-red-100 text-red-800'],
    expired: ['Expired', 'bg-gray-200 text-gray-700'],
};

const label = computed(() => styles[props.status]?.[0] ?? props.status);
const classes = computed(() => styles[props.status]?.[1] ?? 'bg-stone text-ink-muted border border-border');
</script>

<template>
    <span
        class="inline-flex items-center rounded-sm px-2 py-0.5 text-xs font-medium tracking-wide"
        :class="classes"
    >
        {{ label }}
    </span>
</template>
