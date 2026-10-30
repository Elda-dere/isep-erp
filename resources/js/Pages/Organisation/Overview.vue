<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    departments: Array,
    headcount: Number,
    statuses: Array,
});

function statusLabel(status) {
    return status.replaceAll('_', ' ');
}
</script>

<template>
    <Head title="Organisation" />

    <div>
        <h1 class="text-2xl font-bold">Organisation</h1>
        <p class="mt-1 text-sm text-gray-600">
            Headcount counts everyone who is not terminated. Total headcount: <strong>{{ headcount }}</strong>
        </p>

        <section class="mt-4" aria-labelledby="by-status">
            <h2 id="by-status" class="text-lg font-semibold">Employees by status</h2>
            <ul class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-4">
                <li v-for="item in statuses" :key="item.status" class="rounded border border-gray-200 bg-white p-3">
                    <p class="text-2xl font-bold">{{ item.total }}</p>
                    <p class="text-sm capitalize text-gray-600">{{ statusLabel(item.status) }}</p>
                </li>
            </ul>
        </section>

        <section class="mt-6" aria-labelledby="by-department">
            <h2 id="by-department" class="text-lg font-semibold">Departments and positions</h2>

            <p v-if="departments.length === 0" class="mt-2">No departments yet.</p>

            <div v-for="department in departments" :key="department.id" class="mt-3 rounded border border-gray-200 bg-white p-3">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="font-semibold">{{ department.name }}</h3>
                    <p class="text-sm">{{ department.headcount }} people</p>
                </div>
                <p v-if="department.description" class="text-sm text-gray-600">{{ department.description }}</p>

                <p v-if="department.positions.length === 0" class="mt-2 text-sm text-gray-600">No positions yet.</p>

                <ul v-else class="mt-2 divide-y divide-gray-100">
                    <li v-for="position in department.positions" :key="position.id" class="flex min-h-11 items-center justify-between gap-3">
                        <span>{{ position.title }}</span>
                        <span class="text-sm">{{ position.headcount }}</span>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>