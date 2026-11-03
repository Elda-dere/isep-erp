<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';

const phase = ref('starting'); // starting | scanning | working | shown | stopped
const result = ref(null);
const problem = ref('');

let scanner = null;
let busy = false;
let timer = null;

function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function send(token) {
    try {
        const response = await fetch('/attendance/scan', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ token }),
        });

        if (response.status === 401 || response.status === 419) {
            return { ok: false, message: 'Your session has expired. Sign in again.' };
        }

        if (response.status === 403) {
            return { ok: false, message: 'This account is not allowed to scan.' };
        }

        if (response.status === 429) {
            return { ok: false, message: 'Too many scans. Wait a moment and try again.' };
        }

        const data = await response.json().catch(() => null);

        return data ?? { ok: false, message: 'The server gave an unexpected answer. Try again.' };
    } catch {
        return { ok: false, message: 'Could not reach the server. Check the connection and try again.' };
    }
}

async function onDecoded(text) {
    // One scan at a time: the camera sees the same code many times a second.
    if (busy) {
        return;
    }

    busy = true;
    phase.value = 'working';

    try {
        scanner?.pause(true);
    } catch {
        // Already paused.
    }

    result.value = await send(String(text).trim());
    phase.value = 'shown';

    timer = setTimeout(next, result.value.ok ? 2500 : 4000);
}

function next() {
    clearTimeout(timer);

    try {
        scanner?.resume();
    } catch {
        // The camera was stopped meanwhile.
    }

    result.value = null;
    phase.value = 'scanning';
    busy = false;
}

async function stopCamera() {
    clearTimeout(timer);

    if (!scanner) {
        return;
    }

    try {
        await scanner.stop();
    } catch {
        // It was not running.
    }

    try {
        scanner.clear();
    } catch {
        // Nothing to clear.
    }

    scanner = null;
}

async function startCamera() {
    problem.value = '';
    phase.value = 'starting';
    result.value = null;
    busy = false;

    // Browsers only give the camera to secure pages (https:// or localhost).
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        problem.value = 'insecure';
        phase.value = 'stopped';

        return;
    }

    try {
        await stopCamera();

        scanner = new Html5Qrcode('reader');

        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onDecoded,
            () => {},
        );

        phase.value = 'scanning';
    } catch {
        problem.value = 'camera';
        phase.value = 'stopped';
    }
}

onMounted(startCamera);
onBeforeUnmount(stopCamera);
</script>

<template>
    <Head title="Scanner" />

    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-bold">Scanner</h1>
        <p class="mt-1 text-sm text-gray-600">Hold the employee's phone in front of the camera.</p>

        <div id="reader" class="mt-4 w-full overflow-hidden rounded border border-gray-300 bg-black"></div>

        <p v-if="phase === 'starting'" class="mt-3 text-gray-600">Starting the camera...</p>
        <p v-if="phase === 'scanning'" class="mt-3 text-gray-600">Ready. Waiting for a code.</p>
        <p v-if="phase === 'working'" class="mt-3 text-gray-600">Checking the code...</p>

        <div role="status" aria-live="polite">
            <div v-if="phase === 'shown' && result" class="mt-3 rounded border p-4" :class="result.ok ? 'border-green-300 bg-green-50 text-green-900' : 'border-red-300 bg-red-50 text-red-900'">
                <p class="text-xl font-bold">{{ result.ok ? result.message : 'Not accepted' }}</p>
                <template v-if="result.ok">
                    <p class="mt-1 text-lg">{{ result.employee }}</p>
                    <p class="text-sm">{{ result.number }} &middot; {{ result.time }}</p>
                </template>
                <p v-else class="mt-1">{{ result.message }}</p>
            </div>
        </div>

        <div v-if="phase === 'shown'" class="mt-3">
            <button type="button" class="min-h-11 w-full rounded bg-gray-900 px-4 font-medium text-white" @click="next">Scan next</button>
        </div>

        <div v-if="phase === 'stopped'" role="alert" class="mt-3 rounded border border-amber-300 bg-amber-50 p-4 text-amber-900">
            <p v-if="problem === 'insecure'">
                The camera works only on a secure page. Open this site through an https:// address (or through localhost on this computer).
            </p>
            <p v-else>
                The camera could not start. Allow camera access for this site in your browser settings (the lock icon next to the address),
                close any other app that uses the camera, then try again.
            </p>
            <button type="button" class="mt-3 min-h-11 w-full rounded bg-gray-900 px-4 font-medium text-white" @click="startCamera">Try the camera again</button>
        </div>
    </div>
</template>