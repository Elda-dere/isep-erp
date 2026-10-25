<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    roles: Array,
});

const page = usePage();
</script>

<template>
    <Head title="Roles" />

    <div>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Roles</h1>
            <Link href="/roles/create" class="flex min-h-11 items-center rounded bg-gray-900 px-4 font-medium text-white">New role</Link>
        </div>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>

        <div class="mt-4 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Role</th>
                        <th class="py-2 pr-3">Users</th>
                        <th class="py-2 pr-3">Permissions</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3">
                            {{ role.name }}
                            <span v-if="role.locked" class="ml-2 rounded bg-gray-200 px-2 py-0.5 text-xs">Locked</span>
                        </td>
                        <td class="py-2 pr-3">{{ role.users_count }}</td>
                        <td class="py-2 pr-3">{{ role.permissions_count }}</td>
                        <td class="py-2">
                            <Link :href="`/roles/${role.id}/edit`" class="flex min-h-11 w-fit items-center rounded border border-gray-300 bg-white px-3">
                                {{ role.locked ? 'View' : 'Edit' }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul class="mt-4 space-y-3 md:hidden">
            <li v-for="role in roles" :key="role.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">
                    {{ role.name }}
                    <span v-if="role.locked" class="ml-2 rounded bg-gray-200 px-2 py-0.5 text-xs">Locked</span>
                </p>
                <p class="text-sm text-gray-600">{{ role.users_count }} users, {{ role.permissions_count }} permissions</p>
                <Link :href="`/roles/${role.id}/edit`" class="mt-2 flex min-h-11 w-fit items-center rounded border border-gray-300 px-3">
                    {{ role.locked ? 'View' : 'Edit' }}
                </Link>
            </li>
        </ul>
    </div>
</template>