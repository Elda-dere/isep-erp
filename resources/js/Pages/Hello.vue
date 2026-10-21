<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="Hello" />

    <main class="mx-auto max-w-xl p-6">
        <h1 class="text-2xl font-bold">Integrated ERP</h1>
        <p class="mt-2">{{ message }}</p>
    </main>
</template>