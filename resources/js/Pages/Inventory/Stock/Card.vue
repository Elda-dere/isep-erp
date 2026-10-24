<script setup>
// Placeholder: the ledger of one product with its running balance.
defineProps({
    product: Object,
    movements: Object,
    filters: Object,
    warehouses: Array,
    types: Array,
});
</script>

<template>
    <div class="p-4">
        <h1 class="text-xl font-semibold">Stock card: {{ product.name }}</h1>
        <table class="mt-4 w-full text-left text-sm">
            <thead class="border-b text-gray-500">
                <tr>
                    <th>Date (UTC)</th>
                    <th>Type</th>
                    <th>Warehouse</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Balance</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="movement in movements.data" :key="movement.id" class="border-b">
                    <td>{{ movement.created_at }}</td>
                    <td>{{ movement.movement_type }}</td>
                    <td>{{ movement.warehouse?.name }}</td>
                    <td class="text-right">{{ movement.quantity }}</td>
                    <td class="text-right">{{ movement.running_balance }}</td>
                    <td>{{ movement.performed_by?.name }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>