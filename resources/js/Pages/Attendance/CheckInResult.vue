<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    ok: Boolean,
    action: String, // check_in | check_out
    message: String,
    employee: String,
    number: String,
    time: String,
    worked: String,
    late: Boolean,
    department: String,
});
</script>

<template>
    <Head title="Attendance Confirmation" />

    <div class="min-h-full max-w-lg mx-auto py-8 px-4">
        <div class="rounded-3xl border-2 bg-surface p-8 shadow-sm text-center" :class="ok ? 'border-emerald-200' : 'border-red-200'">
            <!-- Icon -->
            <div
                class="h-16 w-16 rounded-full mx-auto flex items-center justify-center mb-5 shadow-xs"
                :class="ok ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'"
            >
                <svg v-if="ok" class="h-8 w-8" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10l4 4 8-8"/></svg>
                <svg v-else class="h-8 w-8" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 6l8 8M14 6l-8 8"/></svg>
            </div>

            <h1 class="text-2xl font-extrabold text-ink tracking-tight mb-2">
                {{ ok ? (action === 'check_out' ? 'Checked Out Successfully' : 'Checked In Successfully') : 'Attendance Notice' }}
            </h1>

            <p class="text-sm font-medium mb-6" :class="ok ? 'text-emerald-700' : 'text-red-700'">
                {{ message }}
            </p>

            <!-- Employee Info Card -->
            <div v-if="employee" class="bg-stone/30 rounded-2xl p-5 mb-6 text-left border border-border">
                <div class="flex items-center justify-between pb-3 border-b border-border">
                    <div>
                        <p class="text-xs text-ink-muted uppercase font-semibold">Employee</p>
                        <p class="text-base font-bold text-ink">{{ employee }}</p>
                    </div>
                    <span v-if="number" class="font-mono text-xs bg-stone px-2.5 py-1 rounded-md text-ink-muted">
                        {{ number }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-3">
                    <div v-if="department">
                        <span class="text-xs text-ink-muted block">Department</span>
                        <span class="text-sm font-semibold text-ink">{{ department }}</span>
                    </div>

                    <div v-if="time">
                        <span class="text-xs text-ink-muted block">Timestamp</span>
                        <span class="text-sm font-mono font-semibold text-ink">{{ time }}</span>
                    </div>

                    <div v-if="worked">
                        <span class="text-xs text-ink-muted block">Hours Worked</span>
                        <span class="text-sm font-semibold text-forest">{{ worked }}</span>
                    </div>

                    <div v-if="late">
                        <span class="text-xs text-ink-muted block">Punctuality</span>
                        <span class="inline-flex items-center text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded">
                            Late Arrival
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="space-y-2">
                <Link
                    href="/dashboard"
                    class="block w-full rounded-xl bg-forest px-4 py-3 text-sm font-semibold text-white shadow-xs hover:bg-forest-hover transition-colors"
                >
                    Go to Dashboard
                </Link>

                <Link
                    href="/my-attendance"
                    class="block w-full rounded-xl border border-border bg-surface px-4 py-2.5 text-sm font-medium text-ink hover:bg-stone transition-colors"
                >
                    View My Attendance Log
                </Link>
            </div>
        </div>
    </div>
</template>
