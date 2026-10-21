<script setup>
defineProps({
    products: Object,
    filters: Object,
    categories: Array,
});
</script>

<template>
    <div class="p-4">
        <h1 class="text-xl font-semibold">Products</h1>
        <ul class="mt-4 space-y-2">
            <li v-for="product in products.data" :key="product.id">
                {{ product.sku }} — {{ product.name }}
            </li>
        </ul>
    </div>
</template>