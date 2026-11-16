<script setup>
import { reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    rows: Object,
    summary: Object,
    date: String,
    today: String,
    timezone: String,
    departments: Array,
    filters: Object,
});

const page = usePage();

const form = reactive({
    date: props.date,
    department: props.filters?.department ?? '',
});

function search() {
    const query = { date: form.date || props.today };
    if (form.department) {
        query.department = form.department;
    }
    router.get('/attendance/board', query, { preserveState: true, replace: true });
}

function goToday() {
    form.date = props.today;
    search();
}

function stateText(row) {
    if (row.state === 'not_in') {
        return 'Not checked in';
    }
    return row.state === 'in' ? 'Checked in' : 'Checked out';
}

function stateClass(row) {
    if (row.state === 'not_in') return 'bg-stone text-ink-muted border-border';
    if (row.state === 'in') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    return 'bg-blue-50 text-blue-700 border-blue-200';
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
</script>

<template>
    <Head title="Attendance Board" />

    <div class="min-h-full">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-ink tracking-tight">Attendance Board</h1>
                <p class="text-sm text-ink-muted mt-0.5">Live view for {{ date }}{{ date === today ? ' (today)' : '' }} · {{ timezone }}</p>
            </div>

            <!-- Date & Dept Form -->
            <form class="flex items-center gap-2 flex-wrap" @submit.prevent="search">
                <input
                    id="b-date"
                    v-model="form.date"
                    type="date"
                    required
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30 transition"
                    @change="search"
                />

                <select
                    id="b-department"
                    v-model="form.department"
                    class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30 transition"
                    @change="search"
                >
                    <option value="">All Departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>

                <button
                    type="button"
                    class="rounded-lg border border-border bg-surface px-3.5 py-2 text-sm font-medium text-ink hover:bg-stone transition-colors"
                    @click="goToday"
                >
                    Today
                </button>
            </form>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="text-xs text-ink-muted font-medium">Expected Staff</div>
                <div class="text-2xl font-bold text-ink mt-1">{{ summary.expected }}</div>
                <div class="text-[11px] text-ink-faint mt-0.5">Active workforce</div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="text-xs text-emerald-700 font-medium">Checked In</div>
                <div class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.checked_in }}</div>
                <div class="text-[11px] text-emerald-600/80 mt-0.5">Scanned on premises</div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="text-xs text-amber-700 font-medium">Late Arrivals</div>
                <div class="text-2xl font-bold text-amber-600 mt-1">{{ summary.late }}</div>
                <div class="text-[11px] text-amber-600/80 mt-0.5">Checked in past shift</div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="text-xs text-blue-700 font-medium">Checked Out</div>
                <div class="text-2xl font-bold text-blue-600 mt-1">{{ summary.checked_out }}</div>
                <div class="text-[11px] text-blue-600/80 mt-0.5">Shift completed</div>
            </div>

            <div class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                <div class="text-xs text-rose-700 font-medium">Not Checked In</div>
                <div class="text-2xl font-bold text-rose-600 mt-1">{{ summary.not_in }}</div>
                <div class="text-[11px] text-rose-600/80 mt-0.5">Yet to scan</div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink">Staff Records</h2>
                <span class="rounded-full bg-stone px-2.5 py-0.5 text-xs text-ink-muted font-medium">
                    {{ rows.data.length }} of {{ rows.total }} employees
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-stone/40">
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Employee</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Department</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">State</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Check In</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Check Out</th>
                            <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Hours Worked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="rows.data.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-ink-muted">
                                No employees to show for this day.
                            </td>
                        </tr>

                        <tr v-for="row in rows.data" :key="row.id" class="hover:bg-stone/30 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-8 w-8 rounded-full flex items-center justify-center font-semibold text-xs shrink-0"
                                        :class="avatarColor(row.name)"
                                    >
                                        {{ initials(row.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-ink leading-tight">{{ row.name }}</p>
                                        <p class="text-xs text-ink-muted font-mono leading-tight">{{ row.employee_number }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-ink-muted text-sm">{{ row.department }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border" :class="stateClass(row)">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="row.state === 'in' ? 'bg-emerald-500' : row.state === 'out' ? 'bg-blue-500' : 'bg-ink-faint'" />
                                    {{ stateText(row) }}
                                    <span v-if="row.late" class="text-red-600 font-bold ml-0.5">(Late)</span>
                                </span>
                            </td>
                            <td class="px-3 py-3 font-mono text-sm text-ink">{{ row.check_in ?? '—' }}</td>
                            <td class="px-3 py-3 font-mono text-sm text-ink">{{ row.check_out ?? '—' }}</td>
                            <td class="px-3 py-3 text-sm text-ink-muted font-medium">{{ row.worked ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-border bg-stone/20">
                <PagerLinks :meta="rows" />
            </div>
        </div>
    </div>
</template>
