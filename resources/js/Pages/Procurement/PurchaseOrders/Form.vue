<script setup>
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';

const props = defineProps({
    order: Object, // null when creating, the draft when editing
    suppliers: Array,
    products: Array, // each has id, sku, name, cost_price, is_active and unit.symbol
});

const isEdit = computed(() => props.order !== null);

const emptyLine = () => ({ product_id: '', quantity: '', unit_cost: '' });

// useForm keeps the values, sends them, and remembers the server's validation errors.
// Quantities and costs stay as TEXT ("2.50"): this keeps the decimals exact.
const form = useForm({
    supplier_id: props.order?.supplier_id ?? '',
    lines: props.order?.lines?.length ? props.order.lines.map((line) => ({ ...line })) : [emptyLine()],
});

function addLine() {
    form.lines.push(emptyLine());
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

// When a product is chosen, its cost price becomes the line's starting unit cost (you can change it).
// We use @change instead of v-model here so that this runs after we have set the product.
function setProduct(line, value) {
    line.product_id = value === '' ? '' : Number(value);

    const product = props.products.find((item) => item.id === line.product_id);
    if (product && line.unit_cost === '') {
        line.unit_cost = product.cost_price;
    }
}

// ---- Totals shown on screen. DISPLAY ONLY: the server calculates the real total. ----
// We use whole numbers (BigInt) instead of decimals, so there is no floating-point error.

// "12.5" becomes 12500n with scale 3 (thousandths). Returns null if the text is not a valid number.
function toScaled(text, scale) {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(String(text ?? '').trim());
    if (!match) return null;

    const decimals = match[2] ?? '';
    if (decimals.length > scale) return null;

    return BigInt(match[1] + decimals.padEnd(scale, '0'));
}

// quantity x unit cost in cents, rounded half-up like the server (DR-03).
function lineCents(line) {
    const quantity = toScaled(line.quantity, 3); // thousandths
    const cost = toScaled(line.unit_cost, 2); // cents

    if (quantity === null || cost === null) return null;

    return (quantity * cost + 500n) / 1000n;
}

// 2501n becomes "25.01", and 1234567n becomes "12,345.67".
function formatCents(cents) {
    const text = cents.toString().padStart(3, '0');
    const whole = text.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${whole}.${text.slice(-2)}`;
}

function lineTotal(line) {
    const cents = lineCents(line);
    return cents === null ? '—' : formatCents(cents);
}

const total = computed(() => {
    let sum = 0n;

    for (const line of form.lines) {
        sum += lineCents(line) ?? 0n;
    }

    return formatCents(sum);
});

function label(product) {
    const unit = product.unit ? ` (${product.unit.symbol})` : '';
    return `${product.sku} — ${product.name}${unit}${product.is_active ? '' : ' — inactive'}`;
}

// Edit sends PUT /purchase-orders/{id}; create sends POST /purchase-orders.
function submit() {
    if (isEdit.value) {
        form.put(`/purchase-orders/${props.order.id}`);
    } else {
        form.post('/purchase-orders');
    }
}

// Back to the order itself when editing, or to the list when creating.
function cancel() {
    router.visit(isEdit.value ? `/purchase-orders/${props.order.id}` : '/purchase-orders');
}
</script>

<template>
    <Head :title="isEdit ? `Edit ${order.order_number}` : 'New purchase order'" />

    <div class="max-w-4xl">
        <h1 class="text-2xl font-semibold">
            {{ isEdit ? `Edit draft ${order.order_number}` : 'New purchase order' }}
        </h1>
        <p v-if="isEdit" class="mt-1 text-sm text-gray-500">
            Only a draft can be changed. The order number never changes.
        </p>

        <FlashMessage class="mt-4" />

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div class="max-w-md">
                <label for="supplier_id" class="block text-sm font-medium">Supplier</label>
                <select id="supplier_id" v-model="form.supplier_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                    <option value="" disabled>Choose a supplier</option>
                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                </select>
                <p v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-700">{{ form.errors.supplier_id }}</p>
            </div>

            <fieldset>
                <legend class="text-lg font-semibold">Lines</legend>

                <!-- Messages from the server that belong to the lines as a whole,
                     for example "product SKU-001 is inactive and cannot be added" -->
                <p v-if="form.errors.lines" role="alert" class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ form.errors.lines }}
                </p>

                <div class="mt-3 space-y-3">
                    <div v-for="(line, index) in form.lines" :key="index" class="rounded border bg-white p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-600">Line {{ index + 1 }}</p>
                            <!-- The last remaining line cannot be removed: an order needs at least one -->
                            <button
                                type="button"
                                :disabled="form.lines.length === 1"
                                :aria-label="`Remove line ${index + 1}`"
                                class="flex min-h-11 items-center rounded px-3 text-red-700 hover:bg-red-50 disabled:opacity-40"
                                @click="removeLine(index)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="mt-2 grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-3">
                                <label :for="`product-${index}`" class="block text-sm font-medium">Product</label>
                                <select
                                    :id="`product-${index}`"
                                    :value="line.product_id"
                                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                                    @change="setProduct(line, $event.target.value)"
                                >
                                    <option value="" disabled>Choose a product</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{ label(product) }}</option>
                                </select>
                                <p v-if="form.errors[`lines.${index}.product_id`]" class="mt-1 text-sm text-red-700">
                                    {{ form.errors[`lines.${index}.product_id`] }}
                                </p>
                            </div>

                            <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot: 2.5 -->
                            <div>
                                <label :for="`quantity-${index}`" class="block text-sm font-medium">Quantity</label>
                                <input
                                    :id="`quantity-${index}`"
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
                                <label :for="`cost-${index}`" class="block text-sm font-medium">Unit cost (ETB)</label>
                                <input
                                    :id="`cost-${index}`"
                                    v-model="line.unit_cost"
                                    type="text"
                                    inputmode="decimal"
                                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3"
                                />
                                <p v-if="form.errors[`lines.${index}.unit_cost`]" class="mt-1 text-sm text-red-700">
                                    {{ form.errors[`lines.${index}.unit_cost`] }}
                                </p>
                            </div>

                            <div>
                                <p class="block text-sm font-medium">Line total</p>
                                <p class="mt-1 flex h-11 items-center">{{ lineTotal(line) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="mt-3 flex min-h-11 items-center rounded border px-4" @click="addLine">
                    Add line
                </button>
            </fieldset>

            <div class="rounded border bg-white p-3">
                <p class="text-lg font-semibold">Total: ETB {{ total }}</p>
                <p class="text-xs text-gray-500">Shown for convenience. The server calculates the real total from the lines.</p>
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