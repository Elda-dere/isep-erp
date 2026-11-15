<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import TextInput from '../../../Components/TextInput.vue';
import { formatCents, lineCents } from '../../../format.js';

const props = defineProps({
    order: {
        type: Object, // null when creating, draft when editing
        default: null,
    },
    suppliers: {
        type: Array,
        required: true,
    },
    products: {
        type: Array, // each has id, sku, name, unit_id, cost_price, is_active, unit: { symbol }
        required: true,
    },
});

const isEdit = computed(() => props.order !== null);

const emptyLine = () => ({ product_id: '', quantity: '', unit_cost: '' });

// useForm keeps values, sends them, and remembers server validation errors.
// Quantities and costs stay exact strings ("2.50", "10.000") to eliminate floating-point error.
const form = useForm({
    supplier_id: props.order?.supplier_id ?? '',
    lines: props.order?.lines?.length
        ? props.order.lines.map((line) => ({ ...line }))
        : [emptyLine()],
});

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

// When a product is chosen, its cost price becomes the line's starting unit cost (can be modified).
function setProduct(line, value) {
    line.product_id = value === '' ? '' : parseInt(value, 10);

    const product = props.products.find((item) => item.id === line.product_id);
    if (product && line.unit_cost === '') {
        line.unit_cost = product.cost_price;
    }
}

// Live line totals and order total computed strictly with BigInt integer arithmetic via lineCents()
function lineTotal(line) {
    const cents = lineCents(line.quantity, line.unit_cost);
    return cents === null ? '—' : `ETB ${formatCents(cents)}`;
}

const total = computed(() => {
    let sum = 0n;
    for (const line of form.lines) {
        sum += lineCents(line.quantity, line.unit_cost) ?? 0n;
    }
    return formatCents(sum);
});

function productLabel(product) {
    const unit = product.unit?.symbol ? ` (${product.unit.symbol})` : '';
    return `${product.sku} — ${product.name}${unit}${product.is_active ? '' : ' — inactive'}`;
}

const supplierOptions = computed(() =>
    props.suppliers.map((s) => ({
        value: s.id,
        label: s.name,
    })),
);

const productOptions = computed(() =>
    props.products.map((p) => ({
        value: p.id,
        label: productLabel(p),
    })),
);

function submit() {
    if (isEdit.value) {
        form.put(`/purchase-orders/${props.order.id}`);
    } else {
        form.post('/purchase-orders');
    }
}

function cancel() {
    router.visit(isEdit.value ? `/purchase-orders/${props.order.id}` : '/purchase-orders');
}
</script>

<template>
    <Head :title="isEdit ? `Edit ${order.order_number}` : 'New purchase order'" />

    <div class="max-w-4xl space-y-6">
        <PageHeader
            :title="isEdit ? `Edit draft ${order.order_number}` : 'New purchase order'"
            :subtitle="isEdit ? 'Only a draft can be changed. The order number never changes.' : 'Create a draft purchase order with vendor and line items.'"
            :back-href="isEdit ? `/purchase-orders/${order.id}` : '/purchase-orders'"
            back-label="Purchase orders"
            :mono-title="isEdit"
        />

        <FlashMessage />

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Supplier Card -->
            <Card padding="md">
                <div class="max-w-md">
                    <FormField
                        for-id="supplier_id"
                        label="Supplier"
                        required
                        hint="Choose the vendor who will fulfill this order"
                        :error="form.errors.supplier_id"
                    >
                        <SelectInput
                            id="supplier_id"
                            v-model="form.supplier_id"
                            :options="supplierOptions"
                            placeholder="Choose a supplier"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>
            </Card>

            <!-- Lines Container Card -->
            <Card padding="md">
                <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                    <div>
                        <h2 class="text-base font-semibold text-ink">Order Lines</h2>
                        <p class="text-xs text-ink-muted">Add at least one product line with quantity and unit cost.</p>
                    </div>

                    <span class="font-mono text-xs text-ink-muted tabular-nums">
                        {{ form.lines.length }} {{ form.lines.length === 1 ? 'line' : 'lines' }}
                    </span>
                </div>

                <!-- Global lines error banner -->
                <div v-if="form.errors.lines" class="mt-4">
                    <ErrorAlert
                        :message="form.errors.lines"
                        title="Invalid order lines"
                    />
                </div>

                <!-- Line Cards Stack -->
                <div class="mt-4 space-y-4">
                    <div
                        v-for="(line, index) in form.lines"
                        :key="index"
                        class="rounded border border-border-subtle bg-stone/40 p-4 transition-colors"
                    >
                        <div class="flex items-center justify-between pb-3 border-b border-border-subtle/70">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-muted">
                                Line {{ index + 1 }}
                            </span>

                            <AppButton
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-red-700 hover:bg-red-50 hover:text-red-800 disabled:opacity-40"
                                :disabled="form.lines.length === 1 || form.processing"
                                :aria-label="`Remove line ${index + 1}`"
                                @click="removeLine(index)"
                            >
                                Remove line
                            </AppButton>
                        </div>

                        <div class="mt-3 grid gap-4 sm:grid-cols-6">
                            <!-- Product Select -->
                            <div class="sm:col-span-3">
                                <FormField
                                    :for-id="`product-${index}`"
                                    label="Product"
                                    required
                                    :error="form.errors[`lines.${index}.product_id`]"
                                >
                                    <SelectInput
                                        :id="`product-${index}`"
                                        :model-value="line.product_id"
                                        :options="productOptions"
                                        placeholder="Choose a product"
                                        :disabled="form.processing"
                                        @update:model-value="setProduct(line, $event)"
                                    />
                                </FormField>
                            </div>

                            <!-- Quantity Input -->
                            <div class="sm:col-span-1">
                                <FormField
                                    :for-id="`quantity-${index}`"
                                    label="Quantity"
                                    required
                                    hint="Max 3 decimals"
                                    :error="form.errors[`lines.${index}.quantity`]"
                                >
                                    <TextInput
                                        :id="`quantity-${index}`"
                                        v-model="line.quantity"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.000"
                                        :mono="true"
                                        :disabled="form.processing"
                                    />
                                </FormField>
                            </div>

                            <!-- Unit Cost Input -->
                            <div class="sm:col-span-1">
                                <FormField
                                    :for-id="`cost-${index}`"
                                    label="Unit cost (ETB)"
                                    required
                                    hint="Max 2 decimals"
                                    :error="form.errors[`lines.${index}.unit_cost`]"
                                >
                                    <TextInput
                                        :id="`cost-${index}`"
                                        v-model="line.unit_cost"
                                        type="text"
                                        inputmode="decimal"
                                        placeholder="0.00"
                                        :mono="true"
                                        :disabled="form.processing"
                                    />
                                </FormField>
                            </div>

                            <!-- Live Line Total Preview -->
                            <div class="sm:col-span-1">
                                <p class="text-xs font-medium text-ink">Line total</p>
                                <p class="mt-2 flex min-h-11 items-center font-mono text-sm font-semibold text-ink tabular-nums">
                                    {{ lineTotal(line) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Line Action -->
                <div class="mt-4">
                    <AppButton
                        type="button"
                        variant="secondary"
                        :disabled="form.processing"
                        @click="addLine"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M10 4v12M4 10h12" />
                        </svg>
                        <span>Add line</span>
                    </AppButton>
                </div>
            </Card>

            <!-- Order Total Preview Card -->
            <Card padding="md">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Estimated Order Total</p>
                        <p class="mt-1 font-mono text-2xl font-bold tabular-nums text-forest">
                            ETB {{ total }}
                        </p>
                    </div>

                    <p class="text-xs text-ink-muted max-w-xs">
                        Calculated live via exact integer arithmetic. The official total is verified by the backend on save.
                    </p>
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
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Save draft') }}
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
