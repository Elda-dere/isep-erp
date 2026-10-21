<script setup>
import { Head, router } from '@inertiajs/vue3';

defineProps({
    userName: String,
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head title="Dashboard" />

    <main class="mx-auto max-w-xl p-6">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="mt-2">Signed in as {{ userName }}.</p>

        <button
            type="button"
            class="mt-6 min-h-11 rounded border border-gray-300 px-4 font-medium"
            @click="logout"
        >
            Sign out
        </button>
    </main>
</template>