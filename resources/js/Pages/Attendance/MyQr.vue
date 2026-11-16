<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import QRCode from 'qrcode';

const props = defineProps({
    state: String,
    employeeName: String,
    message: String,
    qr: Object,
});

const image = ref('');
const secondsLeft = ref(0);
const refreshing = ref(false);
const drawFailed = ref(false);

let deadline = 0;
let timer = null;
let wakeLock = null;
let lastAttempt = 0;

const expired = computed(() => props.state === 'ready' && secondsLeft.value <= 0);
const progress = computed(() => props.qr ? Math.round((secondsLeft.value / props.qr.lifetime) * 100) : 0);
const progressColor = computed(() => {
    if (progress.value > 50) return '#1E463A';
    if (progress.value > 25) return '#B45309';
    return '#DC2626';
});

async function draw() {
    if (props.state !== 'ready' || !props.qr) return;
    deadline = Date.now() + props.qr.expires_in * 1000;
    drawFailed.value = false;

    try {
        image.value = await QRCode.toDataURL(props.qr.token, {
            width: 280,
            margin: 2,
            errorCorrectionLevel: 'M',
            color: { dark: '#1C1E1D', light: '#FFFFFF' },
        });
    } catch {
        drawFailed.value = true;
    }
    tick();
}

function refresh(force = false) {
    if (refreshing.value) return;
    if (!force && Date.now() - lastAttempt < 5000) return;
    lastAttempt = Date.now();
    refreshing.value = true;
    router.reload({ only: ['qr'], onFinish: () => { refreshing.value = false; } });
}

function tick() {
    secondsLeft.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    if (props.state === 'ready' && props.qr && secondsLeft.value <= Math.ceil(props.qr.lifetime / 3)) {
        refresh();
    }
}

async function keepAwake() {
    try {
        if ('wakeLock' in navigator && !wakeLock) {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => { wakeLock = null; });
        }
    } catch {}
}

function onVisible() {
    if (document.visibilityState === 'visible') { keepAwake(); tick(); }
}

watch(() => props.qr, draw);

onMounted(() => {
    draw();
    timer = setInterval(tick, 1000);
    keepAwake();
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
    wakeLock?.release();
});
</script>

<template>
    <Head title="My QR Code" />

    <div class="min-h-full">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-ink tracking-tight">My QR Code</h1>
            <p class="text-sm text-ink-muted mt-0.5">Show this code at the scanner to record your attendance.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl">
            <!-- QR Card -->
            <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h2 class="text-sm font-semibold text-ink">Your Attendance QR</h2>
                    <p class="text-xs text-ink-muted mt-0.5">{{ employeeName }}</p>
                </div>

                <div class="p-6 flex flex-col items-center">
                    <!-- Not ready -->
                    <div v-if="state !== 'ready'" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-center w-full">
                        <svg class="h-10 w-10 text-amber-500 mx-auto mb-2" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M10 6v4l2 2"/></svg>
                        <p class="text-sm font-semibold text-amber-900">{{ message }}</p>
                    </div>

                    <!-- QR Code -->
                    <template v-else>
                        <div
                            class="rounded-2xl p-4 border-2 transition-colors"
                            :class="expired ? 'border-red-300 bg-red-50' : 'border-forest/20 bg-white'"
                        >
                            <img
                                v-if="image && !expired"
                                :src="image"
                                alt="QR code for attendance"
                                class="h-64 w-64 rounded-lg"
                            />
                            <div v-else-if="drawFailed" class="h-64 w-64 flex items-center justify-center rounded-lg bg-red-50">
                                <p class="text-sm text-red-600 text-center px-4">Could not draw QR. Please reload.</p>
                            </div>
                            <div v-else-if="expired" class="h-64 w-64 flex flex-col items-center justify-center rounded-lg bg-red-50 gap-2">
                                <svg class="h-10 w-10 text-red-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M10 6v4l2 2"/></svg>
                                <p class="text-sm text-red-600 font-medium">Code expired</p>
                                <p class="text-xs text-red-500">Refreshing…</p>
                            </div>
                            <div v-else class="h-64 w-64 flex items-center justify-center">
                                <svg class="h-8 w-8 text-ink-faint animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.2"/><path d="M12 2a10 10 0 019.8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            </div>
                        </div>

                        <!-- Countdown bar -->
                        <div class="w-full mt-4 max-w-xs">
                            <div class="flex justify-between text-xs text-ink-muted mb-1">
                                <span>Expires in</span>
                                <span class="font-mono font-semibold" :class="secondsLeft <= 10 ? 'text-red-600' : 'text-ink'">
                                    {{ secondsLeft }}s
                                </span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-stone overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-1000"
                                    :style="{ width: progress + '%', backgroundColor: progressColor }"
                                ></div>
                            </div>
                            <p class="text-[11px] text-ink-muted mt-2 text-center">
                                {{ refreshing ? 'Getting new code…' : 'A new code appears automatically before this one expires.' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink hover:bg-stone transition-colors"
                            :disabled="refreshing"
                            @click="refresh(true)"
                        >
                            <svg class="h-4 w-4 text-ink-muted" :class="{ 'animate-spin': refreshing }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M4 4v5h5M16 16v-5h-5M4.1 9A7 7 0 0116 15.9M19.9 11A7 7 0 018 4.1"/></svg>
                            {{ refreshing ? 'Refreshing…' : 'Get new code now' }}
                        </button>
                    </template>
                </div>
            </div>

            <!-- Instructions Card -->
            <div class="rounded-xl border border-border bg-surface shadow-xs p-5">
                <h3 class="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                    <svg class="h-4 w-4 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M10 9v5M10 7h.01"/></svg>
                    How to check in
                </h3>

                <ol class="space-y-4">
                    <li class="flex gap-3">
                        <div class="h-7 w-7 rounded-full bg-soft-green text-forest text-xs font-bold flex items-center justify-center shrink-0">1</div>
                        <div>
                            <p class="text-sm font-medium text-ink">Open this page on your phone</p>
                            <p class="text-xs text-ink-muted mt-0.5">Make sure your screen brightness is turned up</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <div class="h-7 w-7 rounded-full bg-soft-green text-forest text-xs font-bold flex items-center justify-center shrink-0">2</div>
                        <div>
                            <p class="text-sm font-medium text-ink">Hold your phone to the scanner</p>
                            <p class="text-xs text-ink-muted mt-0.5">The kiosk scanner reads your QR automatically</p>
                        </div>
                    </li>
                    <li class="flex gap-3">
                        <div class="h-7 w-7 rounded-full bg-soft-green text-forest text-xs font-bold flex items-center justify-center shrink-0">3</div>
                        <div>
                            <p class="text-sm font-medium text-ink">Watch for the green confirmation</p>
                            <p class="text-xs text-ink-muted mt-0.5">Your name, ID and time will be shown on the kiosk</p>
                        </div>
                    </li>
                </ol>

                <div class="mt-5 rounded-lg bg-soft-green border border-forest/10 p-3">
                    <p class="text-xs text-forest font-semibold mb-1">🔒 Security</p>
                    <p class="text-xs text-forest/80">Each code works <strong>once only</strong> and expires automatically. Screenshots cannot be reused.</p>
                </div>
            </div>
        </div>
    </div>
</template>
