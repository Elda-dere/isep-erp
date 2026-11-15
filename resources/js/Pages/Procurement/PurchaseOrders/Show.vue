<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../../format.js';

const props = defineProps({
    order: {
        type: Object, // Order with lines; quantities and money are exact strings
        required: true,
    },
});

const page = usePage();

// The buttons are convenience indicators: the server checks purchase_orders.manage on every request anyway.
const canManage = computed(() => Boolean(page.props.auth?.permissions?.includes('purchase_orders.manage')));
const isDraft = computed(() => props.order.status === 'draft');

// A draft or ordered order can be cancelled. The server also refuses once goods were received.
const canCancel = computed(() => ['draft', 'ordered'].includes(props.order.status));

// Receiving goods needs goods_receipts.manage (a different permission), and an order that is placed.
const canReceive = computed(
    () => Boolean(page.props.auth?.permissions?.includes('goods_receipts.manage'))
        && ['ordered', 'partially_received'].includes(props.order.status),
);
const canSeeReceipts = computed(() => Boolean(page.props.auth?.permissions?.includes('goods_receipts.manage')));

// The server sends this message when it refuses (e.g., "Goods have already been received ...").
const statusError = computed(() => page.props.errors?.status ?? null);

function place() {
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

    <div class="space-y-6">
        <PageHeader
            :title="order.order_number"
            :mono-title="true"
            back-href="/purchase-orders"
            back-label="Purchase orders"
        >
            <template #title-after>
                <StatusBadge :status="order.status" class="ml-1" />
            </template>

            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Management actions -->
                    <template v-if="canManage">
                        <AppButton
                            v-if="isDraft"
                            :href="`/purchase-orders/${order.id}/edit`"
                            variant="secondary"
                        >
                            Edit draft
                        </AppButton>

                        <AppButton
                            v-if="isDraft"
                            type="button"
                            variant="primary"
                            @click="place"
                        >
                            Place order
                        </AppButton>

                        <AppButton
                            v-if="canCancel"
                            type="button"
                            variant="ghost"
                            class="text-red-700 hover:bg-red-50 hover:text-red-800"
                            @click="cancelOrder"
                        >
                            Cancel order
                        </AppButton>
                    </template>

                    <!-- Goods receiving actions -->
                    <AppButton
                        v-if="canReceive"
                        :href="`/purchase-orders/${order.id}/receive`"
                        variant="primary"
                    >
                        Receive goods
                    </AppButton>

                    <AppButton
                        v-if="canSeeReceipts"
                        :href="`/goods-receipts?purchase_order_id=${order.id}`"
                        variant="secondary"
                    >
                        Receipts
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="statusError"
            :message="statusError"
            title="Action refused"
        />

        <!-- Summary Cards Grid -->
        <div class="grid gap-4 sm:grid-cols-3">
            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Supplier</p>
                <p class="mt-1 text-base font-semibold text-ink">{{ order.supplier.name }}</p>
                <div class="mt-2 space-y-0.5 text-xs text-ink-muted">
                    <p v-if="order.supplier.phone">Phone: {{ order.supplier.phone }}</p>
                    <p v-if="order.supplier.email">Email: {{ order.supplier.email }}</p>
                </div>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Order History</p>
                <div class="mt-1 space-y-1 text-sm">
                    <p class="text-ink">
                        <span class="text-ink-muted text-xs">Created:</span> {{ formatDateTime(order.created_at) }}
                    </p>
                    <p class="text-xs text-ink-muted">
                        by {{ order.created_by || 'Unknown' }}
                    </p>
                    <p class="text-ink pt-1 border-t border-border-subtle">
                        <span class="text-ink-muted text-xs">Placed:</span>
                        {{ order.ordered_at ? formatDateTime(order.ordered_at) : 'Not placed yet' }}
                    </p>
                </div>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Order Total</p>
                <p class="mt-1 font-mono text-2xl font-semibold tabular-nums text-ink">
                    {{ formatMoney(order.total) }}
                </p>
                <p class="mt-2 text-xs text-ink-muted">
                    Calculated from line items. Sum is never statically stored.
                </p>
            </Card>
        </div>

        <!-- Lines Card -->
        <Card padding="md">
            <div class="flex items-center justify-between pb-4 border-b border-border-subtle">
                <h2 class="text-base font-semibold text-ink">Order Lines</h2>
                <span class="font-mono text-xs text-ink-muted tabular-nums">
                    {{ order.lines.length }} {{ order.lines.length === 1 ? 'line' : 'lines' }}
                </span>
            </div>

            <!-- Desktop Lines Table -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wider text-ink-muted">
                        <tr>
                            <th class="py-3 font-medium">Product</th>
                            <th class="py-3 text-right font-medium">Ordered</th>
                            <th class="py-3 text-right font-medium">Unit cost</th>
                            <th class="py-3 text-right font-medium">Line total</th>
                            <th class="py-3 text-right font-medium">Received</th>
                            <th class="py-3 text-right font-medium">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <tr v-for="line in order.lines" :key="line.id">
                            <td class="py-3">
                                <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                                <p class="font-medium text-ink">{{ line.product.name }}</p>
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums text-ink">
                                {{ line.quantity }} {{ line.product.unit || '' }}
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums text-ink">
                                {{ formatMoney(line.unit_cost) }}
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums font-medium text-ink">
                                {{ formatMoney(line.line_total) }}
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums text-ink-muted">
                                {{ line.received }}
                            </td>
                            <td class="py-3 text-right font-mono tabular-nums font-semibold text-ink">
                                {{ line.outstanding }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards for Lines -->
            <div class="mt-3 space-y-3 sm:hidden">
                <div
                    v-for="line in order.lines"
                    :key="line.id"
                    class="rounded border border-border-subtle bg-stone/40 p-3 space-y-2 text-sm"
                >
                    <div>
                        <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                        <p class="font-semibold text-ink">{{ line.product.name }}</p>
                    </div>

                    <div class="flex items-center justify-between text-xs text-ink-muted">
                        <span>Ordered:</span>
                        <span class="font-mono tabular-nums text-ink font-medium">
                            {{ line.quantity }} {{ line.product.unit || '' }} × {{ formatMoney(line.unit_cost) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-xs text-ink-muted">
                        <span>Line Total:</span>
                        <span class="font-mono tabular-nums text-ink font-semibold">
                            {{ formatMoney(line.line_total) }}
                        </span>
                    </div>

                    <div class="pt-1 border-t border-border-subtle flex items-center justify-between text-xs">
                        <span class="text-ink-muted">Received: {{ line.received }}</span>
                        <span class="font-medium text-ink">Outstanding: {{ line.outstanding }}</span>
                    </div>
                </div>
            </div>
        </Card>
    </div>
</template>
