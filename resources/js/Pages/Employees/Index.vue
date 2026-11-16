<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../Components/FlashMessage.vue';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    employees: Object,
    filters: Object,
    departments: Array,
    positions: Array,
    statuses: Array,
    stats: Object,
});

const page = usePage();
const canManage = computed(() => page.props.auth?.permissions?.includes('employees.manage') ?? false);

const exportUrl = computed(() => {
    const query = new URLSearchParams(
        Object.entries(props.filters ?? {}).filter(([key, value]) =>
            ['search', 'department', 'position', 'status'].includes(key) && value,
        ),
    ).toString();
    return '/employees/export' + (query ? '?' + query : '');
});

const form = reactive({
    search: props.filters?.search ?? '',
    department: props.filters?.department ?? '',
    position: props.filters?.position ?? '',
    status: props.filters?.status ?? '',
});

const selectedRows = ref([]);

function visit(extra = {}) {
    const query = {
        ...Object.fromEntries(Object.entries(form).filter(([, v]) => v !== '' && v !== null)),
        ...(props.filters?.sort ? { sort: props.filters.sort, direction: props.filters.direction ?? 'asc' } : {}),
        ...extra,
    };
    router.get('/employees', query, { preserveState: true, replace: true });
}

function reset() {
    Object.keys(form).forEach((k) => (form[k] = ''));
    router.get('/employees', {}, { preserveState: true, replace: true });
}

function sortBy(col) {
    const dir = props.filters?.sort === col && props.filters?.direction !== 'desc' ? 'desc' : 'asc';
    visit({ sort: col, direction: dir });
}

function sortIcon(col) {
    if (props.filters?.sort !== col) return 'both';
    return props.filters?.direction === 'desc' ? 'down' : 'up';
}

function statusLabel(s) {
    return s.replaceAll('_', ' ');
}

function statusColor(s) {
    return {
        active:     'bg-emerald-50 text-emerald-700 border-emerald-200',
        on_leave:   'bg-amber-50 text-amber-700 border-amber-200',
        suspended:  'bg-orange-50 text-orange-700 border-orange-200',
        terminated: 'bg-red-50 text-red-700 border-red-200',
    }[s] ?? 'bg-stone text-ink-muted border-border';
}

function statusDot(s) {
    return {
        active:     'bg-emerald-500',
        on_leave:   'bg-amber-500',
        suspended:  'bg-orange-500',
        terminated: 'bg-red-500',
    }[s] ?? 'bg-ink-faint';
}

function initials(name) {
    return name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() ?? '??';
}

function avatarColor(name) {
    const colors = [
        'bg-violet-100 text-violet-700',
        'bg-blue-100 text-blue-700',
        'bg-teal-100 text-teal-700',
        'bg-rose-100 text-rose-700',
        'bg-amber-100 text-amber-700',
        'bg-indigo-100 text-indigo-700',
    ];
    const idx = (name?.charCodeAt(0) ?? 0) % colors.length;
    return colors[idx];
}

const allSelected = computed(
    () => props.employees?.data?.length > 0 && selectedRows.value.length === props.employees.data.length,
);

function toggleAll() {
    selectedRows.value = allSelected.value ? [] : props.employees.data.map(e => e.id);
}

function toggleRow(id) {
    selectedRows.value = selectedRows.value.includes(id)
        ? selectedRows.value.filter(r => r !== id)
        : [...selectedRows.value, id];
}
</script>

<template>
    <Head title="Employee Management" />

    <div class="min-h-full">
        <FlashMessage />

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-ink tracking-tight">Employee Management</h1>
                <p class="text-sm text-ink-muted mt-0.5">Manage employees, roles, departments and status.</p>
            </div>
            <div v-if="canManage" class="flex items-center gap-2 shrink-0">
                <a
                    :href="exportUrl"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-medium text-ink shadow-xs hover:bg-stone transition-colors"
                >
                    <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M10 3v10M6 9l4 4 4-4M4 17h12"/></svg>
                    Export CSV
                </a>
                <Link
                    href="/employees/create"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-forest px-3.5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-forest-hover transition-colors"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                    Add New Employee
                </Link>
            </div>
        </div>

        <!-- KPI Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="8" cy="6" r="3"/><path d="M2 18a6 6 0 0112 0"/><path d="M15 4a3 3 0 010 6M18 18a6 6 0 00-5-5.5"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">{{ stats?.total ?? employees.total }}</div>
                        <div class="text-xs text-ink-muted font-medium">Total Employees</div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-emerald-600 font-medium">↑ All registered staff</p>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M7 10l2 2 4-4"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">{{ stats?.active ?? 0 }}</div>
                        <div class="text-xs text-ink-muted font-medium">Active Employees</div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-emerald-600 font-medium">↑ Currently working</p>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M10 6v4l2 2"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">{{ stats?.on_leave ?? 0 }}</div>
                        <div class="text-xs text-ink-muted font-medium">On Leave</div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-amber-600 font-medium">Currently on leave</p>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M13 7L7 13M7 7l6 6"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-ink">{{ stats?.inactive ?? 0 }}</div>
                        <div class="text-xs text-ink-muted font-medium">Inactive</div>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-red-500 font-medium">Suspended / terminated</p>
            </div>
        </div>

        <!-- Table Card -->
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <!-- Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-border">
                <!-- Search -->
                <div class="relative flex-1 max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="9" cy="9" r="6"/><path d="M15 15l3 3"/></svg>
                    <input
                        v-model="form.search"
                        type="search"
                        placeholder="Search by name or number…"
                        class="w-full rounded-lg border border-border bg-stone/50 pl-9 pr-3 py-2 text-sm text-ink placeholder-ink-faint focus:outline-none focus:ring-2 focus:ring-forest/30 focus:border-forest/50 transition"
                        @keyup.enter="visit()"
                    />
                </div>

                <!-- Filters row -->
                <div class="flex items-center gap-2 flex-wrap">
                    <select
                        v-model="form.department"
                        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30 transition"
                        @change="visit()"
                    >
                        <option value="">All Departments</option>
                        <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                    </select>

                    <select
                        v-model="form.status"
                        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30 transition capitalize"
                        @change="visit()"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="s in statuses" :key="s" :value="s" class="capitalize">{{ statusLabel(s) }}</option>
                    </select>

                    <button
                        v-if="form.search || form.department || form.position || form.status"
                        type="button"
                        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink-muted hover:bg-stone transition-colors"
                        @click="reset"
                    >
                        Clear
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
                                    class="h-4 w-4 rounded border-border accent-forest cursor-pointer"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-3 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-ink-muted hover:text-ink" @click="sortBy('name')">
                                    Employee
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5-5 5 5H5zm0 4l5 5 5-5H5z"/></svg>
                                </button>
                            </th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Department</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Position</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Status</th>
                            <th class="px-3 py-3 text-left">
                                <button type="button" class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-ink-muted hover:text-ink" @click="sortBy('hire_date')">
                                    Hired
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5-5 5 5H5zm0 4l5 5 5-5H5z"/></svg>
                                </button>
                            </th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Empty -->
                        <tr v-if="employees.data.length === 0">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="h-12 w-12 rounded-full bg-stone flex items-center justify-center">
                                        <svg class="h-6 w-6 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="6" r="3"/><path d="M2 18a6 6 0 0112 0"/></svg>
                                    </div>
                                    <p class="text-sm font-medium text-ink-muted">No employees found</p>
                                    <Link v-if="canManage" href="/employees/create" class="inline-flex items-center gap-1.5 rounded-lg bg-forest px-4 py-2 text-sm font-semibold text-white hover:bg-forest-hover transition-colors">
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                                        Add First Employee
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <!-- Rows -->
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="hover:bg-stone/40 transition-colors"
                            :class="{ 'bg-soft-green/20': selectedRows.includes(employee.id) }"
                        >
                            <!-- Checkbox -->
                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="selectedRows.includes(employee.id)"
                                    class="h-4 w-4 rounded border-border accent-forest cursor-pointer"
                                    @change="toggleRow(employee.id)"
                                />
                            </td>

                            <!-- Employee (avatar + name + number) -->
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-9 w-9 rounded-full flex items-center justify-center font-semibold text-sm shrink-0 border border-white shadow-xs"
                                        :class="avatarColor(employee.name)"
                                    >
                                        {{ initials(employee.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-ink leading-tight">{{ employee.name }}</p>
                                        <p class="text-xs text-ink-muted leading-tight font-mono">{{ employee.employee_number }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Department -->
                            <td class="px-3 py-3 text-sm text-ink-muted whitespace-nowrap">{{ employee.department }}</td>

                            <!-- Position -->
                            <td class="px-3 py-3 text-sm text-ink whitespace-nowrap">{{ employee.position }}</td>

                            <!-- Status -->
                            <td class="px-3 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold capitalize border"
                                    :class="statusColor(employee.status)"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDot(employee.status)" />
                                    {{ statusLabel(employee.status) }}
                                </span>
                            </td>

                            <!-- Hired -->
                            <td class="px-3 py-3 text-sm text-ink-muted whitespace-nowrap">{{ employee.hire_date ?? '—' }}</td>

                            <!-- Actions -->
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-1">
                                    <Link
                                        v-if="canManage"
                                        :href="`/employees/${employee.id}/edit`"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-border text-ink-muted hover:text-forest hover:bg-soft-green hover:border-forest/20 transition-colors"
                                        title="Edit"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M13.5 3.5a2.121 2.121 0 013 3L7 16l-4 1 1-4 9.5-9.5z"/></svg>
                                    </Link>
                                    <!-- QR badge indicator -->
                                    <span
                                        v-if="employee.has_login"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-border text-ink-muted"
                                        title="Has login & QR access"
                                    >
                                        <svg class="h-3.5 w-3.5 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="13" y="13" width="2" height="2"/><rect x="11" y="11" width="2" height="2"/><rect x="15" y="11" width="2" height="4"/><rect x="13" y="15" width="4" height="2"/></svg>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-5 py-3 border-t border-border bg-stone/20">
                <span class="text-xs text-ink-muted">
                    Showing {{ employees.data.length }} of {{ employees.total }} employee{{ employees.total !== 1 ? 's' : '' }}
                    <span v-if="selectedRows.length"> · {{ selectedRows.length }} selected</span>
                </span>
                <PagerLinks :meta="employees" />
            </div>
        </div>
    </div>
</template>
