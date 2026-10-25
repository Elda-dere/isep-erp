<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    employee: Object,
    suggestedNumber: String,
    positionGroups: Array,
    users: Array,
    statuses: Array,
});

const editing = computed(() => props.employee !== null && props.employee !== undefined);

const form = useForm({
    employee_number: '',
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    phone: props.employee?.phone ?? '',
    hire_date: props.employee?.hire_date ?? '',
    position_id: props.employee?.position_id ?? '',
    user_id: props.employee?.user_id ?? '',
    employment_status: 'active',
});

const statusForm = useForm({
    status: props.employee?.employment_status ?? 'active',
});

function submit() {
    if (editing.value) {
        form.put(`/employees/${props.employee.id}`);
    } else {
        form.post('/employees');
    }
}

function statusLabel(status) {
    return status.replaceAll('_', ' ');
}

function changeStatus() {
    if (statusForm.status === props.employee.employment_status) {
        return;
    }

    const blocking = ['suspended', 'terminated'].includes(statusForm.status);
    const message = blocking
        ? `Change the status to ${statusLabel(statusForm.status)}? Their QR codes stop working at once and they cannot check in.`
        : `Change the status to ${statusLabel(statusForm.status)}?`;

    if (window.confirm(message)) {
        statusForm.patch(`/employees/${props.employee.id}/status`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="editing ? 'Edit employee' : 'New employee'" />

    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-bold">{{ editing ? 'Edit employee' : 'New employee' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div v-if="editing">
                <p class="block text-sm font-medium">Employee number</p>
                <p class="mt-1 flex min-h-11 items-center rounded bg-gray-100 px-3">{{ employee.employee_number }}</p>
            </div>

            <div v-else>
                <label for="employee_number" class="block text-sm font-medium">Employee number (optional)</label>
                <input id="employee_number" v-model="form.employee_number" type="text" :placeholder="suggestedNumber" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p class="mt-1 text-sm text-gray-600">Leave empty to use {{ suggestedNumber }}.</p>
                <p v-if="form.errors.employee_number" class="mt-1 text-sm text-red-700">{{ form.errors.employee_number }}</p>
            </div>

            <div>
                <label for="first_name" class="block text-sm font-medium">First name</label>
                <input id="first_name" v-model="form.first_name" type="text" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-700">{{ form.errors.first_name }}</p>
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium">Last name</label>
                <input id="last_name" v-model="form.last_name" type="text" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-700">{{ form.errors.last_name }}</p>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium">Phone (optional)</label>
                <input id="phone" v-model="form.phone" type="tel" inputmode="tel" autocomplete="off" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-700">{{ form.errors.phone }}</p>
            </div>

            <div>
                <label for="hire_date" class="block text-sm font-medium">Hire date</label>
                <input id="hire_date" v-model="form.hire_date" type="date" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.hire_date" class="mt-1 text-sm text-red-700">{{ form.errors.hire_date }}</p>
            </div>

            <div>
                <label for="position_id" class="block text-sm font-medium">Position</label>
                <select id="position_id" v-model="form.position_id" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="" disabled>Choose a position</option>
                    <optgroup v-for="group in positionGroups" :key="group.department" :label="group.department">
                        <option v-for="position in group.positions" :key="position.id" :value="position.id">{{ position.title }}</option>
                    </optgroup>
                </select>
                <p v-if="form.errors.position_id" class="mt-1 text-sm text-red-700">{{ form.errors.position_id }}</p>
            </div>

            <div>
                <label for="user_id" class="block text-sm font-medium">Login account (optional)</label>
                <select id="user_id" v-model="form.user_id" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">No login account</option>
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                </select>
                <p class="mt-1 text-sm text-gray-600">Only employees with a login can show a QR code on their own phone.</p>
                <p v-if="form.errors.user_id" class="mt-1 text-sm text-red-700">{{ form.errors.user_id }}</p>
            </div>

            <div v-if="!editing">
                <label for="employment_status" class="block text-sm font-medium">Status</label>
                <select id="employment_status" v-model="form.employment_status" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3 capitalize">
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
                </select>
                <p v-if="form.errors.employment_status" class="mt-1 text-sm text-red-700">{{ form.errors.employment_status }}</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editing ? 'Save changes' : 'Create employee' }}
                </button>
                <Link href="/employees" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Cancel</Link>
            </div>
        </form>

        <form v-if="editing" class="mt-10 space-y-3 border-t border-gray-200 pt-6" @submit.prevent="changeStatus">
            <h2 class="text-xl font-bold">Status</h2>
            <p class="text-sm text-gray-600">Suspended or terminated employees lose their QR codes at once and cannot check in. Nobody is ever deleted.</p>

            <div>
                <label for="status" class="block text-sm font-medium">Employment status</label>
                <select id="status" v-model="statusForm.status" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3 capitalize">
                    <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
                </select>
                <p v-if="statusForm.errors.status" class="mt-1 text-sm text-red-700">{{ statusForm.errors.status }}</p>
            </div>

            <button type="submit" :disabled="statusForm.processing" class="min-h-11 rounded border border-gray-300 bg-white px-4 font-medium disabled:opacity-50">
                Change status
            </button>

            <p v-if="statusForm.recentlySuccessful" role="status" class="text-sm text-green-800">Status updated.</p>
        </form>
    </div>
</template>