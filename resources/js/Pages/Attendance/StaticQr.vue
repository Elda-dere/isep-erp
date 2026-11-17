<script setup>
import { onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';

const props = defineProps({
    token: String,
    enabled: Boolean,
});

const qrImage = ref('');
const drawError = ref(false);

async function generateQr() {
    if (!props.token) return;
    try {
        qrImage.value = await QRCode.toDataURL(props.token, {
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
watch(() => props.token, generateQr);
</script>

<template>
    <Head title="Static Attendance QR Poster" />

    <div class="min-h-full max-w-2xl mx-auto py-4">
        <!-- Top Controls (hidden when printing) -->
        <div class="print:hidden flex items-center justify-between mb-6">
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
                    Point your smartphone camera or attendance scanner at the QR code below.
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

            <!-- Token fingerprint -->
            <div class="mt-6">
                <p class="text-xs text-ink-muted uppercase tracking-wider font-semibold">Station Security Token</p>
                <p class="font-mono text-xs text-ink bg-stone/50 py-1.5 px-3 rounded-lg inline-block mt-1 max-w-full truncate">
                    {{ token }}
                </p>
            </div>

            <!-- Footer Instructions -->
            <div class="mt-8 pt-6 border-t border-border grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">1. Open Camera</span>
                    <span class="text-[11px] text-ink-muted">Use your phone's camera app or the ERP scanner kiosk.</span>
                </div>
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">2. Hold in View</span>
                    <span class="text-[11px] text-ink-muted">Hold your screen 10-15cm away from the QR poster.</span>
                </div>
                <div class="bg-stone/30 rounded-xl p-3">
                    <span class="font-bold text-xs text-forest block mb-0.5">3. Instant Record</span>
                    <span class="text-[11px] text-ink-muted">Confirmation displays with your timestamp instantly.</span>
                </div>
            </div>
        </div>
    </div>
</template>
