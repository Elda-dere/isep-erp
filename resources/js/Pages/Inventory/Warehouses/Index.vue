<script setup>
defineProps({
    warehouses: Object,
});
</script>

<template>
    <div class="p-4">
        <h1 class="text-xl font-semibold">Warehouses</h1>
        <ul class="mt-4 space-y-2">
            <li v-for="warehouse in warehouses.data" :key="warehouse.id">
                {{ warehouse.name }} — {{ warehouse.location }}
            </li>
        </ul>
    </div>
</template>