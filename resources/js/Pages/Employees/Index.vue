<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    employees: Object,
    filters: Object,
    departments: Array,
    positions: Array,
    statuses: Array,
});

const page = usePage();

// Hiding the buttons is only convenience. The server checks every request.
const canManage = computed(() => page.props.auth?.permissions?.includes('employees.manage') ?? false);

// The download link carries the filters that are applied right now.
const exportUrl = computed(() => {
    const query = new URLSearchParams(
        Object.entries(props.filters ?? {}).filter(([key, value]) => ['search', 'department', 'position', 'status'].includes(key) && value),
    ).toString();

    return '/employees/export' + (query ? '?' + query : '');
});

const form = reactive({
    search: props.filters?.search ?? '',
    department: props.filters?.department ?? '',
    position: props.filters?.position ?? '',
    status: props.filters?.status ?? '',
});

function visit(extra = {}) {
    const query = {
        ...Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '' && value !== null)),
        ...(props.filters?.sort ? { sort: props.filters.sort, direction: props.filters.direction ?? 'asc' } : {}),
        ...extra,
    };

    router.get('/employees', query, { preserveState: true, replace: true });
}

function reset() {
    Object.keys(form).forEach((key) => {
        form[key] = '';
    });

    router.get('/employees', {}, { preserveState: true, replace: true });
}

function sortBy(column) {
    const direction = props.filters?.sort === column && props.filters?.direction !== 'desc' ? 'desc' : 'asc';

    visit({ sort: column, direction });
}

function marker(column) {
    if (props.filters?.sort !== column) {
        return '';
    }

    return props.filters?.direction === 'desc' ? ' (desc)' : ' (asc)';
}

function statusLabel(status) {
    return status.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="Employees" />

    <div>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Employees</h1>
            <div v-if="canManage" class="flex gap-2">
                <a :href="exportUrl" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4 font-medium">Export CSV</a>
                <Link href="/employees/create" class="flex min-h-11 items-center rounded bg-gray-900 px-4 font-medium text-white">
                    New employee
                </Link>
            </div>
        </div>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>

        <form class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="visit()">
            <div>
                <label for="f-search" class="block text-sm font-medium">Name or number</label>
                <input id="f-search" v-model="form.search" type="text" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <div>
                <label for="f-department" class="block text-sm font-medium">Department</label>
                <select id="f-department" v-model="form.department" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
            </div>

            <div>
                <label for="f-position" class="block text-sm font-medium">Position</label>
                <select id="f-position" v-model="form.position" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All positions</option>
                    <option v-for="p in positions" :key="p.id" :value="String(p.id)">{{ p.title }}</option>
                </select>
            </div>

            <div>
                <label for="f-status" class="block text-sm font-medium">Status</label>
                <select id="f-status" v-model="form.status" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3 capitalize">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
                </select>
            </div>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
                <button type="submit" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white">Search</button>
                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4 font-medium" @click="reset">Clear</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ employees.total }} employees</p>

        <p v-if="employees.data.length === 0" class="mt-2">No employees match these filters.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3"><button type="button" class="font-semibold" @click="sortBy('employee_number')">Number{{ marker('employee_number') }}</button></th>
                        <th class="py-2 pr-3"><button type="button" class="font-semibold" @click="sortBy('name')">Name{{ marker('name') }}</button></th>
                        <th class="py-2 pr-3">Position</th>
                        <th class="py-2 pr-3">Department</th>
                        <th class="py-2 pr-3"><button type="button" class="font-semibold" @click="sortBy('hire_date')">Hired{{ marker('hire_date') }}</button></th>
                        <th class="py-2 pr-3">Status</th>
                        <th v-if="canManage" class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="employee in employees.data" :key="employee.id" class="border-b border-gray-200 align-middle">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ employee.employee_number }}</td>
                        <td class="py-2 pr-3">{{ employee.name }}</td>
                        <td class="py-2 pr-3">{{ employee.position }}</td>
                        <td class="py-2 pr-3">{{ employee.department }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ employee.hire_date }}</td>
                        <td class="py-2 pr-3 capitalize">{{ statusLabel(employee.status) }}</td>
                        <td v-if="canManage" class="py-2">
                            <Link :href="`/employees/${employee.id}/edit`" class="flex min-h-11 w-fit items-center rounded border border-gray-300 bg-white px-3">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="employees.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="employee in employees.data" :key="employee.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ employee.name }}</p>
                <p class="text-sm text-gray-600">{{ employee.employee_number }} &middot; <span class="capitalize">{{ statusLabel(employee.status) }}</span></p>
                <p class="mt-1 text-sm">{{ employee.position }}, {{ employee.department }}</p>
                <Link v-if="canManage" :href="`/employees/${employee.id}/edit`" class="mt-2 flex min-h-11 w-fit items-center rounded border border-gray-300 px-3">Edit</Link>
            </li>
        </ul>

        <PagerLinks :meta="employees" />
    </div>
</template>