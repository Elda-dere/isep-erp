<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { formatCents, formatMoney, lineCents } from '../../format.js';

const props = defineProps({
    customers: Array,
    defaultCustomerId: Number, // the Walk-in customer, or null if it is missing
    warehouses: Array,
    products: Array, // active products: id, sku, name, selling_price, unit.symbol
    stock: [Object, Array], // { productId: { warehouseId: "100.000" } }
    canOverridePrice: Boolean,
});

// useForm keeps the values, sends them, and remembers the server's validation errors.
// Quantities and prices stay as TEXT ("2.50"): this keeps the decimals exact.
const form = useForm({
    customer_id: props.defaultCustomerId ?? '',
    lines: [],
});

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

    <div class="max-w-4xl">
        <h1 class="text-2xl font-semibold">New sale</h1>
        <p class="mt-1 text-sm text-gray-500">Stock is taken out when you save, and held while the customer pays.</p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div class="max-w-md">
                <label for="customer_id" class="block text-sm font-medium">Customer</label>
                <select id="customer_id" v-model="form.customer_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                    <option value="" disabled>Choose a customer</option>
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
                </select>
                <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-700" role="alert">{{ form.errors.customer_id }}</p>
            </div>

            <div>
                <label for="search" class="block text-sm font-medium">Add a product</label>
                <input
                    id="search"
                    v-model="search"
                    type="search"
                    autocomplete="off"
                    placeholder="Search by name or SKU"
                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                />

                <ul v-if="matches.length" class="mt-2 divide-y rounded border bg-white">
                    <li v-for="product in matches" :key="product.id">
                        <button
                            type="button"
                            class="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-gray-50"
                            @click="addProduct(product)"
                        >
                            <span>
                                <span class="font-mono text-xs text-gray-500">{{ product.sku }}</span>
                                {{ product.name }}
                            </span>
                            <span class="text-sm">{{ formatMoney(product.selling_price) }}</span>
                        </button>
                    </li>
                </ul>
                <p v-else-if="search.trim() !== ''" class="mt-2 text-sm text-gray-500">No product found.</p>
            </div>

            <fieldset>
                <legend class="text-lg font-semibold">Lines</legend>

                <!-- Messages from the server that belong to the lines as a whole, for example
                     "Line 2: product SKU-002: only 40.000 is available in Main store, 60.000 requested" -->
                <p v-if="form.errors.lines" role="alert" class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ form.errors.lines }}
                </p>

                <p v-if="form.lines.length === 0" class="mt-2 text-sm text-gray-500">
                    No products yet. Search above and tap a product to add it.
                </p>

                <div class="mt-3 space-y-3">
                    <div v-for="(line, index) in form.lines" :key="line.uid" class="rounded border bg-white p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-mono text-xs text-gray-500">{{ productsById.get(line.product_id)?.sku }}</p>
                                <p class="font-medium">{{ productsById.get(line.product_id)?.name }}</p>
                            </div>
                            <button
                                type="button"
                                :aria-label="`Remove line ${index + 1}`"
                                class="flex min-h-11 items-center rounded px-3 text-red-700 hover:bg-red-50"
                                @click="removeLine(index)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="mt-2 grid gap-3 sm:grid-cols-4">
                            <div>
                                <label :for="`warehouse-${line.uid}`" class="block text-sm font-medium">Warehouse</label>
                                <select
                                    :id="`warehouse-${line.uid}`"
                                    v-model="line.warehouse_id"
                                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                                >
                                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">
                                    Available: {{ available(line) }} {{ productsById.get(line.product_id)?.unit?.symbol }}
                                </p>
                                <p v-if="form.errors[`lines.${index}.warehouse_id`]" class="mt-1 text-sm text-red-700">
                                    {{ form.errors[`lines.${index}.warehouse_id`] }}
                                </p>
                            </div>

                            <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot: 2.5 -->
                            <div>
                                <label :for="`quantity-${line.uid}`" class="block text-sm font-medium">Quantity</label>
                                <input
                                    :id="`quantity-${line.uid}`"
                                    v-model="line.quantity"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                                />
                                <p v-if="form.errors[`lines.${index}.quantity`]" class="mt-1 text-sm text-red-700">
                                    {{ form.errors[`lines.${index}.quantity`] }}
                                </p>
                            </div>

                            <div>
                                <label :for="`price-${line.uid}`" class="block text-sm font-medium">Unit price (ETB)</label>
                                <!-- Only users with sales.price_override may change a price (SAL-04). The server checks again. -->
                                <input
                                    v-if="canOverridePrice"
                                    :id="`price-${line.uid}`"
                                    v-model="line.unit_price"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                                />
                                <p v-else class="mt-1 flex h-11 items-center">{{ line.unit_price }}</p>
                                <p v-if="form.errors[`lines.${index}.unit_price`]" class="mt-1 text-sm text-red-700">
                                    {{ form.errors[`lines.${index}.unit_price`] }}
                                </p>
                            </div>

                            <div>
                                <p class="block text-sm font-medium">Line total</p>
                                <p class="mt-1 flex h-11 items-center font-medium">{{ lineTotal(line) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <div class="rounded border bg-white p-3">
                <p class="text-xl font-semibold">Total: ETB {{ total }}</p>
                <p class="text-xs text-gray-500">Shown for convenience. The server calculates the real total from the lines.</p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Create sale' }}
                </button>
                <Link href="/dashboard" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>