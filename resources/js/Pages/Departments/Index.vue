<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

defineProps({
    departments: Object,
});

const page = usePage();

const editingId = ref(null);
const form = useForm({ name: '', description: '' });

function edit(department) {
    editingId.value = department.id;
    form.name = department.name;
    form.description = department.description ?? '';
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
        form.put(`/departments/${editingId.value}`, options);
    } else {
        form.post('/departments', options);
    }
}

function remove(department) {
    if (window.confirm(`Delete the department "${department.name}"?`)) {
        router.delete(`/departments/${department.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Departments" />

    <div>
        <h1 class="text-2xl font-bold">Departments</h1>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>
        <p v-if="page.props.errors?.department" role="alert" class="mt-3 rounded bg-red-50 p-3 text-red-900">
            {{ page.props.errors.department }}
        </p>

        <form class="mt-4 space-y-3 rounded border border-gray-200 bg-white p-3" @submit.prevent="submit">
            <h2 class="font-semibold">{{ editingId ? 'Edit department' : 'New department' }}</h2>

            <div>
                <label for="d-name" class="block text-sm font-medium">Name</label>
                <input id="d-name" v-model="form.name" type="text" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="d-description" class="block text-sm font-medium">Description (optional)</label>
                <textarea id="d-description" v-model="form.description" rows="2" class="mt-1 block w-full rounded border border-gray-300 px-3 py-2"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-700">{{ form.errors.description }}</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editingId ? 'Save changes' : 'Add department' }}
                </button>
                <button v-if="editingId" type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4" @click="cancel">Cancel</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ departments.total }} departments</p>

        <p v-if="departments.data.length === 0" class="mt-2">No departments yet.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Description</th>
                        <th class="py-2 pr-3">Positions</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="department in departments.data" :key="department.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3">{{ department.name }}</td>
                        <td class="py-2 pr-3">{{ department.description ?? '-' }}</td>
                        <td class="py-2 pr-3">{{ department.positions_count }}</td>
                        <td class="py-2">
                            <div class="flex gap-2">
                                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="edit(department)">Edit</button>
                                <button type="button" :disabled="department.positions_count > 0" :title="department.positions_count > 0 ? 'Delete its positions first' : ''" class="min-h-11 rounded border border-gray-300 bg-white px-3 disabled:opacity-40" @click="remove(department)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="departments.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="department in departments.data" :key="department.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ department.name }}</p>
                <p class="text-sm text-gray-600">{{ department.description ?? 'No description' }}</p>
                <p class="mt-1 text-sm">{{ department.positions_count }} positions</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="edit(department)">Edit</button>
                    <button type="button" :disabled="department.positions_count > 0" class="min-h-11 rounded border border-gray-300 px-3 disabled:opacity-40" @click="remove(department)">Delete</button>
                </div>
            </li>
        </ul>

        <PagerLinks :meta="departments" />
    </div>
</template>