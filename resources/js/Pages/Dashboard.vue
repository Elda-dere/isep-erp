<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    userName: String,
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto max-w-xl">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="mt-2">Signed in as {{ userName }}.</p>
    </div>
</template>