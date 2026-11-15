<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    className: {
        type: [String, Array, Object],
        default: 'h-4 w-4 shrink-0',
    },
});

function collectClassTokens(value) {
    if (!value) {
        return [];
    }
    if (typeof value === 'string') {
        return value.split(/\s+/).filter(Boolean);
    }
    if (Array.isArray(value)) {
        return value.flatMap(collectClassTokens);
    }
    if (typeof value === 'object') {
        return Object.entries(value)
            .filter(([, enabled]) => Boolean(enabled))
            .flatMap(([cls]) => cls.split(/\s+/).filter(Boolean));
    }
    return [];
}

// Always enforce shrink-0 and h-4 w-4 default dimensions unless caller specifies size classes
const resolvedClassName = computed(() => {
    const tokens = collectClassTokens(props.className);
    const hasSize = tokens.some((t) => /(?:^|:)size-/.test(t));
    const hasWidth = hasSize || tokens.some((t) => /(?:^|:)w-/.test(t));
    const hasHeight = hasSize || tokens.some((t) => /(?:^|:)h-/.test(t));
    const hasShrink = tokens.some((t) => /(?:^|:)shrink/.test(t));

    return [
        !hasWidth ? 'w-4' : '',
        !hasHeight ? 'h-4' : '',
        !hasShrink ? 'shrink-0' : '',
        ...tokens,
    ].filter(Boolean);
});
</script>

<template>
    <!-- Dashboard -->
    <svg v-if="name === 'dashboard'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="3" width="6" height="6" rx="1.5" />
        <rect x="11" y="3" width="6" height="6" rx="1.5" />
        <rect x="3" y="11" width="6" height="6" rx="1.5" />
        <rect x="11" y="11" width="6" height="6" rx="1.5" />
    </svg>

    <!-- Sales -->
    <svg v-else-if="name === 'sales'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4.5 3h11a1 1 0 011 1v13l-2.16-1.5L12.17 17 10 15.5 7.83 17l-2.17-1.5L3.5 17V4a1 1 0 011-1z" />
        <path d="M7 7h6M7 11h4" />
    </svg>

    <!-- New Sale (POS) -->
    <svg v-else-if="name === 'new-sale'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="10" cy="10" r="7" />
        <path d="M10 7v6M7 10h6" />
    </svg>

    <!-- Customers -->
    <svg v-else-if="name === 'customers'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="10" cy="7" r="3.5" />
        <path d="M4 17a6 6 0 0112 0" />
    </svg>

    <!-- Stock Overview -->
    <svg v-else-if="name === 'stock'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 7l7-4 7 4-7 4-7-4z" />
        <path d="M3 7v6l7 4 7-4V7" />
        <path d="M10 11v6" />
    </svg>

    <!-- Low Stock -->
    <svg v-else-if="name === 'low-stock'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10 3.5l7 12H3l7-12z" />
        <path d="M10 8v3.5M10 14h.01" />
    </svg>

    <!-- Adjust Stock -->
    <svg v-else-if="name === 'stock-adjust'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6h3M10 6h7M3 14h8M15 14h2" />
        <circle cx="8" cy="6" r="2" />
        <circle cx="13" cy="14" r="2" />
    </svg>

    <!-- Products -->
    <svg v-else-if="name === 'products'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 4a1 1 0 011-1h4.59a1 1 0 01.7.29l7.42 7.42a1 1 0 010 1.41l-4.59 4.59a1 1 0 01-1.41 0L3.29 9.29A1 1 0 013 8.59V4z" />
        <circle cx="7" cy="7" r="1.5" fill="currentColor" />
    </svg>

    <!-- Categories -->
    <svg v-else-if="name === 'categories'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 5a1 1 0 011-1h4l2 2h6a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V5z" />
    </svg>

    <!-- Units -->
    <svg v-else-if="name === 'units'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10 3v14M5 7l5-2 5 2M3 13l2-6 2 6a2 2 0 01-4 0zM13 13l2-6 2 6a2 2 0 01-4 0z" />
    </svg>

    <!-- Warehouses -->
    <svg v-else-if="name === 'warehouses'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 7.5L10 3l7 4.5V17H3V7.5z" />
        <path d="M7 11h6v6H7v-6z" />
    </svg>

    <!-- Purchase Orders -->
    <svg v-else-if="name === 'purchase-orders'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="4" y="5" width="12" height="12" rx="1.5" />
        <path d="M8 3h4a1 1 0 011 1v1H7V4a1 1 0 011-1z" />
        <path d="M7 9h6M7 12h4" />
    </svg>

    <!-- Goods Receipts -->
    <svg v-else-if="name === 'goods-receipts'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 4h10v9H3V4z" />
        <path d="M13 7h3l2 3v3h-5V7z" />
        <circle cx="6.5" cy="15.5" r="1.5" />
        <circle cx="15.5" cy="15.5" r="1.5" />
    </svg>

    <!-- Suppliers -->
    <svg v-else-if="name === 'suppliers'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 17V5a1 1 0 011-1h10a1 1 0 011 1v12" />
        <path d="M7 8h2M11 8h2M7 11h2M11 11h2M2 17h16" />
    </svg>

    <!-- Attendance Board -->
    <svg v-else-if="name === 'attendance-board'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="4" width="14" height="13" rx="1.5" />
        <path d="M3 8h14M7 2v3M13 2v3" />
        <circle cx="7.5" cy="11.5" r="1" fill="currentColor" />
        <circle cx="12.5" cy="11.5" r="1" fill="currentColor" />
    </svg>

    <!-- Attendance Summary -->
    <svg v-else-if="name === 'attendance-summary'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M4 16h12M6 13v-3M10 13V6M14 13v-5" />
    </svg>

    <!-- Scanner -->
    <svg v-else-if="name === 'scanner'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6V4a1 1 0 011-1h2M14 3h2a1 1 0 011 1v2M3 14v2a1 1 0 001 1h2M14 17h2a1 1 0 001-1v-2" />
        <path d="M3 10h14" stroke-dasharray="2 2" />
    </svg>

    <!-- My QR -->
    <svg v-else-if="name === 'my-qr'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="3" width="5" height="5" rx="1" />
        <rect x="12" y="3" width="5" height="5" rx="1" />
        <rect x="3" y="12" width="5" height="5" rx="1" />
        <path d="M12 12h2v2h-2zM15 15h2v2h-2zM12 16h1v1h-1zM16 12h1v2h-1z" />
    </svg>

    <!-- My Attendance -->
    <svg v-else-if="name === 'my-attendance'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="10" cy="10" r="7" />
        <path d="M10 6v4.5l3 1.5" />
    </svg>

    <!-- Employees -->
    <svg v-else-if="name === 'employees'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M13 16a4 4 0 00-8 0" />
        <circle cx="9" cy="8" r="3" />
        <path d="M13.5 8a2.5 2.5 0 11-1.5 4.5M16 16a3.5 3.5 0 00-2.5-3.3" />
    </svg>

    <!-- Organisation -->
    <svg v-else-if="name === 'organisation'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="8" y="2" width="4" height="4" rx="1" />
        <rect x="3" y="14" width="4" height="4" rx="1" />
        <rect x="13" y="14" width="4" height="4" rx="1" />
        <path d="M10 6v4m-5 4v-2a2 2 0 012-2h6a2 2 0 012 2v2" />
    </svg>

    <!-- Departments -->
    <svg v-else-if="name === 'departments'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="6" width="14" height="11" rx="1.5" />
        <path d="M7 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
        <path d="M3 11h14" />
    </svg>

    <!-- Positions -->
    <svg v-else-if="name === 'positions'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="4" y="3" width="12" height="14" rx="1.5" />
        <path d="M8 7h4M8 10h4M8 13h2" />
    </svg>

    <!-- Users -->
    <svg v-else-if="name === 'users'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="8" cy="7" r="3" />
        <path d="M3 16a5 5 0 0110 0" />
        <path d="M14 7h4m-2-2v4" />
    </svg>

    <!-- Roles -->
    <svg v-else-if="name === 'roles'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10 3l6 2.5v5c0 4-2.5 7-6 8-3.5-1-6-4-6-8v-5L10 3z" />
        <path d="M8 10l1.5 1.5 3-3" />
    </svg>

    <!-- Audit Log -->
    <svg v-else-if="name === 'audit-logs'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="10" cy="10" r="7" />
        <path d="M10 6v4l2.5 2.5" />
        <path d="M4 10A6 6 0 1110 16" stroke-dasharray="1 2" />
    </svg>

    <!-- Networks -->
    <svg v-else-if="name === 'networks'" :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 7a10 10 0 0114 0M5.5 10a6.5 6.5 0 019 0M8 13a3 3 0 014 0M10 16h.01" />
    </svg>

    <!-- Default fallback icon -->
    <svg v-else :class="resolvedClassName" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="10" cy="10" r="7" />
    </svg>
</template>
