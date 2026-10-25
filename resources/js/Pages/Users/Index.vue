<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
});

const page = usePage();

// Hiding the buttons is only convenience. The server checks every request.
const canManage = computed(() => page.props.auth?.permissions?.includes('users.manage') ?? false);

const form = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    role: props.filters?.role ?? '',
});

function search() {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== '' && value !== null),
    );

    router.get('/users', query, { preserveState: true, replace: true });
}

function reset() {
    Object.keys(form).forEach((key) => {
        form[key] = '';
    });

    router.get('/users', {}, { preserveState: true, replace: true });
}

function deactivate(user) {
    if (window.confirm(`Deactivate ${user.name}? They will be signed out on their next request.`)) {
        router.patch(`/users/${user.id}/deactivate`, {}, { preserveScroll: true });
    }
}

function activate(user) {
    router.patch(`/users/${user.id}/activate`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Users" />

    <div>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Users</h1>
            <Link
                v-if="canManage"
                href="/users/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 font-medium text-white"
            >
                New user
            </Link>
        </div>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>
        <p v-if="page.props.errors?.user" role="alert" class="mt-3 rounded bg-red-50 p-3 text-red-900">
            {{ page.props.errors.user }}
        </p>

        <form class="mt-4 grid gap-3 sm:grid-cols-3" @submit.prevent="search">
            <div>
                <label for="f-search" class="block text-sm font-medium">Name or email</label>
                <input id="f-search" v-model="form.search" type="text" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <div>
                <label for="f-status" class="block text-sm font-medium">Status</label>
                <select id="f-status" v-model="form.status" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div>
                <label for="f-role" class="block text-sm font-medium">Role</label>
                <select id="f-role" v-model="form.role" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All roles</option>
                    <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
                </select>
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white">Search</button>
                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4 font-medium" @click="reset">Clear</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ users.total }} users</p>

        <p v-if="users.data.length === 0" class="mt-2">No users match these filters.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Roles</th>
                        <th class="py-2 pr-3">Status</th>
                        <th v-if="canManage" class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b border-gray-200 align-middle">
                        <td class="py-2 pr-3">{{ user.name }}</td>
                        <td class="py-2 pr-3">{{ user.email }}</td>
                        <td class="py-2 pr-3">{{ user.roles.length ? user.roles.join(', ') : '-' }}</td>
                        <td class="py-2 pr-3">{{ user.is_active ? 'Active' : 'Inactive' }}</td>
                        <td v-if="canManage" class="py-2">
                            <div class="flex gap-2">
                                <Link :href="`/users/${user.id}/edit`" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-3">Edit</Link>
                                <button v-if="user.is_active" type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="deactivate(user)">Deactivate</button>
                                <button v-else type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="activate(user)">Activate</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="users.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="user in users.data" :key="user.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ user.name }}</p>
                <p class="text-sm text-gray-600">{{ user.email }}</p>
                <p class="mt-1 text-sm">{{ user.roles.length ? user.roles.join(', ') : 'No roles' }}</p>
                <p class="text-sm">Status: {{ user.is_active ? 'Active' : 'Inactive' }}</p>
                <div v-if="canManage" class="mt-2 flex gap-2">
                    <Link :href="`/users/${user.id}/edit`" class="flex min-h-11 items-center rounded border border-gray-300 px-3">Edit</Link>
                    <button v-if="user.is_active" type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="deactivate(user)">Deactivate</button>
                    <button v-else type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="activate(user)">Activate</button>
                </div>
            </li>
        </ul>

        <nav class="mt-4 flex items-center justify-between" aria-label="Pagination">
            <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Previous</Link>
            <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Previous</span>

            <span class="text-sm">Page {{ users.current_page }} of {{ users.last_page }}</span>

            <Link v-if="users.next_page_url" :href="users.next_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Next</Link>
            <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Next</span>
        </nav>
    </div>
</template>