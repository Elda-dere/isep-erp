<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import Card from '../../Components/Card.vue';
import ErrorAlert from '../../Components/ErrorAlert.vue';
import FlashMessage from '../../Components/FlashMessage.vue';
import PageHeader from '../../Components/PageHeader.vue';
import SalePayments from '../../Components/SalePayments.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../format.js';

const props = defineProps({
    sale: {
        type: Object, // the sale with its lines; quantities and money are exact strings
        required: true,
    },
    payments: {
        type: Array, // its payment attempts
        default: () => [],
    },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);

// The buttons are only a convenience: the server checks the permission on every request anyway.
const canCreate = computed(() => permissions.value.includes('sales.create'));
// Only a PENDING sale can be cancelled: a paid sale cannot (SAL-11).
const canCancel = computed(() => permissions.value.includes('sales.cancel') && props.sale.status === 'pending');

// The server sends this message when it refuses (for example, "A paid sale cannot be cancelled.").
const statusError = computed(() => page.props.errors?.status ?? null);

function cancelSale() {
    // Cancelling puts the stock back and cannot be undone, so we ask first (SRS section 6.1).
    if (!confirm(`Cancel sale ${props.sale.sale_number}? Its stock goes back to the warehouse. This cannot be undone.`)) {
        return;
    }

    router.post(`/sales/${props.sale.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Sale ${sale.sale_number}`" />

    <div class="space-y-6">
        <PageHeader
            :title="sale.sale_number"
            mono-title
            :subtitle="`Created ${formatDateTime(sale.created_at)} by ${sale.created_by || 'System'}`"
            back-href="/sales"
            back-label="Sales"
        >
            <template #title-meta>
                <StatusBadge :status="sale.status" />
            </template>

            <template #actions>
                <AppButton
                    v-if="canCancel"
                    type="button"
                    variant="ghost"
                    class="text-red-700 hover:bg-red-50 hover:text-red-800"
                    @click="cancelSale"
                >
                    Cancel sale
                </AppButton>
                <AppButton
                    v-if="canCreate"
                    href="/sales/create"
                    variant="primary"
                >
                    New sale
                </AppButton>
            </template>
        </PageHeader>

        <FlashMessage />

        <ErrorAlert
            v-if="statusError"
            :message="statusError"
            title="Cannot cancel sale"
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Customer</p>
                <p class="mt-1 font-semibold text-ink">{{ sale.customer.name }}</p>
                <p v-if="sale.customer.phone" class="mt-0.5 text-xs text-ink-muted">{{ sale.customer.phone }}</p>
                <p v-if="sale.customer.email" class="text-xs text-ink-muted">{{ sale.customer.email }}</p>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Creation & Cashier</p>
                <p class="mt-1 font-semibold text-ink">{{ formatDateTime(sale.created_at) }}</p>
                <p class="mt-0.5 text-xs text-ink-muted">Cashier: {{ sale.created_by || 'System' }}</p>
            </Card>

            <Card padding="md">
                <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Total Amount</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-forest">{{ formatMoney(sale.total_amount, sale.currency) }}</p>
            </Card>
        </div>

        <SalePayments :sale="sale" :payments="payments" />

        <section class="space-y-3">
            <h2 class="text-lg font-semibold text-ink">Lines</h2>

            <!-- Desktop table: from 640px upward -->
            <div class="hidden overflow-hidden rounded-md border border-border bg-surface shadow-2xs sm:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-border bg-stone/40 text-xs font-medium text-ink-muted">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Warehouse</th>
                            <th class="px-4 py-3 text-right">Quantity</th>
                            <th class="px-4 py-3 text-right">Unit price</th>
                            <th class="px-4 py-3 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <tr v-for="line in sale.lines" :key="line.id">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-ink-muted">{{ line.product.sku }}</span>
                                <span class="block font-medium text-ink">{{ line.product.name }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-muted">{{ line.warehouse }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-ink">
                                {{ line.quantity }} {{ line.product.unit }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums text-ink">
                                {{ formatMoney(line.unit_price, sale.currency) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium tabular-nums text-forest">
                                {{ formatMoney(line.line_total, sale.currency) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards: below 640px -->
            <div class="space-y-3 sm:hidden">
                <div
                    v-for="line in sale.lines"
                    :key="line.id"
                    class="rounded-md border border-border bg-surface p-3.5 shadow-2xs"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-ink">{{ line.product.name }}</p>
                            <p class="font-mono text-xs text-ink-muted">{{ line.product.sku }} · {{ line.warehouse }}</p>
                        </div>
                        <span class="font-mono font-semibold tabular-nums text-forest">
                            {{ formatMoney(line.line_total, sale.currency) }}
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-ink-muted">
                        {{ line.quantity }} {{ line.product.unit }} × {{ formatMoney(line.unit_price, sale.currency) }}
                    </p>
                </div>
            </div>
        </section>

        <Card padding="md">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <p class="text-xl font-bold tabular-nums text-ink">
                    Total: {{ formatMoney(sale.total_amount, sale.currency) }}
                </p>
                <p v-if="sale.status === 'pending'" class="max-w-md text-xs text-ink-muted">
                    The stock of these lines is held while the customer pays. A sale that stays unpaid is cancelled automatically.
                </p>
                <p v-else-if="sale.status === 'cancelled'" class="max-w-md text-xs text-ink-muted">
                    This sale is cancelled and its stock was put back.
                </p>
                <p v-else class="max-w-md text-xs text-ink-muted">
                    This sale is paid, so it cannot be cancelled.
                </p>
            </div>
        </Card>
    </div>
</template>
