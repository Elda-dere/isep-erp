<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FlashMessage from '../../Components/FlashMessage.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../format.js';

defineProps({
    sale: Object, // the sale with its lines; quantities and money are exact strings
});
</script>

<template>
    <Head :title="`Sale ${sale.sale_number}`" />

    <div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold">{{ sale.sale_number }}</h1>
            <StatusBadge :status="sale.status" />
        </div>

        <FlashMessage class="mt-4" />

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Customer</dt>
                <dd class="font-medium">{{ sale.customer.name }}</dd>
                <dd v-if="sale.customer.phone" class="text-gray-600">{{ sale.customer.phone }}</dd>
                <dd v-if="sale.customer.email" class="text-gray-600">{{ sale.customer.email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Created</dt>
                <dd class="font-medium">{{ formatDateTime(sale.created_at) }}</dd>
                <dd class="text-gray-600">by {{ sale.created_by }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Total</dt>
                <dd class="text-lg font-semibold">{{ formatMoney(sale.total_amount, sale.currency) }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-lg font-semibold">Lines</h2>

        <!-- Table: from 640 px upward -->
        <table class="mt-2 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Product</th>
                    <th>Warehouse</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit price</th>
                    <th class="text-right">Line total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in sale.lines" :key="line.id" class="border-b">
                    <td class="py-3">
                        <span class="font-mono text-xs text-gray-500">{{ line.product.sku }}</span>
                        <span class="block">{{ line.product.name }}</span>
                    </td>
                    <td>{{ line.warehouse }}</td>
                    <td class="text-right">{{ line.quantity }} {{ line.product.unit }}</td>
                    <td class="text-right">{{ formatMoney(line.unit_price, sale.currency) }}</td>
                    <td class="text-right">{{ formatMoney(line.line_total, sale.currency) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px -->
        <div class="mt-2 space-y-3 sm:hidden">
            <div v-for="line in sale.lines" :key="line.id" class="rounded border bg-white p-3">
                <p class="font-medium">{{ line.product.name }}</p>
                <p class="font-mono text-xs text-gray-500">{{ line.product.sku }} · {{ line.warehouse }}</p>
                <p class="mt-2 text-sm">
                    {{ line.quantity }} {{ line.product.unit }} × {{ formatMoney(line.unit_price, sale.currency) }}
                    = <span class="font-medium">{{ formatMoney(line.line_total, sale.currency) }}</span>
                </p>
            </div>
        </div>

        <div class="mt-4 rounded border bg-white p-3">
            <p class="text-lg font-semibold">Total: {{ formatMoney(sale.total_amount, sale.currency) }}</p>
            <p class="text-xs text-gray-500">The stock of these lines is held while the customer pays.</p>
        </div>

        <div class="mt-6 flex flex-wrap gap-2">
            <Link href="/sales/create" class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white">New sale</Link>
        </div>
    </div>
</template>