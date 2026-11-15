<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime } from '../../../format.js';

const props = defineProps({
    receipt: {
        type: Object, // Receipt with lines; quantities are exact strings
        required: true,
    },
    warehouses: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const isDraft = computed(() => props.receipt.status === 'draft');

// The server sends this message when it refuses (e.g., "only 4.000 is still outstanding").
const statusError = computed(() => page.props.errors?.status ?? null);

// PRC-08: the warehouse is chosen here, when posting.
const postForm = useForm({ warehouse_id: '' });

const warehouseOptions = computed(() =>
    props.warehouses.map((w) => ({
        value: w.id,
        label: w.name,
    })),
);

function post() {
    if (!postForm.warehouse_id) {
        postForm.setError('warehouse_id', 'Choose the warehouse that receives the goods.');
        return;
    }

    // Posting changes the stock and cannot be undone (SRS section 6.1).
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

    <div class="space-y-6">
        <PageHeader
            :title="receipt.receipt_number"
            :mono-title="true"
            back-href="/goods-receipts"
            back-label="Goods receipts"
        >
            <template #title-after>
                <StatusBadge :status="receipt.status" class="ml-1" />
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="statusError"
            :message="statusError"
            title="Action refused"
        />

        <!-- Posting card for draft receipts -->
        <Card v-if="isDraft" padding="md">
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-ink">Post Goods Receipt</h2>
                    <p class="mt-0.5 text-xs text-ink-muted">
                        This receipt is currently a draft and has not changed inventory. Posting increases physical stock in the selected warehouse and advances purchase order progress.
                    </p>
                </div>

                <div class="max-w-md">
                    <FormField
                        for-id="warehouse"
                        label="Receiving warehouse"
                        required
                        hint="Select where physical stock is placed"
                        :error="postForm.errors.warehouse_id"
                    >
                        <SelectInput
                            id="warehouse"
                            v-model="postForm.warehouse_id"
                            :options="warehouseOptions"
                            placeholder="Choose a warehouse"
                            :disabled="postForm.processing"
                        />
                    </FormField>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-border-subtle">
                    <AppButton
                        type="button"
                        variant="primary"
                        :loading="postForm.processing"
                        :disabled="postForm.processing"
                        @click="post"
                    >
                        {{ postForm.processing ? 'Posting…' : 'Post receipt' }}
                    </AppButton>

                    <AppButton
                        :href="`/goods-receipts/${receipt.id}/edit`"
                        variant="secondary"
                        :disabled="postForm.processing"
                    >
                        Edit draft
                    </AppButton>

                    <AppButton
                        type="button"
                        variant="ghost"
                        class="text-red-700 hover:bg-red-50 hover:text-red-800"
                        :disabled="postForm.processing"
                        @click="cancelReceipt"
                    >
                        Cancel receipt
                    </AppButton>
                </div>
            </div>
        </Card>

        <!-- Summary Cards Grid -->
        <div class="grid gap-4 sm:grid-cols-3">
            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Purchase Order</p>
                <div class="mt-1 space-y-1">
                    <Link
                        :href="`/purchase-orders/${receipt.order.id}`"
                        class="inline-flex min-h-11 items-center font-mono font-semibold text-forest hover:underline"
                    >
                        {{ receipt.order.order_number }}
                    </Link>
                    <p class="text-sm font-medium text-ink">{{ receipt.order.supplier }}</p>
                    <div class="pt-1">
                        <StatusBadge :status="receipt.order.status" />
                    </div>
                </div>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Receipt Details</p>
                <div class="mt-1 space-y-1 text-sm">
                    <p class="text-ink">
                        <span class="text-xs text-ink-muted">Received:</span> {{ formatDateTime(receipt.received_at) }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        by {{ receipt.received_by || 'Unknown' }}
                    </p>
                </div>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Status & Inventory</p>
                <div class="mt-1 space-y-1 text-sm">
                    <p v-if="receipt.status === 'posted'" class="font-medium text-forest">
                        Stock posted to warehouse
                    </p>
                    <p v-else-if="receipt.status === 'draft'" class="font-medium text-amber-700">
                        Draft (Inventory unaffected)
                    </p>
                    <p v-else class="font-medium text-ink-muted">
                        Cancelled
                    </p>
                    <p class="text-xs text-ink-muted">
                        {{ receipt.status === 'posted' ? 'Discrepancies must be corrected with a stock adjustment.' : 'Select a warehouse and post when verified.' }}
                    </p>
                </div>
            </Card>
        </div>

        <!-- Lines Card -->
        <Card padding="md">
            <div class="flex items-center justify-between pb-4 border-b border-border-subtle">
                <h2 class="text-base font-semibold text-ink">Received Items</h2>
                <span class="font-mono text-xs text-ink-muted tabular-nums">
                    {{ receipt.lines.length }} {{ receipt.lines.length === 1 ? 'line' : 'lines' }}
                </span>
            </div>

            <!-- Desktop Lines Table -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wider text-ink-muted">
                        <tr>
                            <th class="py-3 font-medium">Product</th>
                            <th class="py-3 text-right font-medium">Quantity received</th>
                            <th class="py-3 pl-6 font-medium">Warehouse</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <tr v-for="line in receipt.lines" :key="line.id">
                            <td class="py-3">
                                <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                                <p class="font-medium text-ink">{{ line.product.name }}</p>
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums font-semibold text-ink">
                                {{ line.quantity }} {{ line.product.unit || '' }}
                            </td>
                            <td class="py-3 pl-6 text-sm text-ink-muted">
                                {{ line.warehouse ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards for Lines -->
            <div class="mt-3 space-y-3 sm:hidden">
                <div
                    v-for="line in receipt.lines"
                    :key="line.id"
                    class="rounded border border-border-subtle bg-stone/40 p-3 space-y-2 text-sm"
                >
                    <div>
                        <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                        <p class="font-semibold text-ink">{{ line.product.name }}</p>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-ink-muted">Quantity:</span>
                        <span class="font-mono tabular-nums text-ink font-semibold">
                            {{ line.quantity }} {{ line.product.unit || '' }}
                        </span>
                    </div>

                    <div v-if="line.warehouse" class="pt-1 border-t border-border-subtle flex items-center justify-between text-xs text-ink-muted">
                        <span>Warehouse:</span>
                        <span class="font-medium text-ink">{{ line.warehouse }}</span>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>
