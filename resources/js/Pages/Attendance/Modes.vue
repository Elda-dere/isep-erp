<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import FlashMessage from '../../Components/FlashMessage.vue';

const props = defineProps({
    settings: Object,
    salaryMethods: Array,
    branches: Array,
    devices: Array,
});

// Settings form
const selectedMethod = ref(props.settings?.salary_calculation_method || 'Fixed 30 Days Month');
const savingSettings = ref(false);

function saveAllSettings() {
    savingSettings.value = true;
    router.post(
        '/attendance/modes/settings',
        { salary_calculation_method: selectedMethod.value },
        {
            preserveScroll: true,
            onFinish: () => {
                savingSettings.value = false;
            },
        },
    );
}

// Token & Mode Actions
const generatingToken = ref(false);
const togglingMode = ref(null);

function generateNewToken() {
    if (!confirm('Generate a new Static QR token? This will invalidate the previous poster/token.')) return;
    generatingToken.value = true;
    router.post(
        '/attendance/modes/static-token',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                generatingToken.value = false;
            },
        },
    );
}

function toggleMode(mode) {
    togglingMode.value = mode;
    router.post(
        '/attendance/modes/toggle',
        { mode },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingMode.value = null;
            },
        },
    );
}

// Modals
const showDeviceModal = ref(false);
const deviceForm = ref({
    name: '',
    serial_number: '',
    branch_id: props.branches?.[0]?.id || '',
    ip_address: '',
    status: 'active',
});
const savingDevice = ref(false);

function submitDevice() {
    savingDevice.value = true;
    router.post('/attendance/modes/devices', deviceForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showDeviceModal.value = false;
            deviceForm.value = {
                name: '',
                serial_number: '',
                branch_id: props.branches?.[0]?.id || '',
                ip_address: '',
                status: 'active',
            };
        },
        onFinish: () => {
            savingDevice.value = false;
        },
    });
}

function deleteDevice(id) {
    if (!confirm('Are you sure you want to delete this biometric device?')) return;
    router.delete(`/attendance/modes/devices/${id}`, { preserveScroll: true });
}

function syncDevice(id) {
    router.post(`/attendance/modes/devices/${id}/sync`, {}, { preserveScroll: true });
}

const showBranchModal = ref(false);
const branchForm = ref({
    branch_id: '',
    name: '',
    location: '',
});
const savingBranch = ref(false);

function submitBranch() {
    savingBranch.value = true;
    router.post('/attendance/modes/branches', branchForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showBranchModal.value = false;
            branchForm.value = { branch_id: '', name: '', location: '' };
        },
        onFinish: () => {
            savingBranch.value = false;
        },
    });
}

function deleteBranch(id) {
    if (!confirm('Are you sure you want to remove this branch?')) return;
    router.delete(`/attendance/modes/branches/${id}`, { preserveScroll: true });
}

// Copy token helper
const copied = ref(false);
function copyToken(token) {
    if (!token) return;
    navigator.clipboard.writeText(token);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}
</script>

<template>
    <Head title="Attendance Capture Modes" />

    <div class="min-h-full space-y-6">
        <FlashMessage />

        <!-- ========================================== -->
        <!-- Top Section: Salary Calculation Method      -->
        <!-- ========================================== -->
        <div class="rounded-xl border border-border bg-surface p-5 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                <div class="flex-1 max-w-xl">
                    <label for="salary-method" class="block text-sm font-semibold text-ink mb-1.5">
                        Salary Calculation Method
                    </label>
                    <select
                        id="salary-method"
                        v-model="selectedMethod"
                        class="w-full rounded-lg border border-border bg-stone/40 px-3.5 py-2.5 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30 transition"
                    >
                        <option v-for="method in salaryMethods" :key="method" :value="method">
                            {{ method }}
                        </option>
                    </select>
                </div>

                <button
                    type="button"
                    :disabled="savingSettings"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-xs transition-colors shrink-0 disabled:opacity-50"
                    @click="saveAllSettings"
                >
                    <svg v-if="savingSettings" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.25"/><path d="M12 2a10 10 0 019.8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Save All Settings
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- Section 1: Attendance Capture Modes        -->
        <!-- ========================================== -->
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-border bg-stone/20">
                <h2 class="text-base font-bold text-ink tracking-tight">Attendance Capture Modes</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-stone/30 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 text-left">Capture Mode</th>
                            <th class="px-5 py-3 text-center w-36">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Row 1: Static QR -->
                        <tr class="hover:bg-stone/20 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-ink text-sm">Static QR</div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-ink-muted">
                                    <span>Token:</span>
                                    <span class="font-mono bg-stone px-2 py-0.5 rounded text-[11px] text-ink select-all max-w-[280px] sm:max-w-md truncate">
                                        {{ settings.static_qr_token || 'No token generated' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-xs text-forest hover:underline font-medium ml-1"
                                        title="Copy token"
                                        @click="copyToken(settings.static_qr_token)"
                                    >
                                        {{ copied ? 'Copied!' : 'Copy' }}
                                    </button>
                                </div>
                            </td>

                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <span
                                    class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold text-white shadow-xs"
                                    :class="settings.static_qr_enabled ? 'bg-red-500' : 'bg-gray-400'"
                                >
                                    {{ settings.static_qr_enabled ? 'enabled' : 'disabled' }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <!-- New Token Button (Orange) -->
                                    <button
                                        type="button"
                                        :disabled="generatingToken"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors disabled:opacity-50"
                                        @click="generateNewToken"
                                    >
                                        <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': generatingToken }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h5M16 16v-5h-5M4.1 9A7 7 0 0116 15.9M19.9 11A7 7 0 018 4.1"/></svg>
                                        New Token
                                    </button>

                                    <!-- Disable / Enable Button (Dark Pill) -->
                                    <button
                                        type="button"
                                        :disabled="togglingMode === 'static'"
                                        class="inline-flex items-center gap-1 rounded-lg bg-slate-800 hover:bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors"
                                        @click="toggleMode('static')"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="7"/><path d="M10 7v3"/></svg>
                                        {{ settings.static_qr_enabled ? 'Disable' : 'Enable' }}
                                    </button>

                                    <!-- View Page (Green Pill) -->
                                    <Link
                                        href="/attendance/static-qr"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 4.5h6.5v6.5M15.5 4.5L8 12"/></svg>
                                        View Page
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 2: Dynamic QR -->
                        <tr class="hover:bg-stone/20 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-ink text-sm">Dynamic QR</div>
                                <div class="text-xs text-ink-muted mt-0.5">Rotating single-use QR tokens refreshing every 60s for individual staff mobile check-in</div>
                            </td>

                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <span
                                    class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold text-white shadow-xs"
                                    :class="settings.dynamic_qr_enabled ? 'bg-red-500' : 'bg-gray-400'"
                                >
                                    {{ settings.dynamic_qr_enabled ? 'enabled' : 'disabled' }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <!-- Disable / Enable Button (Dark Pill) -->
                                    <button
                                        type="button"
                                        :disabled="togglingMode === 'dynamic'"
                                        class="inline-flex items-center gap-1 rounded-lg bg-slate-800 hover:bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors"
                                        @click="toggleMode('dynamic')"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="7"/><path d="M10 7v3"/></svg>
                                        {{ settings.dynamic_qr_enabled ? 'Disable' : 'Enable' }}
                                    </button>

                                    <!-- View Page (Green Pill) -->
                                    <Link
                                        href="/my-qr"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 4.5h6.5v6.5M15.5 4.5L8 12"/></svg>
                                        View Page
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- Section 2: eSSL Biometric Devices          -->
        <!-- ========================================== -->
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-stone/20">
                <h2 class="text-base font-bold text-ink tracking-tight">eSSL Biometric Devices</h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition-colors"
                    @click="showDeviceModal = true"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                    Add Device
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-stone/30 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 text-left">Device Name</th>
                            <th class="px-5 py-3 text-left">Serial Number (SN)</th>
                            <th class="px-5 py-3 text-left">Branch</th>
                            <th class="px-5 py-3 text-left">Last Sync</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <!-- Empty State matching image exactly -->
                        <tr v-if="devices.length === 0">
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-ink-muted">
                                No eSSL biometric devices added yet.
                            </td>
                        </tr>

                        <!-- Rows if devices exist -->
                        <tr v-for="dev in devices" :key="dev.id" class="hover:bg-stone/20 transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-ink">{{ dev.name }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs text-ink-muted">{{ dev.serial_number }}</td>
                            <td class="px-5 py-3.5 text-xs text-ink-muted">{{ dev.branch }}</td>
                            <td class="px-5 py-3.5 text-xs text-ink-muted">{{ dev.last_sync }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                    :class="dev.status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-stone text-ink-muted'"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="dev.status === 'active' ? 'bg-emerald-500' : 'bg-ink-faint'" />
                                    {{ dev.status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded px-2 py-1 text-xs font-medium text-forest hover:bg-stone transition-colors"
                                        title="Trigger sync"
                                        @click="syncDevice(dev.id)"
                                    >
                                        Sync Now
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors"
                                        title="Delete device"
                                        @click="deleteDevice(dev.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- Section 3: Branches                        -->
        <!-- ========================================== -->
        <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-stone/20">
                <h2 class="text-base font-bold text-ink tracking-tight">Branches</h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition-colors"
                    @click="showBranchModal = true"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 4v12M4 10h12"/></svg>
                    Add Branch
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-stone/30 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">
                            <th class="px-5 py-3 text-left">Branch ID</th>
                            <th class="px-5 py-3 text-left">Branch Name</th>
                            <th class="px-5 py-3 text-left">Location</th>
                            <th class="px-5 py-3 text-center">Devices</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-if="branches.length === 0">
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-ink-muted">
                                No branches added yet.
                            </td>
                        </tr>

                        <tr v-for="branch in branches" :key="branch.id" class="hover:bg-stone/20 transition-colors">
                            <td class="px-5 py-3.5 font-mono text-xs font-semibold text-ink-muted">
                                {{ branch.branch_id }}
                            </td>
                            <td class="px-5 py-3.5 font-semibold text-ink">
                                {{ branch.name }}
                            </td>
                            <td class="px-5 py-3.5 text-xs text-ink-muted">
                                {{ branch.location || '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center text-xs">
                                <span class="bg-stone px-2 py-0.5 rounded font-mono">{{ branch.devices_count || 0 }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors"
                                    @click="deleteBranch(branch.id)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- Add Device Modal                           -->
        <!-- ========================================== -->
        <div
            v-if="showDeviceModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 shadow-xl">
                <h3 class="text-lg font-bold text-ink mb-4">Register eSSL Biometric Device</h3>

                <form class="space-y-4" @submit.prevent="submitDevice">
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Device Name</label>
                        <input
                            v-model="deviceForm.name"
                            type="text"
                            required
                            placeholder="e.g. Main Reception Terminal"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Serial Number (SN)</label>
                        <input
                            v-model="deviceForm.serial_number"
                            type="text"
                            required
                            placeholder="e.g. ESSL-K90-992817"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm font-mono text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Branch</label>
                        <select
                            v-model="deviceForm.branch_id"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        >
                            <option value="">No Branch Assigned</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">
                                {{ b.name }} ({{ b.branch_id }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">IP Address (Optional)</label>
                        <input
                            v-model="deviceForm.ip_address"
                            type="text"
                            placeholder="e.g. 192.168.1.120"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm font-mono text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <button
                            type="button"
                            class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-stone transition-colors"
                            @click="showDeviceModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="savingDevice"
                            class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors disabled:opacity-50"
                        >
                            {{ savingDevice ? 'Registering…' : 'Register Device' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- Add Branch Modal                           -->
        <!-- ========================================== -->
        <div
            v-if="showBranchModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/40 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-2xl border border-border bg-surface p-6 shadow-xl">
                <h3 class="text-lg font-bold text-ink mb-4">Add Company Branch</h3>

                <form class="space-y-4" @submit.prevent="submitBranch">
                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Branch ID</label>
                        <input
                            v-model="branchForm.branch_id"
                            type="text"
                            required
                            placeholder="e.g. BR-003"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm font-mono text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Branch Name</label>
                        <input
                            v-model="branchForm.name"
                            type="text"
                            required
                            placeholder="e.g. Hawassa Regional Office"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink mb-1">Location / Address</label>
                        <input
                            v-model="branchForm.location"
                            type="text"
                            placeholder="e.g. Industrial Park, Hawassa"
                            class="w-full rounded-lg border border-border bg-stone/40 px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                        <button
                            type="button"
                            class="rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-stone transition-colors"
                            @click="showBranchModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="savingBranch"
                            class="rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors disabled:opacity-50"
                        >
                            {{ savingBranch ? 'Saving…' : 'Add Branch' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
