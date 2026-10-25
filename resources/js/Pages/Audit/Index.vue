<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    logs: Object,
    filters: Object,
    users: Array,
    entities: Array,
});

const form = reactive({
    user: props.filters?.user ?? '',
    action: props.filters?.action ?? '',
    entity: props.filters?.entity ?? '',
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    ip: props.filters?.ip ?? '',
});

function search() {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== '' && value !== null),
    );

    router.get('/audit-logs', query, { preserveState: true, replace: true });
}

function reset() {
    Object.keys(form).forEach((key) => {
        form[key] = '';
    });

    router.get('/audit-logs', {}, { preserveState: true, replace: true });
}

function who(log) {
    return log.user ? log.user.name : 'System';
}

function what(log) {
    return log.entity_type ? `${log.entity_type} #${log.entity_id ?? ''}` : '-';
}

function hasValues(log) {
    return log.old_values !== null || log.new_values !== null;
}

function pretty(value) {
    return JSON.stringify(value, null, 2);
}
</script>

<template>
    <Head title="Audit log" />

    <div>
        <h1 class="text-2xl font-bold">Audit log</h1>
        <p class="mt-1 text-sm text-gray-600">
            Read-only record of important actions. Times are shown in Africa/Addis_Ababa.
        </p>

        <form class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="search">
            <div>
                <label for="f-user" class="block text-sm font-medium">User</label>
                <select id="f-user" v-model="form.user" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All users</option>
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <p v-if="$page.props.errors.user" class="mt-1 text-sm text-red-700">{{ $page.props.errors.user }}</p>
            </div>

            <div>
                <label for="f-action" class="block text-sm font-medium">Action starts with</label>
                <input id="f-action" v-model="form.action" type="text" placeholder="auth. or product." class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <div>
                <label for="f-entity" class="block text-sm font-medium">Record type</label>
                <select id="f-entity" v-model="form.entity" class="mt-1 block min-h-11 w-full rounded border border-gray-300 bg-white px-3">
                    <option value="">All types</option>
                    <option v-for="e in entities" :key="e" :value="e">{{ e }}</option>
                </select>
            </div>

            <div>
                <label for="f-from" class="block text-sm font-medium">From date</label>
                <input id="f-from" v-model="form.from" type="date" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <div>
                <label for="f-to" class="block text-sm font-medium">To date</label>
                <input id="f-to" v-model="form.to" type="date" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="$page.props.errors.to" class="mt-1 text-sm text-red-700">{{ $page.props.errors.to }}</p>
            </div>

            <div>
                <label for="f-ip" class="block text-sm font-medium">IP address</label>
                <input id="f-ip" v-model="form.ip" type="text" inputmode="decimal" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <button type="submit" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white">Search</button>
                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4 font-medium" @click="reset">Clear</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ logs.total }} entries</p>

        <p v-if="logs.data.length === 0" class="mt-2">No entries match these filters.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Time</th>
                        <th class="py-2 pr-3">User</th>
                        <th class="py-2 pr-3">Action</th>
                        <th class="py-2 pr-3">Record</th>
                        <th class="py-2 pr-3">IP address</th>
                        <th class="py-2">Values</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-b border-gray-200 align-top">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ log.created_at }}</td>
                        <td class="py-2 pr-3">{{ who(log) }}</td>
                        <td class="py-2 pr-3">{{ log.action }}</td>
                        <td class="py-2 pr-3">{{ what(log) }}</td>
                        <td class="py-2 pr-3">{{ log.ip_address ?? '-' }}</td>
                        <td class="py-2">
                            <details v-if="hasValues(log)">
                                <summary class="cursor-pointer">View</summary>
                                <pre v-if="log.old_values !== null" class="mt-1 overflow-x-auto text-xs">Old: {{ pretty(log.old_values) }}</pre>
                                <pre v-if="log.new_values !== null" class="mt-1 overflow-x-auto text-xs">New: {{ pretty(log.new_values) }}</pre>
                            </details>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="logs.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="log in logs.data" :key="log.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ log.action }}</p>
                <p class="text-sm text-gray-600">{{ log.created_at }}</p>
                <p class="mt-1 text-sm">{{ who(log) }} &middot; {{ what(log) }}</p>
                <p class="text-sm text-gray-600">IP: {{ log.ip_address ?? '-' }}</p>
                <details v-if="hasValues(log)" class="mt-2">
                    <summary class="min-h-11 cursor-pointer py-2">View values</summary>
                    <pre v-if="log.old_values !== null" class="overflow-x-auto text-xs">Old: {{ pretty(log.old_values) }}</pre>
                    <pre v-if="log.new_values !== null" class="overflow-x-auto text-xs">New: {{ pretty(log.new_values) }}</pre>
                </details>
            </li>
        </ul>

        <nav class="mt-4 flex items-center justify-between" aria-label="Pagination">
            <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Previous</Link>
            <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Previous</span>

            <span class="text-sm">Page {{ logs.current_page }} of {{ logs.last_page }}</span>

            <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Next</Link>
            <span v-else class="flex min-h-11 items-center px-4 text-gray-400">Next</span>
        </nav>
    </div>
</template>