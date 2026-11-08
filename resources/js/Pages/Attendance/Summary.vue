<script setup>
import { computed, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    rows: Object,
    month: String,
    currentMonth: String,
    timezone: String,
    countedThrough: String,
    workingDayNames: Array,
    departments: Array,
    filters: Object,
});

const page = usePage();

// Hiding the button is only convenience. The server checks every request.
const canExport = computed(() => page.props.auth?.permissions?.includes('attendance.correct') ?? false);

const form = reactive({
    month: props.month,
    department: props.filters?.department ?? '',
});

function search() {
    const query = { month: form.month || props.currentMonth };

    if (form.department) {
        query.department = form.department;
    }

    router.get('/attendance/summary', query, { preserveState: true, replace: true });
}

const exportUrl = computed(() => {
    const query = new URLSearchParams({
        month: props.month,
        ...(props.filters?.department ? { department: props.filters.department } : {}),
    }).toString();

    return '/attendance/summary/export?' + query;
});

function absentText(row) {
    return row.absent === null ? '-' : String(row.absent);
}
</script>

<template>
    <Head title="Attendance summary" />

    <div>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Attendance summary</h1>
            <a v-if="canExport" :href="exportUrl" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4 font-medium">Export CSV</a>
        </div>

        <form class="mt-4 grid gap-3 sm:grid-cols-3" @submit.prevent="search">
            <div>
                <label for="s-month" class="block text-sm font-medium">Month</label>
                <input id="s-month" v-model="form.month" type="month" :max="currentMonth" required placeholder="YYYY-MM" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="page.props.errors?.month" class="mt-1 text-sm text-red-700">{{ page.props.errors.month }}</p>
            </div>

            <div>
                <label for="s-department" class="block text-sm font-medium">Department</label>
                <select id="s-department" v-model="form.department" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white">Show</button>
            </div>
        </form>

        <section class="mt-4 rounded border border-gray-200 bg-white p-3 text-sm text-gray-700" aria-label="How the numbers are worked out">
            <p><strong>Days worked</strong> are days with an attendance record. <strong>Hours worked</strong> are check-out minus check-in.</p>
            <p class="mt-1">
                <strong>Absent days</strong> are working days ({{ workingDayNames.join(', ') }}) without a record,
                <template v-if="countedThrough">counted from the hire date or the 1st up to {{ countedThrough }}.</template>
                <template v-else>but no day of this month has been counted yet.</template>
                Holidays are not known. Absence is shown only for active employees.
            </p>
            <p class="mt-1"><strong>Missing check-outs</strong> leave out today. Times use {{ timezone }}.</p>
        </section>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ rows.total }} employees, {{ month }}</p>

        <p v-if="rows.data.length === 0" class="mt-2">No employees to show for this month.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Number</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Department</th>
                        <th class="py-2 pr-3">Days worked</th>
                        <th class="py-2 pr-3">Late days</th>
                        <th class="py-2 pr-3">Hours worked</th>
                        <th class="py-2 pr-3">Missing check-outs</th>
                        <th class="py-2">Absent days</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ row.employee_number }}</td>
                        <td class="py-2 pr-3">{{ row.name }}</td>
                        <td class="py-2 pr-3">{{ row.department }}</td>
                        <td class="py-2 pr-3">{{ row.days }}</td>
                        <td class="py-2 pr-3">{{ row.late }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ row.worked }}</td>
                        <td class="py-2 pr-3">{{ row.missing }}</td>
                        <td class="py-2">{{ absentText(row) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="rows.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="row in rows.data" :key="row.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ row.name }}</p>
                <p class="text-sm text-gray-600">{{ row.employee_number }} &middot; {{ row.department }}</p>
                <p class="mt-1 text-sm">{{ row.days }} days worked, {{ row.late }} late, {{ row.worked }}</p>
                <p class="text-sm">Missing check-outs: {{ row.missing }}. Absent days: {{ absentText(row) }}.</p>
            </li>
        </ul>

        <PagerLinks :meta="rows" />
    </div>
</template>