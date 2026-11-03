<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime } from '../../../format.js';

const props = defineProps({
    receipt: Object, // the receipt with its lines; quantities are exact strings
    warehouses: Array,
});

const page = usePage();

const isDraft = computed(() => props.receipt.status === 'draft');

// The server sends this message when it refuses (for example, "only 4.000 is still outstanding").
const statusError = computed(() => page.props.errors?.status ?? null);

// PRC-08: the warehouse is chosen here, when posting.
const postForm = useForm({ warehouse_id: '' });

function post() {
    if (!postForm.warehouse_id) {
        postForm.setError('warehouse_id', 'Choose the warehouse that receives the goods.');
        return;
    }

    // Posting changes the stock and cannot be undone, so we ask first (SRS section 6.1).
    if (!confirm(`Post receipt ${props.receipt.receipt_number}? The stock of the chosen warehouse goes up, and a posted receipt cannot be edited.`)) {
        return;
    }

    postForm.post(`/goods-receipts/${props.receipt.id}/post`, { preserveScroll: true });
}

function cancelReceipt() {
    if (!confirm(`Cancel receipt ${props.receipt.receipt_number}? This cannot be undone.`)) {
        return;
    }

    router.post(`/goods-receipts/${props.receipt.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Receipt ${receipt.receipt_number}`" />

    <div>
        <Link href="/goods-receipts" class="text-sm text-gray-600 underline">← Goods receipts</Link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold">{{ receipt.receipt_number }}</h1>
            <StatusBadge :status="receipt.status" />
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="statusError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {{ statusError }}
        </p>

        <!-- Actions for a draft: post, edit or cancel. A posted receipt is final. -->
        <div v-if="isDraft" class="mt-4 rounded border bg-white p-3">
            <p class="text-sm text-gray-600">
                A draft has not changed any stock. Posting adds the quantities below to the warehouse you choose.
            </p>

            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1 sm:max-w-xs">
                    <label for="warehouse" class="block text-sm font-medium">Warehouse</label>
                    <select id="warehouse" v-model="postForm.warehouse_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Choose a warehouse</option>
                        <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                    </select>
                </div>
                <button
                    type="button"
                    :disabled="postForm.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white disabled:opacity-50"
                    @click="post"
                >
                    {{ postForm.processing ? 'Posting…' : 'Post receipt' }}
                </button>
            </div>
            <p v-if="postForm.errors.warehouse_id" class="mt-1 text-sm text-red-700" role="alert">{{ postForm.errors.warehouse_id }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <Link :href="`/goods-receipts/${receipt.id}/edit`" class="flex min-h-11 items-center rounded border px-4">Edit</Link>
                <button
                    type="button"
                    class="flex min-h-11 items-center rounded border border-red-300 px-4 text-red-700"
                    @click="cancelReceipt"
                >
                    Cancel receipt
                </button>
            </div>
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Purchase order</dt>
                <dd>
                    <Link :href="`/purchase-orders/${receipt.order.id}`" class="font-mono font-medium underline">
                        {{ receipt.order.order_number }}
                    </Link>
                </dd>
                <dd class="text-gray-600">{{ receipt.order.supplier }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Order status</dt>
                <dd><StatusBadge :status="receipt.order.status" /></dd>
            </div>
            <div>
                <dt class="text-gray-500">Received</dt>
                <dd class="font-medium">{{ formatDateTime(receipt.received_at) }}</dd>
                <dd class="text-gray-600">by {{ receipt.received_by }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-lg font-semibold">Lines</h2>

        <!-- Table: from 640 px upward -->
        <table class="mt-2 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Product</th>
                    <th class="text-right">Quantity received</th>
                    <th class="pl-6">Warehouse</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in receipt.lines" :key="line.id" class="border-b">
                    <td class="py-3">
                        <span class="font-mono text-xs text-gray-500">{{ line.product.sku }}</span>
                        <span class="block">{{ line.product.name }}</span>
                    </td>
                    <td class="text-right">{{ line.quantity }} {{ line.product.unit }}</td>
                    <td class="pl-6">{{ line.warehouse ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px -->
        <div class="mt-2 space-y-3 sm:hidden">
            <div v-for="line in receipt.lines" :key="line.id" class="rounded border bg-white p-3">
                <p class="font-medium">{{ line.product.name }}</p>
                <p class="font-mono text-xs text-gray-500">{{ line.product.sku }}</p>
                <p class="mt-2 text-sm">
                    <span class="font-medium">{{ line.quantity }} {{ line.product.unit }}</span>
                    <span v-if="line.warehouse" class="text-gray-600"> → {{ line.warehouse }}</span>
                </p>
            </div>
        </div>

        <p v-if="receipt.status === 'posted'" class="mt-4 text-xs text-gray-500">
            This receipt is posted and cannot be changed. A mistake is corrected with a stock adjustment.
        </p>
    </div>
</template>