<script setup>
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

const props = defineProps({
    networks: Object,
    yourIp: String,
    hasActive: Boolean,
    yourIpAllowed: Boolean,
});

const page = usePage();

const editingId = ref(null);
const form = useForm({ name: '', ip_range: '' });

function fillMyAddress() {
    // One single address: /32 for IPv4, /128 for IPv6.
    const suffix = props.yourIp?.includes(':') ? '/128' : '/32';

    form.ip_range = (props.yourIp ?? '') + suffix;
}

function edit(network) {
    editingId.value = network.id;
    form.name = network.name;
    form.ip_range = network.ip_range;
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
        form.put(`/networks/${editingId.value}`, options);
    } else {
        form.post('/networks', options);
    }
}

function toggle(network) {
    if (!network.is_active) {
        router.patch(`/networks/${network.id}/activate`, {}, { preserveScroll: true });

        return;
    }

    if (window.confirm(`Deactivate "${network.name}"? Scans from this range will be rejected.`)) {
        router.patch(`/networks/${network.id}/deactivate`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Approved networks" />

    <div>
        <h1 class="text-2xl font-bold">Approved networks</h1>
        <p class="mt-1 text-sm text-gray-600">
            Attendance scans are accepted only from these address ranges.
        </p>

        <p v-if="page.props.flash?.success" role="status" class="mt-3 rounded bg-green-50 p-3 text-green-900">
            {{ page.props.flash.success }}
        </p>

        <p v-if="!hasActive" role="alert" class="mt-3 rounded bg-amber-50 p-3 text-amber-900">
            No network is active. Every attendance scan is rejected until you add and activate one.
        </p>

        <div class="mt-3 rounded border border-gray-200 bg-white p-3 text-sm">
            <p>Your address, as the server sees it: <strong>{{ yourIp || 'unknown' }}</strong></p>
            <p class="mt-1">Inside an active approved network: <strong>{{ yourIpAllowed ? 'Yes' : 'No' }}</strong></p>
            <p class="mt-1 text-gray-600">
                Behind a proxy or load balancer the server may see the proxy's address instead.
                That must be configured before real use (trusted proxies).
            </p>
        </div>

        <form class="mt-4 space-y-3 rounded border border-gray-200 bg-white p-3" @submit.prevent="submit">
            <h2 class="font-semibold">{{ editingId ? 'Edit network' : 'New network' }}</h2>

            <div>
                <label for="n-name" class="block text-sm font-medium">Name</label>
                <input id="n-name" v-model="form.name" type="text" required placeholder="Head office Wi-Fi" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="n-range" class="block text-sm font-medium">Address range</label>
                <input id="n-range" v-model="form.ip_range" type="text" required inputmode="text" autocomplete="off" placeholder="192.168.1.0/24" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p class="mt-1 text-sm text-gray-600">
                    Write an address and a slash, for example 192.168.1.0/24 or 2001:db8::/32. One computer is /32 (IPv4) or /128 (IPv6).
                </p>
                <p v-if="form.errors.ip_range" class="mt-1 text-sm text-red-700">{{ form.errors.ip_range }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editingId ? 'Save changes' : 'Add network' }}
                </button>
                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4" @click="fillMyAddress">Use my address</button>
                <button v-if="editingId" type="button" class="min-h-11 rounded border border-gray-300 bg-white px-4" @click="cancel">Cancel</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-gray-600" role="status">{{ networks.total }} networks</p>

        <p v-if="networks.data.length === 0" class="mt-2">No networks yet.</p>

        <div v-else class="mt-2 hidden overflow-x-auto md:block">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Address range</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="network in networks.data" :key="network.id" class="border-b border-gray-200">
                        <td class="py-2 pr-3">{{ network.name }}</td>
                        <td class="py-2 pr-3 font-mono">{{ network.ip_range }}</td>
                        <td class="py-2 pr-3">{{ network.is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="py-2">
                            <div class="flex gap-2">
                                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="edit(network)">Edit</button>
                                <button type="button" class="min-h-11 rounded border border-gray-300 bg-white px-3" @click="toggle(network)">
                                    {{ network.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="networks.data.length > 0" class="mt-2 space-y-3 md:hidden">
            <li v-for="network in networks.data" :key="network.id" class="rounded border border-gray-200 bg-white p-3">
                <p class="font-medium">{{ network.name }}</p>
                <p class="font-mono text-sm">{{ network.ip_range }}</p>
                <p class="mt-1 text-sm">Status: {{ network.is_active ? 'Active' : 'Inactive' }}</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="edit(network)">Edit</button>
                    <button type="button" class="min-h-11 rounded border border-gray-300 px-3" @click="toggle(network)">
                        {{ network.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </li>
        </ul>

        <PagerLinks :meta="networks" />
    </div>
</template>