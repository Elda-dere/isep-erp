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
</script>

<template>
    <Head title="Attendance board" />

    <div>
        <h1 class="text-2xl font-bold">Attendance board</h1>
        <p class="mt-1 text-sm text-gray-600">Times are shown in {{ timezone }}.</p>

        <form class="mt-4 grid gap-3 sm:grid-cols-3" @submit.prevent="search">
            <div>
                <label for="b-date" class="block text-sm font-medium">Date</label>
                <input id="b-date" v-model="form.date" type="date" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="page.props.errors?.date" class="mt-1 text-sm text-red-700">{{ page.props.errors.date }}</p>
            </div>

            <div>
                <label for="b-department" class="block text-sm font-medium">Department</label>
                <select id="b-department" v-model="form.department" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white">Show</button>
                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4 font-medium" @click="goToday">Today</button>
            </div>
        </form>

        <section class="mt-4" aria-label="Summary for {{ date }}">
            <p class="text-sm text-gray-600">Showing {{ date }}{{ date === today ? ' (today)' : '' }}</p>
            <ul class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-5">
                <li class="rounded border border-gray-200 bg-white p-3"><p class="text-2xl font-bold">{{ summary.expected }}</p><p class="text-sm text-gray-600">Expected</p></li>
                <li class="rounded border border-gray-200 bg-white p-3"><p class="text-2xl font-bold">{{ summary.checked_in }}</p><p class="text-sm text-gray-600">Checked in</p></li>
                <li class="rounded border border-gray-200 bg-white p-3"><p class="text-2xl font-bold">{{ summary.late }}</p><p class="text-sm text-gray-600">Late</p></li>
                <li class="rounded border border-gray-200 bg-white p-3"><p class="text-2xl font-bold">{{ summary.checked_out }}</p><p class="text-sm text-gray-600">Checked out</p></li>
                <li class="rounded border border-gray-200 bg-white p-3"><p class="text-2xl font-bold">{{ summary.not_in }}</p><p class="text-sm text-gray-600">Not yet checked in</p></li>
            </ul>
            <p class="mt-2 text-sm text-gray-600">"Checked in" counts everyone with a record that day, including those already checked out.</p>
        </section>

        <p v-if="rows.data.length === 0" class="mt-4">No employees to show for this day.</p>

        <div v-else class="mt-4 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Number</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Department</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">In</th>
                        <th class="py-2 pr-3">Out</th>
                        <th class="py-2">Worked</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ row.employee_number }}</td>
                        <td class="py-2 pr-3">{{ row.name }}</td>
                        <td class="py-2 pr-3">{{ row.department }}</td>
                        <td class="py-2 pr-3">{{ stateText(row) }}<span v-if="row.late" class="ml-1 font-medium text-red-700">(late)</span></td>
                        <td class="py-2 pr-3">{{ row.check_in ?? '-' }}</td>
                        <td class="py-2 pr-3">{{ row.check_out ?? '-' }}</td>
                        <td class="py-2">{{ row.worked ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="rows.data.length > 0" class="mt-4 space-y-3 md:hidden">
            <li v-for="row in rows.data" :key="row.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ row.name }}</p>
                <p class="text-sm text-gray-600">{{ row.employee_number }} &middot; {{ row.department }}</p>
                <p class="mt-1 text-sm">{{ stateText(row) }}<span v-if="row.late" class="ml-1 font-medium text-red-700">(late)</span></p>
                <p v-if="row.check_in" class="text-sm">In {{ row.check_in }}<span v-if="row.check_out">, out {{ row.check_out }}</span><span v-if="row.worked">, worked {{ row.worked }}</span></p>
            </li>
        </ul>

        <PagerLinks :meta="rows" />
    </div>
</template>