<script setup>
import { Head } from '@inertiajs/vue3';
import PagerLinks from '../../Components/PagerLinks.vue';

defineProps({
    state: String,
    message: String,
    employeeName: String,
    timezone: String,
    today: Object,
    records: Object,
});

function statusColor(status) {
    if (status === 'present') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (status === 'late') return 'bg-amber-50 text-amber-700 border-amber-200';
    return 'bg-stone text-ink-muted border-border';
}
</script>

<template>
    <Head title="My Attendance" />

    <div class="min-h-full max-w-4xl">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-ink tracking-tight">My Attendance</h1>
            <p class="text-sm text-ink-muted mt-0.5">{{ employeeName }} · Times shown in {{ timezone }}</p>
        </div>

        <div v-if="state !== 'ready'" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 mb-6">
            <p class="font-medium text-sm">{{ message }}</p>
        </div>

        <template v-else>
            <!-- Today's Status Banner Card -->
            <div class="rounded-xl border border-border bg-surface p-5 shadow-xs mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Today's Check-in</h2>
                        <p class="text-lg font-bold text-ink mt-0.5">{{ today.date }}</p>
                    </div>

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold border"
                        :class="today.state === 'in' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : today.state === 'out' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-stone text-ink-muted border-border'"
                    >
                        <span class="h-2 w-2 rounded-full" :class="today.state === 'in' ? 'bg-emerald-500' : today.state === 'out' ? 'bg-blue-500' : 'bg-ink-faint'" />
                        {{ today.state === 'not_in' ? 'Not Checked In' : today.state === 'in' ? 'Checked In' : 'Completed' }}
                        <span v-if="today.late" class="text-red-600 font-bold ml-1">(Late)</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-border">
                    <div>
                        <span class="text-xs text-ink-muted">In Time</span>
                        <p class="text-sm font-semibold text-ink font-mono mt-0.5">{{ today.check_in ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-ink-muted">Out Time</span>
                        <p class="text-sm font-semibold text-ink font-mono mt-0.5">{{ today.check_out ?? '—' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-ink-muted">Worked</span>
                        <p class="text-sm font-semibold text-ink mt-0.5">{{ today.worked ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- History Table Card -->
            <div class="rounded-xl border border-border bg-surface shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink">Attendance History</h2>
                    <span class="rounded-full bg-stone px-2.5 py-0.5 text-xs text-ink-muted font-medium">
                        {{ records.total }} total days
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-border bg-stone/40">
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Date</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Check In</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Check Out</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Hours Worked</th>
                                <th class="px-3 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="records.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-ink-muted">
                                    No attendance recorded yet.
                                </td>
                            </tr>

                            <tr v-for="record in records.data" :key="record.id" class="hover:bg-stone/30 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap font-medium text-ink">{{ record.date }}</td>
                                <td class="px-3 py-3 font-mono text-ink">{{ record.check_in }}</td>
                                <td class="px-3 py-3 font-mono text-ink">{{ record.check_out ?? '—' }}</td>
                                <td class="px-3 py-3 text-ink-muted">{{ record.worked ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold capitalize border" :class="statusColor(record.status)">
                                        {{ record.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-border bg-stone/20">
                    <PagerLinks :meta="records" />
                </div>
            </div>
        </template>
    </div>
</template>
