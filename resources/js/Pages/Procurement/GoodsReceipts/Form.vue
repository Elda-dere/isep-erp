<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    order: Object, // id, order_number, supplier
    receipt: Object, // null when creating, the draft when editing
    lines: Array, // every order line, with ordered / received / outstanding / quantity
});

const isEdit = computed(() => props.receipt !== null);

// useForm keeps the values, sends them, and remembers the server's validation errors.
// ALL lines are sent, in the order shown, so "line 2" in an error message is the second card.
// The server ignores lines whose quantity is blank or zero.
// Quantities stay as TEXT ("4.500"): this keeps the decimals exact.
const form = useForm({
    lines: props.lines.map((line) => ({
        purchase_order_item_id: line.purchase_order_item_id,
        quantity: line.quantity,
    })),
});

// Nothing is left to receive on this line (the server sends exact strings like "0.000").
function isComplete(line) {
    return line.outstanding === '0.000';
}

// Edit sends PUT /goods-receipts/{id}; create sends POST /purchase-orders/{id}/goods-receipts.
function submit() {
    if (isEdit.value) {
        form.put(`/goods-receipts/${props.receipt.id}`);
    } else {
        form.post(`/purchase-orders/${props.order.id}/goods-receipts`);
    }
}

function cancel() {
    router.visit(isEdit.value ? `/goods-receipts/${props.receipt.id}` : `/purchase-orders/${props.order.id}`);
}
</script>

<template>
    <Head :title="isEdit ? `Edit ${receipt.receipt_number}` : `Receive goods: ${order.order_number}`" />

    <div class="max-w-3xl">
        <h1 class="text-2xl font-semibold">
            {{ isEdit ? `Edit draft ${receipt.receipt_number}` : 'Receive goods' }}
        </h1>
        <p class="mt-1 text-sm text-gray-600">
            Order <span class="font-mono">{{ order.order_number }}</span> · {{ order.supplier }}
        </p>
        <p class="mt-1 text-sm text-gray-500">
            Enter what really arrived. Leave a line blank (or 0) if nothing arrived. This only saves a draft:
            the stock changes when you post the receipt.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <!-- Messages from the server that belong to the lines as a whole,
                 for example "only 4.000 is still outstanding" -->
            <p v-if="form.errors.lines" role="alert" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ form.errors.lines }}
            </p>

            <div v-for="(line, index) in lines" :key="line.purchase_order_item_id" class="rounded border bg-white p-3">
                <p class="font-mono text-xs text-gray-500">{{ line.product.sku }}</p>
                <p class="font-medium">{{ line.product.name }}</p>
                <p class="mt-1 text-sm text-gray-600">
                    Ordered {{ line.ordered }} · received {{ line.received }} ·
                    <span class="font-medium">outstanding {{ line.outstanding }}</span> {{ line.product.unit }}
                </p>

                <div class="mt-3 max-w-xs">
                    <label :for="`quantity-${index}`" class="block text-sm font-medium">Quantity received now</label>
                    <p v-if="isComplete(line)" class="mt-1 flex h-11 items-center text-sm text-gray-500">Fully received</p>
                    <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot: 2.5 -->
                    <input
                        v-else
                        :id="`quantity-${index}`"
                        v-model="form.lines[index].quantity"
                        type="text"
                        inputmode="decimal"
                        class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                    />
                    <p v-if="form.errors[`lines.${index}.quantity`]" class="mt-1 text-sm text-red-700" role="alert">
                        {{ form.errors[`lines.${index}.quantity`] }}
                    </p>
                    <p v-if="form.errors[`lines.${index}.purchase_order_item_id`]" class="mt-1 text-sm text-red-700" role="alert">
                        {{ form.errors[`lines.${index}.purchase_order_item_id`] }}
                    </p>
                </div>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save draft' }}
                </button>
                <button type="button" class="flex min-h-11 items-center rounded border px-5" @click="cancel">Cancel</button>
            </div>
        </form>
    </div>
</template>