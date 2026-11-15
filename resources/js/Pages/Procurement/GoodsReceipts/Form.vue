<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    order: {
        type: Object, // id, order_number, supplier
        required: true,
    },
    receipt: {
        type: Object, // null when creating, draft when editing
        default: null,
    },
    lines: {
        type: Array, // every order line, with ordered / received / outstanding / quantity
        required: true,
    },
});

const isEdit = computed(() => props.receipt !== null);

// useForm keeps values, sends them, and remembers server validation errors.
// ALL lines are sent in the exact order shown so "line 2" in validation matches the second card.
// Quantities stay as TEXT ("4.500") to eliminate floating-point error.
const form = useForm({
    lines: props.lines.map((line) => ({
        purchase_order_item_id: line.purchase_order_item_id,
        quantity: line.quantity,
    })),
});

// The server sends exact strings like "0.000" for completed lines
function isComplete(line) {
    return line.outstanding === '0.000';
}

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

    <div class="max-w-3xl space-y-6">
        <PageHeader
            :title="isEdit ? `Edit draft ${receipt.receipt_number}` : 'Receive goods'"
            :subtitle="`Order ${order.order_number} · ${order.supplier}`"
            :back-href="isEdit ? `/goods-receipts/${receipt.id}` : `/purchase-orders/${order.id}`"
            :back-label="isEdit ? 'Goods receipt' : 'Purchase order'"
            :mono-title="isEdit"
        />

        <!-- Instructional Card -->
        <Card padding="md">
            <p class="text-sm text-ink-muted">
                Enter what physically arrived in this shipment. Leave a line blank (or 0) if nothing arrived. This action only saves a draft receipt; warehouse stock updates when you subsequently post the receipt.
            </p>
        </Card>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Global lines error banner -->
            <ErrorAlert
                v-if="form.errors.lines"
                :message="form.errors.lines"
                title="Invalid receipt lines"
            />

            <!-- Line Items Stack -->
            <Card padding="md">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <h2 class="text-base font-semibold text-ink">Purchase Order Items</h2>
                    <span class="font-mono text-xs text-ink-muted tabular-nums">
                        {{ lines.length }} {{ lines.length === 1 ? 'item' : 'items' }}
                    </span>
                </div>

                <div class="mt-4 space-y-4">
                    <div
                        v-for="(line, index) in lines"
                        :key="line.purchase_order_item_id"
                        class="rounded border border-border-subtle bg-stone/40 p-4"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                                <p class="text-base font-semibold text-ink">{{ line.product.name }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-xs font-mono tabular-nums">
                                <span class="rounded bg-stone px-2 py-0.5 text-ink-muted border border-border">
                                    Ordered: {{ line.ordered }}
                                </span>
                                <span class="rounded bg-stone px-2 py-0.5 text-ink-muted border border-border">
                                    Received: {{ line.received }}
                                </span>
                                <span
                                    class="rounded px-2 py-0.5 font-medium border"
                                    :class="isComplete(line) ? 'bg-soft-green text-forest border-forest/20' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                >
                                    Outstanding: {{ line.outstanding }} {{ line.product.unit || '' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 max-w-xs">
                            <FormField
                                :for-id="`quantity-${index}`"
                                label="Quantity received now"
                                hint="Decimal up to 3 places"
                                :error="form.errors[`lines.${index}.quantity`] || form.errors[`lines.${index}.purchase_order_item_id`]"
                            >
                                <div v-if="isComplete(line)" class="flex min-h-11 items-center">
                                    <span class="inline-flex items-center rounded-sm bg-soft-green px-2.5 py-1 text-xs font-medium text-forest border border-forest/20">
                                        Fully received
                                    </span>
                                </div>

                                <TextInput
                                    v-else
                                    :id="`quantity-${index}`"
                                    v-model="form.lines[index].quantity"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="0.000"
                                    :mono="true"
                                    :disabled="form.processing"
                                />
                            </FormField>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Actions Row -->
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <AppButton
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving…' : 'Save draft' }}
                </AppButton>

                <AppButton
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="cancel"
                >
                    Cancel
                </AppButton>
            </div>
        </form>
    </div>
</template>
