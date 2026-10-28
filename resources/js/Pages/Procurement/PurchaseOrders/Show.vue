<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../../format.js';

const props = defineProps({
    order: Object, // the order with its lines; quantities and money are exact strings
});

const page = usePage();

// The buttons are only a convenience: the server checks purchase_orders.manage on every request anyway.
const canManage = computed(() => page.props.auth?.permissions?.includes('purchase_orders.manage'));

const isDraft = computed(() => props.order.status === 'draft');
// A draft or an ordered order can be cancelled. The server also refuses once goods were received.
const canCancel = computed(() => ['draft', 'ordered'].includes(props.order.status));

// The server sends this message when it refuses (for example, "Goods have already been received ...").
const statusError = computed(() => page.props.errors?.status ?? null);

function place() {
    // Placing is a one-way step, so we ask first (SRS section 6.1).
    if (!confirm(`Place order ${props.order.order_number}? After this the lines can no longer be changed.`)) {
        return;
    }

    router.post(`/purchase-orders/${props.order.id}/place`, {}, { preserveScroll: true });
}

function cancelOrder() {
    if (!confirm(`Cancel order ${props.order.order_number}? This cannot be undone.`)) {
        return;
    }

    router.post(`/purchase-orders/${props.order.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <div>
        <Link href="/purchase-orders" class="text-sm text-gray-600 underline">← Purchase orders</Link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold">{{ order.order_number }}</h1>
            <StatusBadge :status="order.status" />
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="statusError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ statusError }}
        </p>

        <!-- Actions. Only people with purchase_orders.manage see them. -->
        <div v-if="canManage" class="mt-4 flex flex-wrap gap-2">
            <Link v-if="isDraft" :href="`/purchase-orders/${order.id}/edit`" class="flex min-h-11 items-center rounded border px-4">
                Edit
            </Link>
            <button
                v-if="isDraft"
                type="button"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
                @click="place"
            >
                Place order
            </button>
            <button
                v-if="canCancel"
                type="button"
                class="flex min-h-11 items-center rounded border border-red-300 px-4 text-red-700"
                @click="cancelOrder"
            >
                Cancel order
            </button>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Supplier</dt>
                <dd class="font-medium">{{ order.supplier.name }}</dd>
                <dd v-if="order.supplier.phone" class="text-gray-600">{{ order.supplier.phone }}</dd>
                <dd v-if="order.supplier.email" class="text-gray-600">{{ order.supplier.email }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Created</dt>
                <dd class="font-medium">{{ formatDateTime(order.created_at) }}</dd>
                <dd class="text-gray-600">by {{ order.created_by }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Placed</dt>
                <dd class="font-medium">{{ order.ordered_at ? formatDateTime(order.ordered_at) : 'Not placed yet' }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-lg font-semibold">Lines</h2>

        <!-- Table: from 640 px upward -->
        <table class="mt-2 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Product</th>
                    <th class="text-right">Ordered</th>
                    <th class="text-right">Unit cost</th>
                    <th class="text-right">Line total</th>
                    <th class="text-right">Received</th>
                    <th class="text-right">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in order.lines" :key="line.id" class="border-b">
                    <td class="py-3">
                        <span class="font-mono text-xs text-gray-500">{{ line.product.sku }}</span>
                        <span class="block">{{ line.product.name }}</span>
                    </td>
                    <td class="text-right">{{ line.quantity }} {{ line.product.unit }}</td>
                    <td class="text-right">{{ formatMoney(line.unit_cost) }}</td>
                    <td class="text-right">{{ formatMoney(line.line_total) }}</td>
                    <td class="text-right">{{ line.received }}</td>
                    <td class="text-right font-medium">{{ line.outstanding }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px -->
        <div class="mt-2 space-y-3 sm:hidden">
            <div v-for="line in order.lines" :key="line.id" class="rounded border bg-white p-3">
                <p class="font-medium">{{ line.product.name }}</p>
                <p class="font-mono text-xs text-gray-500">{{ line.product.sku }}</p>
                <p class="mt-2 text-sm">
                    {{ line.quantity }} {{ line.product.unit }} × {{ formatMoney(line.unit_cost) }}
                    = <span class="font-medium">{{ formatMoney(line.line_total) }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    Received {{ line.received }} · outstanding {{ line.outstanding }}
                </p>
            </div>
        </div>

        <div class="mt-4 rounded border bg-white p-3">
            <p class="text-lg font-semibold">Total: {{ formatMoney(order.total) }}</p>
            <p class="text-xs text-gray-500">Calculated from the lines. It is never stored.</p>
        </div>
    </div>
</template>