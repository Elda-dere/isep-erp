<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    positions: Object,
    departments: Array,
    filters: Object,
});

const page = usePage();

const editingId = ref(null);
const filterDepartment = ref(props.filters?.department ?? '');
const form = useForm({ department_id: '', title: '' });

function applyFilter() {
    router.get('/positions', filterDepartment.value ? { department: filterDepartment.value } : {}, {
        preserveState: true,
        replace: true,
    });
}

function edit(position) {
    editingId.value = position.id;
    form.department_id = String(position.department_id);
    form.title = position.title;
    form.clearErrors();
}

function cancel() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => cancel() };

    if (editingId.value) {
        form.put(`/positions/${editingId.value}`, options);
    } else {
        form.post('/positions', options);
    }
}

function remove(position) {
    if (window.confirm(`Delete the position "${position.title}"?`)) {
        router.delete(`/positions/${position.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Positions" />

    <div>
        <h1 class="text-2xl font-bold">Positions</h1>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>
        <p v-if="page.props.errors?.position" role="alert" class="mt-3 rounded bg-red-50 p-3 text-red-900">
            {{ page.props.errors.position }}
        </p>

        <form class="mt-4 space-y-3 rounded border border-gray-200 bg-white p-3" @submit.prevent="submit">
            <h2 class="font-semibold">{{ editingId ? 'Edit position' : 'New position' }}</h2>

            <div>
                <label for="p-department" class="block text-sm font-medium">Department</label>
                <select id="p-department" v-model="form.department_id" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="" disabled>Choose a department</option>
                    <option v-for="department in departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
                </select>
                <p v-if="form.errors.department_id" class="mt-1 text-sm text-red-700">{{ form.errors.department_id }}</p>
            </div>

            <div>
                <label for="p-title" class="block text-sm font-medium">Title</label>
                <input id="p-title" v-model="form.title" type="text" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-700">{{ form.errors.title }}</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editingId ? 'Save changes' : 'Add position' }}
                </button>
                <button v-if="editingId" type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4" @click="cancel">Cancel</button>
            </div>
        </form>

        <div class="mt-4">
            <label for="p-filter" class="block text-sm font-medium">Show positions of</label>
            <select id="p-filter" v-model="filterDepartment" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3 sm:w-72" @change="applyFilter">
                <option value="">All departments</option>
                <option v-for="department in departments" :key="department.id" :value="String(department.id)">{{ department.name }}</option>
            </select>
        </div>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ positions.total }} positions</p>

        <p v-if="positions.data.length === 0" class="mt-2">No positions found.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Title</th>
                        <th class="py-2 pr-3">Department</th>
                        <th class="py-2 pr-3">Employees</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="position in positions.data" :key="position.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3">{{ position.title }}</td>
                        <td class="py-2 pr-3">{{ position.department }}</td>
                        <td class="py-2 pr-3">{{ position.employees_count }}</td>
                        <td class="py-2">
                            <div class="flex gap-2">
                                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="edit(position)">Edit</button>
                                <button type="button" :disabled="position.employees_count > 0" :title="position.employees_count > 0 ? 'An employee holds this position' : ''" class="min-h-11 rounded border border-gray-300 bg-white px-3 disabled:opacity-40" @click="remove(position)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="positions.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="position in positions.data" :key="position.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ position.title }}</p>
                <p class="text-sm text-gray-600">{{ position.department }}</p>
                <p class="mt-1 text-sm">{{ position.employees_count }} employees</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="edit(position)">Edit</button>
                    <button type="button" :disabled="position.employees_count > 0" class="min-h-11 rounded border border-gray-300 px-3 disabled:opacity-40" @click="remove(position)">Delete</button>
                </div>
            </li>
        </ul>

        <PagerLinks :meta="positions" />
    </div>
</template>