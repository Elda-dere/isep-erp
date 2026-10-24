<script setup>
// Placeholder: shows what the controller sends. The real screen comes with the layout.
defineProps({
    products: Object,
    filters: Object,
    categories: Array,
    warehouses: Array,
});
</script>

<template>
    <div class="p-4">
        <h1 class="text-xl font-semibold">Stock overview</h1>
        <ul class="mt-4 space-y-3">
            <li v-for="product in products.data" :key="product.id">
                <strong>{{ product.sku }} — {{ product.name }}</strong>
                : total {{ product.total_quantity }} {{ product.unit?.symbol }}
                <ul class="ml-4 text-sm text-gray-600">
                    <!-- One line per warehouse that holds this product -->
                    <li v-for="stock in product.stocks" :key="stock.id">
                        {{ stock.warehouse?.name }}: {{ stock.quantity }}
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>