<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';

const phase = ref('starting'); // starting | scanning | working | shown | stopped
const result = ref(null);
const problem = ref('');
const manualToken = ref('');
const showManual = ref(false);
const manualLoading = ref(false);

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

        if (response.status === 401 || response.status === 419) return { ok: false, message: 'Session expired. Sign in again.' };
        if (response.status === 403) return { ok: false, message: 'This account is not allowed to scan.' };
        if (response.status === 429) return { ok: false, message: 'Too many scans. Wait a moment and try again.' };

        const data = await response.json().catch(() => null);
        return data ?? { ok: false, message: 'Unexpected server response.' };
    } catch {
        return { ok: false, message: 'Could not reach the server. Check your connection.' };
    }
}

async function onDecoded(text) {
    if (busy) return;
    busy = true;
    phase.value = 'working';

    try { scanner?.pause(true); } catch {}

    result.value = await send(String(text).trim());
    phase.value = 'shown';
    timer = setTimeout(next, result.value.ok ? 2500 : 4000);
}

function next() {
    clearTimeout(timer);
    try { scanner?.resume(); } catch {}
    result.value = null;
    phase.value = 'scanning';
    busy = false;
}

async function stopCamera() {
    clearTimeout(timer);
    if (!scanner) return;
    try { await scanner.stop(); } catch {}
    try { scanner.clear(); } catch {}
    scanner = null;
}

async function startCamera() {
    problem.value = '';
    phase.value = 'starting';
    result.value = null;
    busy = false;

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        problem.value = 'insecure';
        phase.value = 'stopped';
        return;
    }

    try {
        await stopCamera();
        scanner = new Html5Qrcode('qr-reader');
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            onDecoded,
            () => {},
        );
        phase.value = 'scanning';
    } catch {
        problem.value = 'camera';
        phase.value = 'stopped';
    }
}

async function submitManual() {
    const token = manualToken.value.trim();
    if (!token) return;
    manualLoading.value = true;
    result.value = await send(token);
    manualLoading.value = false;
    manualToken.value = '';
    phase.value = 'shown';
    timer = setTimeout(() => {
        result.value = null;
        phase.value = scanner ? 'scanning' : 'stopped';
    }, result.value.ok ? 2500 : 4000);
}

onMounted(startCamera);
onBeforeUnmount(stopCamera);
</script>

<template>
    <Head title="QR Attendance Scanner" />

    <div class="min-h-full">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-ink tracking-tight">QR Attendance Scanner</h1>
            <p class="text-sm text-ink-muted mt-0.5">Point the camera at an employee's QR code to record check-in / check-out.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Camera Card -->
            <div class="lg:col-span-2 rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center gap-2">
                    <div class="h-2 w-2 rounded-full" :class="phase === 'scanning' ? 'bg-emerald-500 animate-pulse' : 'bg-ink-faint'"></div>
                    <h2 class="text-sm font-semibold text-ink">
                        {{ phase === 'starting' ? 'Starting camera…' : phase === 'scanning' ? 'Camera Live — Ready to Scan' : phase === 'working' ? 'Processing…' : phase === 'shown' ? 'Scan Complete' : 'Camera Stopped' }}
                    </h2>
                </div>

                <!-- Camera viewport -->
                <div class="p-5">
                    <div
                        id="qr-reader"
                        class="w-full max-w-md mx-auto rounded-xl overflow-hidden border-2 transition-colors"
                        :class="{
                            'border-border bg-ink/5': phase === 'starting' || phase === 'stopped',
                            'border-forest/40': phase === 'scanning',
                            'border-amber-400': phase === 'working',
                            'border-emerald-400': phase === 'shown' && result?.ok,
                            'border-red-400': phase === 'shown' && !result?.ok,
                        }"
                        style="min-height: 300px;"
                    ></div>

                    <!-- Status messages -->
                    <div class="mt-4 max-w-md mx-auto" role="status" aria-live="polite">
                        <!-- Working -->
                        <div v-if="phase === 'working'" class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <svg class="h-5 w-5 text-amber-500 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" opacity="0.25"/><path d="M12 2a10 10 0 019.8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <p class="text-sm font-medium text-amber-800">Verifying QR code…</p>
                        </div>

                        <!-- Result -->
                        <div
                            v-if="phase === 'shown' && result"
                            class="rounded-xl border px-5 py-4 flex items-start gap-4"
                            :class="result.ok ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'"
                        >
                            <div
                                class="h-10 w-10 rounded-full flex items-center justify-center shrink-0"
                                :class="result.ok ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'"
                            >
                                <svg v-if="result.ok" class="h-6 w-6" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 10l4 4 7-7"/></svg>
                                <svg v-else class="h-6 w-6" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l8 8M14 6l-8 8"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-base" :class="result.ok ? 'text-emerald-800' : 'text-red-800'">
                                    {{ result.ok ? result.message : 'Not Accepted' }}
                                </p>
                                <template v-if="result.ok">
                                    <p class="text-sm text-emerald-700 mt-0.5">{{ result.employee }}</p>
                                    <p class="text-xs text-emerald-600 font-mono mt-0.5">{{ result.number }} · {{ result.time }}</p>
                                </template>
                                <p v-else class="text-sm text-red-700 mt-0.5">{{ result.message }}</p>
                            </div>
                        </div>

                        <!-- Camera stopped -->
                        <div v-if="phase === 'stopped'" class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                            <p class="font-semibold text-amber-900 mb-1">Camera unavailable</p>
                            <p v-if="problem === 'insecure'" class="text-sm text-amber-800">
                                The camera only works over <strong>https://</strong> or <strong>localhost</strong>. Open this page via a secure URL.
                            </p>
                            <p v-else class="text-sm text-amber-800">
                                Allow camera access in your browser settings (click the lock icon in the address bar), close any other app using the camera, then try again.
                            </p>
                            <button
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 rounded-lg bg-forest px-4 py-2 text-sm font-semibold text-white hover:bg-forest-hover transition-colors"
                                @click="startCamera"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h5M16 16v-5h-5M4.1 9A7 7 0 0116 15.9M19.9 11A7 7 0 018 4.1"/></svg>
                                Retry Camera
                            </button>
                        </div>
                    </div>

                    <!-- Scan Next button -->
                    <div v-if="phase === 'shown'" class="mt-4 max-w-md mx-auto">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-forest px-4 py-2.5 text-sm font-semibold text-white hover:bg-forest-hover transition-colors"
                            @click="next"
                        >
                            Scan Next Employee
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Panel -->
            <div class="flex flex-col gap-4">
                <!-- How it works -->
                <div class="rounded-xl border border-border bg-surface shadow-xs p-5">
                    <h3 class="text-sm font-semibold text-ink mb-3 flex items-center gap-2">
                        <svg class="h-4 w-4 text-forest" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="10" r="8"/><path d="M10 9v5M10 7h.01"/></svg>
                        How it works
                    </h3>
                    <ol class="space-y-2.5 text-sm text-ink-muted">
                        <li class="flex gap-2.5">
                            <span class="h-5 w-5 rounded-full bg-forest text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">1</span>
                            <span>Employee opens <strong class="text-ink">My QR</strong> on their phone — a rotating one-time QR code appears</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="h-5 w-5 rounded-full bg-forest text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">2</span>
                            <span>They hold the phone in front of <strong class="text-ink">this scanner</strong> screen</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="h-5 w-5 rounded-full bg-forest text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">3</span>
                            <span>The system records <strong class="text-ink">check-in</strong> (first scan) or <strong class="text-ink">check-out</strong> (second scan) for the day</span>
                        </li>
                        <li class="flex gap-2.5">
                            <span class="h-5 w-5 rounded-full bg-forest text-white text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5">4</span>
                            <span>Each code is <strong class="text-ink">single-use</strong> — screenshots and replays are rejected</span>
                        </li>
                    </ol>
                </div>

                <!-- Manual token entry -->
                <div class="rounded-xl border border-border bg-surface shadow-xs p-5">
                    <button
                        type="button"
                        class="flex items-center justify-between w-full text-sm font-semibold text-ink"
                        @click="showManual = !showManual"
                    >
                        <span class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="13" y="13" width="2" height="2"/></svg>
                            Manual Token Entry
                        </span>
                        <svg class="h-4 w-4 text-ink-muted transition-transform" :class="showManual ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8l5 5 5-5"/></svg>
                    </button>

                    <div v-if="showManual" class="mt-4 space-y-3">
                        <p class="text-xs text-ink-muted">Use when the camera is unavailable. Paste or type the QR token.</p>
                        <input
                            v-model="manualToken"
                            type="text"
                            placeholder="Paste token here…"
                            class="w-full rounded-lg border border-border bg-stone/50 px-3 py-2 text-sm font-mono text-ink placeholder-ink-faint focus:outline-none focus:ring-2 focus:ring-forest/30 focus:border-forest/50 transition"
                            @keyup.enter="submitManual"
                        />
                        <button
                            type="button"
                            :disabled="!manualToken.trim() || manualLoading"
                            class="w-full rounded-lg bg-forest px-4 py-2 text-sm font-semibold text-white hover:bg-forest-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="submitManual"
                        >
                            {{ manualLoading ? 'Checking…' : 'Submit Token' }}
                        </button>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="rounded-xl border border-border bg-surface shadow-xs p-5">
                    <h3 class="text-sm font-semibold text-ink mb-3">Quick Links</h3>
                    <div class="space-y-1">
                        <a href="/attendance/board" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-muted hover:bg-stone hover:text-ink transition-colors">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="3" width="16" height="14" rx="2"/><path d="M2 7h16M6 7v10"/></svg>
                            Attendance Board
                        </a>
                        <a href="/attendance/summary" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-muted hover:bg-stone hover:text-ink transition-colors">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 15l4-4 3 3 4-5 3 2"/></svg>
                            Attendance Summary
                        </a>
                        <a href="/employees" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-muted hover:bg-stone hover:text-ink transition-colors">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="8" cy="6" r="3"/><path d="M2 18a6 6 0 0112 0"/></svg>
                            Employees
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
