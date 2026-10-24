<script setup>
// Placeholder: products at or below their reorder level.
defineProps({
    products: Object,
});
</script>

<template>
    <div class="p-4">
        <h1 class="text-xl font-semibold">Low stock</h1>
        <p class="mt-1 text-sm text-gray-500">{{ products.total }} product(s) need attention</p>
        <ul class="mt-4 space-y-2">
            <li v-for="product in products.data" :key="product.id">
                {{ product.sku }} — {{ product.name }}:
                {{ product.total_quantity }} in stock (reorder at {{ product.reorder_level }})
            </li>
        </ul>
    </div>
</template>