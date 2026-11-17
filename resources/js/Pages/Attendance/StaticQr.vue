<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';

const props = defineProps({
    token: String,
    enabled: Boolean,
    localIp: {
        type: String,
        default: '10.4.108.201',
    },
    availableIps: {
        type: Array,
        default: () => ['10.4.108.201'],
    },
});

const qrImage = ref('');
const drawError = ref(false);

// Host selection: default to local IP for mobile phone scanning
const selectedHost = ref(props.localIp ? `http://${props.localIp}` : 'http://isep-erp.test');
const customHost = ref('');
const isCustom = ref(false);

const activeBaseUrl = computed(() => {
    if (isCustom.value && customHost.value.trim()) {
        let h = customHost.value.trim();
        if (!h.startsWith('http://') && !h.startsWith('https://')) {
            h = 'http://' + h;
        }
        return h.replace(/\/+$/, '');
    }
    return selectedHost.value.replace(/\/+$/, '');
});

const fullCheckInUrl = computed(() => {
    return `${activeBaseUrl.value}/attendance/check-in?token=${props.token || ''}`;
});

async function generateQr() {
    if (!props.token) return;
    try {
        qrImage.value = await QRCode.toDataURL(fullCheckInUrl.value, {
            width: 360,
            margin: 2,
            errorCorrectionLevel: 'H',
            color: {
                dark: '#1C1E1D',
                light: '#FFFFFF',
            },
        });
        drawError.value = false;
    } catch {
        drawError.value = true;
    }
}

function printPoster() {
    window.print();
}

onMounted(generateQr);
watch([() => props.token, fullCheckInUrl], generateQr);
</script>

<template>
    <Head title="Static Attendance QR Poster" />

    <div class="min-h-full max-w-2xl mx-auto py-4">
        <!-- Top Controls (hidden when printing) -->
        <div class="print:hidden space-y-4 mb-6">
            <div class="flex items-center justify-between">
                <Link
                    href="/attendance/modes"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 16l-6-6 6-6"/></svg>
                    Back to Capture Modes
                </Link>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-xs transition-colors"
                    @click="printPoster"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h10v3H5V4zM5 14v2h10v-2M4 7h12a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V8a1 1 0 011-1z"/></svg>
                    Print Official Poster
                </button>
            </div>

            <!-- Network Target Selector (Solves Safari "server can't be found" on iPhone) -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-amber-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 7l8-4 8 4v6l-8 4-8-4V7z"/></svg>
                        Network Address for Mobile Phones
                    </span>
                    <span class="text-[11px] text-amber-800">Phones on Wi-Fi must use IP address</span>
                </div>

                <div class="flex flex-wrap gap-2 text-xs">
                    <button
                        v-for="ip in availableIps"
                        :key="ip"
                        type="button"
                        class="rounded-lg px-3 py-1.5 font-medium border transition-colors"
                        :class="!isCustom && selectedHost === `http://${ip}` ? 'bg-forest text-white border-forest' : 'bg-surface text-ink border-border hover:bg-stone'"
                        @click="() => { isCustom = false; selectedHost = `http://${ip}`; }"
                    >
                        Wi-Fi IP: {{ ip }} (Recommended)
                    </button>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 font-medium border transition-colors"
                        :class="!isCustom && selectedHost === 'http://isep-erp.test' ? 'bg-forest text-white border-forest' : 'bg-surface text-ink border-border hover:bg-stone'"
                        @click="() => { isCustom = false; selectedHost = 'http://isep-erp.test'; }"
                    >
                        Domain: isep-erp.test (PC only)
                    </button>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 font-medium border transition-colors"
                        :class="isCustom ? 'bg-forest text-white border-forest' : 'bg-surface text-ink border-border hover:bg-stone'"
                        @click="isCustom = true"
                    >
                        Custom IP / Port
                    </button>
                </div>

                <div v-if="isCustom" class="mt-2.5 flex items-center gap-2">
                    <input
                        v-model="customHost"
                        type="text"
                        placeholder="e.g. http://10.4.108.201:8000"
                        class="flex-1 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs text-ink focus:outline-none focus:ring-2 focus:ring-forest/30"
                    />
                </div>

                <p class="text-[11px] text-amber-800 mt-2">
                    Scanning target: <strong class="font-mono">{{ fullCheckInUrl }}</strong>
                </p>
            </div>
        </div>

        <!-- Poster Printable Card -->
        <div class="rounded-3xl border-2 border-border bg-surface p-8 sm:p-12 shadow-sm text-center print:border-none print:shadow-none print:p-0">
            <!-- Company / System Brand Header -->
            <div class="flex items-center justify-center gap-3 mb-6">
                <div class="h-10 w-10 rounded-xl bg-forest text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    IE
                </div>
                <div class="text-left">
                    <h1 class="text-xl font-bold text-ink tracking-tight">Integrated ERP</h1>
                    <p class="text-xs text-ink-muted font-medium">Official Office Attendance Point</p>
                </div>
            </div>

            <div class="my-6">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-ink tracking-tight">
                    Scan to Check In / Out
                </h2>
                <p class="text-sm text-ink-muted mt-2 max-w-md mx-auto">
                    Open your iPhone or Android camera, point it at the code, and tap the link to record attendance.
                </p>
            </div>

            <!-- Status Indicator -->
            <div class="mb-6">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="enabled ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                >
                    <span class="h-2 w-2 rounded-full" :class="enabled ? 'bg-emerald-500' : 'bg-red-500'" />
                    {{ enabled ? 'Active Station QR' : 'Station Disabled' }}
                </span>
            </div>

            <!-- QR Code Display -->
            <div class="inline-block rounded-2xl border-4 border-forest/15 bg-white p-4 shadow-sm">
                <img
                    v-if="qrImage"
                    :src="qrImage"
                    alt="Official Static Attendance QR Code"
                    class="h-72 w-72 sm:h-80 sm:w-80 mx-auto"
                />
                <div v-else class="h-72 w-72 flex items-center justify-center text-ink-faint">
                    Generating QR code…
                </div>
            </div>

            <!-- Check-in URL display -->
            <div class="mt-6">
                <p class="text-xs text-ink-muted uppercase tracking-wider font-semibold">Live Check-in Link</p>
                <a
                    :href="fullCheckInUrl"
                    target="_blank"
                    class="font-mono text-xs text-forest hover:underline bg-stone/50 py-1.5 px-3 rounded-lg inline-block mt-1 max-w-full truncate"
                >
                    {{ fullCheckInUrl }}
                </a>
            </div>

            <!-- Footer Instructions -->
            <div class="mt-8 pt-6 border-t border-border grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">1. Open Camera</span>
                    <span class="text-[11px] text-ink-muted">Open your smartphone camera (iPhone / Android).</span>
                </div>
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">2. Tap Notification</span>
                    <span class="text-[11px] text-ink-muted">Tap the yellow/yellow banner linking to {{ activeBaseUrl }}.</span>
                </div>
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">3. Instant Record</span>
                    <span class="text-[11px] text-ink-muted">Your arrival time is automatically logged and confirmed!</span>
                </div>
            </div>
        </div>
    </div>
</template>
