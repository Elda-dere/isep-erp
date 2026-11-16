<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    userName: String,
    stats: Object,
    inventoryItems: Array,
    recentSales: Array,
});

const search = ref('');
const selectedRows = ref([]);

const filteredItems = computed(() => {
    if (!props.inventoryItems) return [];
    if (!search.value.trim()) return props.inventoryItems;
    const q = search.value.toLowerCase();
    return props.inventoryItems.filter(
        (item) =>
            item.name?.toLowerCase().includes(q) ||
            item.sku?.toLowerCase().includes(q) ||
            item.category?.toLowerCase().includes(q),
    );
});

function toggleRow(id) {
    if (selectedRows.value.includes(id)) {
        selectedRows.value = selectedRows.value.filter((r) => r !== id);
    } else {
        selectedRows.value.push(id);
    }
}

function toggleAll() {
    if (selectedRows.value.length === filteredItems.value.length) {
        selectedRows.value = [];
    } else {
        selectedRows.value = filteredItems.value.map((i) => i.id);
    }
}

const allSelected = computed(
    () => filteredItems.value.length > 0 && selectedRows.value.length === filteredItems.value.length,
);

const statCards = computed(() => [
    {
        label: 'Total Products',
        value: props.stats?.total_products ?? 0,
        icon: 'box',
        color: 'emerald',
        sub: 'Active SKUs',
    },
    {
        label: "Today's Revenue",
        value: props.stats?.revenue_today ?? '$0.00',
        icon: 'currency',
        color: 'blue',
        sub: 'Paid sales',
    },
    {
        label: "Today's Sales",
        value: props.stats?.sales_today ?? 0,
        icon: 'chart',
        color: 'purple',
        sub: 'Transactions',
    },
    {
        label: 'Low Stock',
        value: props.stats?.low_stock ?? 0,
        icon: 'alert',
        color: 'amber',
        sub: 'Need reorder',
    },
    {
        label: 'Customers',
        value: props.stats?.customers ?? 0,
        icon: 'users',
        color: 'teal',
        sub: 'Registered',
    },
    {
        label: 'Pending Sales',
        value: props.stats?.pending_sales ?? 0,
        icon: 'clock',
        color: 'rose',
        sub: 'Awaiting payment',
    },
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-full">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-xl font-bold text-ink tracking-tight">Dashboard</h1>
                    <p class="text-sm text-ink-muted mt-0.5">Welcome back, {{ userName }} — here's what's happening today.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-medium text-ink shadow-xs hover:bg-stone transition-colors"
                    >
                        <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 6h12M6 10h8M8 14h4"/></svg>
                        Action
                        <svg class="h-3.5 w-3.5 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-medium text-ink shadow-xs hover:bg-stone transition-colors"
                    >
                        <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 10h14M10 3l7 7-7 7"/></svg>
                        List on Marketplace
                        <svg class="h-3.5 w-3.5 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                    </button>
                    <Link
                        href="/products/create"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-forest px-3.5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-forest-hover transition-colors"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                        Add New
                    </Link>
                </div>
            </div>

            <!-- KPI Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-xl border border-border bg-surface p-4 shadow-xs flex flex-col gap-2 hover:shadow-sm transition-shadow"
                >
                    <!-- Icon -->
                    <div
                        class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0"
                        :class="{
                            'bg-emerald-50 text-emerald-600': card.color === 'emerald',
                            'bg-blue-50 text-blue-600': card.color === 'blue',
                            'bg-purple-50 text-purple-600': card.color === 'purple',
                            'bg-amber-50 text-amber-600': card.color === 'amber',
                            'bg-teal-50 text-teal-600': card.color === 'teal',
                            'bg-rose-50 text-rose-600': card.color === 'rose',
                        }"
                    >
                        <!-- box -->
                        <svg v-if="card.icon === 'box'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M2 7l8-4 8 4v6l-8 4-8-4V7z"/><path d="M2 7l8 4 8-4M10 11v7"/></svg>
                        <!-- currency -->
                        <svg v-else-if="card.icon === 'currency'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M10 6v8M8 8h3a1.5 1.5 0 010 3H8v1h4"/></svg>
                        <!-- chart -->
                        <svg v-else-if="card.icon === 'chart'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 15l4-4 3 3 4-5 3 3"/></svg>
                        <!-- alert -->
                        <svg v-else-if="card.icon === 'alert'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M10 3L2 17h16L10 3z"/><path d="M10 9v4M10 15h.01"/></svg>
                        <!-- users -->
                        <svg v-else-if="card.icon === 'users'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="8" cy="7" r="3"/><path d="M2 17a6 6 0 0112 0"/><path d="M14 5a3 3 0 010 6M18 17a6 6 0 00-5-5.9"/></svg>
                        <!-- clock -->
                        <svg v-else-if="card.icon === 'clock'" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M10 6v4l3 2"/></svg>
                    </div>

                    <div>
                        <div class="text-xl font-bold text-ink leading-tight">{{ card.value }}</div>
                        <div class="text-xs font-medium text-ink leading-tight mt-0.5">{{ card.label }}</div>
                        <div class="text-[11px] text-ink-muted mt-0.5">{{ card.sub }}</div>
                    </div>
                </div>
            </div>

            <!-- Inventory Table Card -->
            <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <!-- Table Toolbar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-border">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-ink-muted shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="3" width="16" height="14" rx="2"/><path d="M2 7h16M6 7v10"/></svg>
                        <h2 class="text-sm font-semibold text-ink">Inventory Overview</h2>
                        <span class="rounded-full bg-stone px-2 py-0.5 text-[11px] font-semibold text-ink-muted">
                            {{ filteredItems.length }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Search -->
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="9" cy="9" r="6"/><path d="M15 15l3 3"/></svg>
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search SKU, name, category…"
                                class="w-56 rounded-lg border border-border bg-stone/50 pl-9 pr-3 py-2 text-sm text-ink placeholder-ink-faint focus:outline-none focus:ring-2 focus:ring-forest/30 focus:border-forest/50 transition"
                            />
                        </div>
                        <!-- Filter icon -->
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-surface text-ink-muted hover:bg-stone transition-colors"
                            title="Filter"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 5h14M6 10h8M9 15h2"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-stone/40">
                                <th class="w-10 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        class="h-4 w-4 rounded border-border text-forest accent-forest cursor-pointer"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted whitespace-nowrap">SKU</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Title</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Category</th>
                                <th class="px-3 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-ink-muted">QTY</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Warehouse</th>
                                <th class="px-3 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Price</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted whitespace-nowrap">Last Modified</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Status</th>
                                <th class="px-3 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <!-- Empty State -->
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="10" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="h-12 w-12 rounded-full bg-stone flex items-center justify-center">
                                            <svg class="h-6 w-6 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 7l8-4 8 4v6l-8 4-8-4V7z"/></svg>
                                        </div>
                                        <p class="text-sm font-medium text-ink-muted">No inventory items found</p>
                                        <p class="text-xs text-ink-faint">Add products to see them here</p>
                                        <Link href="/products/create" class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-forest px-4 py-2 text-sm font-semibold text-white hover:bg-forest-hover transition-colors">
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                                            Add Product
                                        </Link>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="item in filteredItems"
                                :key="item.id"
                                class="hover:bg-stone/40 transition-colors"
                                :class="{ 'bg-soft-green/30': selectedRows.includes(item.id) }"
                            >
                                <!-- Checkbox -->
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="selectedRows.includes(item.id)"
                                        class="h-4 w-4 rounded border-border text-forest accent-forest cursor-pointer"
                                        @change="toggleRow(item.id)"
                                    />
                                </td>

                                <!-- SKU -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="font-mono text-xs font-semibold text-ink-muted bg-stone px-2 py-0.5 rounded">{{ item.sku }}</span>
                                </td>

                                <!-- Title (with product avatar) -->
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <!-- Product Avatar (initials) -->
                                        <div class="h-8 w-8 rounded-lg bg-soft-green text-forest flex items-center justify-center font-bold text-xs shrink-0 border border-forest/10">
                                            {{ item.name?.substring(0, 2).toUpperCase() }}
                                        </div>
                                        <span class="font-medium text-ink truncate max-w-[180px]">{{ item.name }}</span>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="rounded-full bg-stone px-2.5 py-0.5 text-xs font-medium text-ink-muted">{{ item.category }}</span>
                                </td>

                                <!-- QTY -->
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <span class="font-semibold text-ink">{{ item.qty }}</span>
                                    <span class="text-ink-faint text-xs ml-1">{{ item.unit }}</span>
                                </td>

                                <!-- Warehouse -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span class="text-ink-muted text-xs">{{ item.warehouse }}</span>
                                </td>

                                <!-- Price -->
                                <td class="px-3 py-3 text-right whitespace-nowrap font-semibold text-ink">
                                    {{ item.price }}
                                </td>

                                <!-- Last Modified -->
                                <td class="px-3 py-3 whitespace-nowrap text-xs text-ink-muted">
                                    {{ item.last_modified }}
                                </td>

                                <!-- Status Badge -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200': item.status === 'in-stock',
                                            'bg-amber-50 text-amber-700 border border-amber-200': item.status === 'low',
                                        }"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="{
                                                'bg-emerald-500': item.status === 'in-stock',
                                                'bg-amber-500': item.status === 'low',
                                            }"
                                        />
                                        {{ item.status === 'in-stock' ? 'In Stock' : 'Low Stock' }}
                                    </span>
                                </td>

                                <!-- Edit -->
                                <td class="px-3 py-3">
                                    <Link
                                        :href="`/products/${item.id}/edit`"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-border text-ink-muted hover:text-forest hover:bg-soft-green hover:border-forest/20 transition-colors"
                                        title="Edit product"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M13.5 3.5a2.121 2.121 0 013 3L7 16l-4 1 1-4 9.5-9.5z"/></svg>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div v-if="filteredItems.length > 0" class="flex items-center justify-between px-5 py-3 border-t border-border bg-stone/20">
                    <span class="text-xs text-ink-muted">
                        Showing {{ filteredItems.length }} item{{ filteredItems.length !== 1 ? 's' : '' }}
                        <span v-if="selectedRows.length"> · {{ selectedRows.length }} selected</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <Link href="/stock" class="text-xs font-medium text-forest hover:underline">View full inventory →</Link>
                    </div>
                </div>
            </div>

            <!-- Bottom: Recent Sales (if any) -->
            <div v-if="recentSales && recentSales.length > 0" class="mt-4 rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 4h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 12.414V17l-4-2v-2.586L3.293 7.707A1 1 0 013 7V5a1 1 0 011-1z"/></svg>
                        <h2 class="text-sm font-semibold text-ink">Recent Sales</h2>
                    </div>
                    <Link href="/sales" class="text-xs font-medium text-forest hover:underline">View all →</Link>
                </div>
                <div class="divide-y divide-border">
                    <div v-for="sale in recentSales" :key="sale.id" class="flex items-center justify-between px-5 py-3 hover:bg-stone/30 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-soft-green text-forest flex items-center justify-center font-bold text-xs border border-forest/10">
                                {{ sale.customer?.substring(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-ink">{{ sale.customer }}</p>
                                <p class="text-xs text-ink-muted">{{ sale.number }} · {{ sale.date }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-sm text-ink">{{ sale.amount }}</span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                :class="{
                                    'bg-emerald-50 text-emerald-700 border border-emerald-200': sale.status === 'paid',
                                    'bg-amber-50 text-amber-700 border border-amber-200': sale.status === 'pending',
                                    'bg-red-50 text-red-700 border border-red-200': sale.status === 'cancelled',
                                }"
                            >
                                {{ sale.status.charAt(0).toUpperCase() + sale.status.slice(1) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</template>
