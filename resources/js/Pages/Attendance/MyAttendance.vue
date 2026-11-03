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
</script>

<template>
    <Head title="My attendance" />

    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold">My attendance</h1>

        <p v-if="state !== 'ready'" role="alert" class="mt-4 rounded bg-amber-50 p-3 text-amber-900">{{ message }}</p>

        <template v-else>
            <p class="mt-1 text-sm text-gray-600">{{ employeeName }}. Times are shown in {{ timezone }}.</p>

            <section class="mt-4 rounded border border-gray-200 bg-white p-4" aria-labelledby="today-title">
                <h2 id="today-title" class="font-semibold">Today, {{ today.date }}</h2>

                <p v-if="today.state === 'not_in'" class="mt-1">You have not checked in today.</p>

                <p v-else-if="today.state === 'in'" class="mt-1">
                    Checked in at {{ today.check_in }}<span v-if="today.late"> (late)</span>. Not checked out yet.
                </p>

                <p v-else class="mt-1">
                    Checked in at {{ today.check_in }}<span v-if="today.late"> (late)</span>,
                    checked out at {{ today.check_out }}. Worked {{ today.worked }}.
                </p>
            </section>

            <h2 class="mt-6 text-lg font-semibold">History</h2>
            <p class="mt-1 text-sm text-gray-600" role="status">{{ records.total }} days</p>

            <p v-if="records.data.length === 0" class="mt-2">No attendance recorded yet.</p>

            <div v-else class="mt-2 hidden overflow-x-auto md:block">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-300">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">In</th>
                            <th class="py-2 pr-3">Out</th>
                            <th class="py-2 pr-3">Worked</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="record in records.data" :key="record.id" class="border-b border-gray-200">
                            <td class="py-2 pr-3 whitespace-nowrap">{{ record.date }}</td>
                            <td class="py-2 pr-3">{{ record.check_in }}</td>
                            <td class="py-2 pr-3">{{ record.check_out ?? '-' }}</td>
                            <td class="py-2 pr-3">{{ record.worked ?? '-' }}</td>
                            <td class="py-2 capitalize">{{ record.status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul v-if="records.data.length > 0" class="mt-2 space-y-3 md:hidden">
                <li v-for="record in records.data" :key="record.id" class="rounded border border-gray-200 bg-white p-3">
                    <p class="font-medium">{{ record.date }} <span class="font-normal capitalize text-gray-600">({{ record.status }})</span></p>
                    <p class="text-sm">In {{ record.check_in }}, out {{ record.check_out ?? 'not yet' }}</p>
                    <p v-if="record.worked" class="text-sm text-gray-600">Worked {{ record.worked }}</p>
                </li>
            </ul>

            <PagerLinks :meta="records" />
        </template>
    </div>
</template>