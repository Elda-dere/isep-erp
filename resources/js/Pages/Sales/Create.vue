<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import Card from '../../Components/Card.vue';
import ErrorAlert from '../../Components/ErrorAlert.vue';
import FormField from '../../Components/FormField.vue';
import PageHeader from '../../Components/PageHeader.vue';
import SelectInput from '../../Components/SelectInput.vue';
import TextInput from '../../Components/TextInput.vue';
import { formatCents, formatMoney, lineCents } from '../../format.js';

const props = defineProps({
    customers: {
        type: Array,
        required: true,
    },
    defaultCustomerId: {
        type: Number, // the Walk-in customer, or null if it is missing
        default: null,
    },
    warehouses: {
        type: Array,
        required: true,
    },
    products: {
        type: Array, // active products: id, sku, name, selling_price, unit.symbol
        required: true,
    },
    stock: {
        type: [Object, Array], // { productId: { warehouseId: "100.000" } }
        required: true,
    },
    canOverridePrice: {
        type: Boolean,
        default: false,
    },
});

// useForm keeps the values, sends them, and remembers the server's validation errors.
// Quantities and prices stay as TEXT ("2.50"): this keeps the decimals exact.
const form = useForm({
    customer_id: props.defaultCustomerId ?? '',
    lines: [],
});

const customerOptions = computed(() =>
    props.customers.map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const productsById = computed(() => new Map(props.products.map((product) => [product.id, product])));

// ---- Searching: instant, because the page already has every active product ----
const search = ref('');

const matches = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') return [];

    return props.products
        .filter((product) => product.name.toLowerCase().includes(term) || product.sku.toLowerCase().includes(term))
        .slice(0, 8); // a short list is easier to tap on a phone
});

// The warehouse with the most stock of this product is a sensible default. Number() is only used
// here to compare which stock is bigger, never for any amount that is saved.
function bestWarehouse(productId) {
    const levels = props.stock[productId] ?? {};
    let best = null;

    for (const [warehouseId, quantity] of Object.entries(levels)) {
        if (best === null || Number(quantity) > Number(levels[best])) best = warehouseId;
    }

    return Number(best ?? props.warehouses[0]?.id ?? '');
}

// Each line gets a number that never changes, so Vue keeps the right inputs when a line is removed.
let nextKey = 0;

function addProduct(product) {
    form.lines.push({
        uid: nextKey++,
        product_id: product.id,
        warehouse_id: bestWarehouse(product.id),
        quantity: '1',
        unit_price: product.selling_price, // the catalogue price (SAL-03)
    });

    search.value = '';
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

// What is in stock for this line's product and warehouse, as the exact string the server sent.
function available(line) {
    return props.stock[line.product_id]?.[line.warehouse_id] ?? '0.000';
}

function lineTotal(line) {
    const cents = lineCents(line.quantity, line.unit_price);
    return cents === null ? '—' : formatCents(cents);
}

const total = computed(() => {
    let sum = 0n;

    for (const line of form.lines) {
        sum += lineCents(line.quantity, line.unit_price) ?? 0n;
    }

    return formatCents(sum);
});

function submit() {
    if (form.lines.length === 0) {
        form.setError('lines', 'Add at least one product.');
        return;
    }

    form
        .transform((data) => ({
            customer_id: data.customer_id,
            lines: data.lines.map((line) => ({
                product_id: line.product_id,
                warehouse_id: line.warehouse_id,
                quantity: line.quantity,
                // Only people with the price override permission send a price. Everyone else always
                // sells at the CURRENT catalogue price, chosen by the server.
                ...(props.canOverridePrice ? { unit_price: line.unit_price } : {}),
            })),
        }))
        .post('/sales');
}
</script>

<template>
    <Head title="New sale" />

    <div class="max-w-4xl space-y-6">
        <PageHeader
            title="New sale"
            subtitle="Stock is taken out when you save, and held while the customer pays."
            back-href="/sales"
            back-label="Sales"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <Card padding="md">
                <div class="max-w-md">
                    <FormField
                        for-id="customer_id"
                        label="Customer"
                        required
                        :error="form.errors.customer_id"
                    >
                        <SelectInput
                            id="customer_id"
                            v-model="form.customer_id"
                            placeholder="Choose a customer"
                            placeholder-disabled
                            :options="customerOptions"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>
            </Card>

            <Card padding="md">
                <div>
                    <label for="search" class="block text-sm font-medium text-ink">Add a product</label>
                    <div class="relative mt-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-muted">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <circle cx="9" cy="9" r="6" />
                                <path d="M13.5 13.5L18 18" stroke-linecap="round" />
                            </svg>
                        </div>
                        <TextInput
                            id="search"
                            v-model="search"
                            type="search"
                            autocomplete="off"
                            placeholder="Search by name or SKU"
                            class="pl-9"
                        />
                    </div>

                    <ul v-if="matches.length" class="mt-2 divide-y divide-border rounded-md border border-border bg-surface shadow-xs">
                        <li v-for="product in matches" :key="product.id">
                            <button
                                type="button"
                                class="flex min-h-11 w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left transition-colors hover:bg-stone/60 focus:bg-stone/60 focus:outline-none"
                                @click="addProduct(product)"
                            >
                                <span class="min-w-0">
                                    <span class="font-mono text-xs text-ink-muted">{{ product.sku }}</span>
                                    <span class="ml-2 font-medium text-ink">{{ product.name }}</span>
                                </span>
                                <span class="shrink-0 font-medium tabular-nums text-forest">{{ formatMoney(product.selling_price) }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else-if="search.trim() !== ''" class="mt-2 text-sm text-ink-muted">
                        No product found matching "{{ search.trim() }}".
                    </p>
                </div>
            </Card>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink">Lines</h2>
                    <span v-if="form.lines.length" class="text-xs text-ink-muted">
                        {{ form.lines.length }} {{ form.lines.length === 1 ? 'item' : 'items' }}
                    </span>
                </div>

                <!-- Messages from the server that belong to the lines as a whole -->
                <ErrorAlert
                    v-if="form.errors.lines"
                    :message="form.errors.lines"
                />

                <div
                    v-if="form.lines.length === 0"
                    class="rounded-md border border-dashed border-border p-8 text-center text-sm text-ink-muted"
                >
                    No products yet. Search above and tap a product to add it.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="(line, index) in form.lines"
                        :key="line.uid"
                        class="rounded-md border border-border bg-surface p-4 shadow-2xs"
                    >
                        <div class="flex items-start justify-between gap-3 border-b border-border-subtle pb-3">
                            <div class="min-w-0">
                                <p class="font-mono text-xs text-ink-muted">{{ productsById.get(line.product_id)?.sku }}</p>
                                <p class="font-medium text-ink">{{ productsById.get(line.product_id)?.name }}</p>
                            </div>
                            <AppButton
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-red-700 hover:bg-red-50 hover:text-red-800"
                                :aria-label="`Remove line ${index + 1}`"
                                @click="removeLine(index)"
                            >
                                Remove
                            </AppButton>
                        </div>

                        <div class="mt-3 grid gap-3 sm:grid-cols-4">
                            <div>
                                <label :for="`warehouse-${line.uid}`" class="block text-xs font-medium text-ink">Warehouse</label>
                                <select
                                    :id="`warehouse-${line.uid}`"
                                    v-model="line.warehouse_id"
                                    class="mt-1 h-11 w-full rounded border border-border bg-surface px-3 text-sm text-ink focus:border-forest focus:outline-none focus:ring-1 focus:ring-forest"
                                >
                                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                                </select>
                                <p class="mt-1 text-xs text-ink-muted">
                                    Available: {{ available(line) }} {{ productsById.get(line.product_id)?.unit?.symbol }}
                                </p>
                                <p v-if="form.errors[`lines.${index}.warehouse_id`]" class="mt-1 text-xs text-red-700" role="alert">
                                    {{ form.errors[`lines.${index}.warehouse_id`] }}
                                </p>
                            </div>

                            <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot: 2.5 -->
                            <div>
                                <label :for="`quantity-${line.uid}`" class="block text-xs font-medium text-ink">Quantity</label>
                                <TextInput
                                    :id="`quantity-${line.uid}`"
                                    v-model="line.quantity"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1"
                                />
                                <p v-if="form.errors[`lines.${index}.quantity`]" class="mt-1 text-xs text-red-700" role="alert">
                                    {{ form.errors[`lines.${index}.quantity`] }}
                                </p>
                            </div>

                            <div>
                                <label :for="`price-${line.uid}`" class="block text-xs font-medium text-ink">Unit price (ETB)</label>
                                <!-- Only users with sales.price_override may change a price (SAL-04). The server checks again. -->
                                <TextInput
                                    v-if="canOverridePrice"
                                    :id="`price-${line.uid}`"
                                    v-model="line.unit_price"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1"
                                />
                                <div v-else class="mt-1 flex h-11 items-center rounded border border-border-subtle bg-stone/50 px-3 text-sm font-medium tabular-nums text-ink">
                                    {{ line.unit_price }}
                                </div>
                                <p v-if="form.errors[`lines.${index}.unit_price`]" class="mt-1 text-xs text-red-700" role="alert">
                                    {{ form.errors[`lines.${index}.unit_price`] }}
                                </p>
                            </div>

                            <div>
                                <p class="block text-xs font-medium text-ink">Line total</p>
                                <div class="mt-1 flex h-11 items-center font-semibold tabular-nums text-forest">
                                    ETB {{ lineTotal(line) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <Card padding="md">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-ink-muted">Estimated Total</p>
                        <p class="mt-0.5 text-2xl font-bold tabular-nums text-forest">ETB {{ total }}</p>
                    </div>
                    <p class="max-w-xs text-xs text-ink-muted sm:text-right">
                        Shown for convenience. The server calculates the real total from the lines.
                    </p>
                </div>
            </Card>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <AppButton
                    type="submit"
                    variant="primary"
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving…' : 'Create sale' }}
                </AppButton>
                <AppButton
                    variant="secondary"
                    href="/dashboard"
                    :disabled="form.processing"
                >
                    Cancel
                </AppButton>
            </div>
        </form>
    </div>
</template>
