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

async function draw() {
    if (props.state !== 'ready' || !props.qr) {
        return;
    }

    // The countdown uses this phone's own clock, so a wrong phone clock cannot break it.
    deadline = Date.now() + props.qr.expires_in * 1000;
    drawFailed.value = false;

    try {
        image.value = await QRCode.toDataURL(props.qr.token, {
            width: 288,
            margin: 2,
            errorCorrectionLevel: 'M',
        });
    } catch {
        drawFailed.value = true;
    }

    tick();
}

function refresh(force = false) {
    if (refreshing.value) {
        return;
    }

    // Wait 5 seconds between automatic tries, for example while the phone is offline.
    if (!force && Date.now() - lastAttempt < 5000) {
        return;
    }

    lastAttempt = Date.now();
    refreshing.value = true;

    router.reload({
        only: ['qr'],
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

function tick() {
    secondsLeft.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));

    // Ask for a new code while about a third of the life is left, so the old one never runs out at the scanner.
    if (props.state === 'ready' && props.qr && secondsLeft.value <= Math.ceil(props.qr.lifetime / 3)) {
        refresh();
    }
}

async function keepAwake() {
    try {
        if ('wakeLock' in navigator && !wakeLock) {
            wakeLock = await navigator.wakeLock.request('screen');
            wakeLock.addEventListener('release', () => {
                wakeLock = null;
            });
        }
    } catch {
        // Not allowed here (for example over plain HTTP). The code still works.
    }
}

function onVisible() {
    if (document.visibilityState === 'visible') {
        keepAwake();
        tick();
    }
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
    <Head title="My QR" />

    <div class="mx-auto max-w-sm text-center">
        <h1 class="text-2xl font-bold">My QR</h1>

        <p v-if="state !== 'ready'" role="alert" class="mt-4 rounded bg-amber-50 p-3 text-amber-900">{{ message }}</p>

        <template v-else>
            <p class="mt-1 text-gray-600">{{ employeeName }}</p>

            <div class="mt-4 flex min-h-72 items-center justify-center rounded border border-gray-200 bg-white p-2">
                <img v-if="image && !expired" :src="image" alt="QR code for check-in" class="h-72 w-72" />
                <p v-else-if="drawFailed" role="alert" class="p-4 text-red-700">The code could not be drawn. Reload the page.</p>
                <p v-else-if="expired" role="alert" class="p-4">This code has expired.</p>
                <p v-else class="p-4 text-gray-600">Preparing your code...</p>
            </div>

            <p class="mt-3">
                <span v-if="!expired">Valid for {{ secondsLeft }} seconds. A new code appears by itself.</span>
                <span v-else>Reconnecting...</span>
            </p>

            <button type="button" class="mt-3 min-h-11 rounded border border-gray-300 bg-white px-4" @click="refresh(true)">
                Get a new code now
            </button>

            <p class="mt-4 text-sm text-gray-600">
                Hold your phone up to the scanner. Each code works once, so a screenshot is useless.
            </p>
        </template>
    </div>
</template>