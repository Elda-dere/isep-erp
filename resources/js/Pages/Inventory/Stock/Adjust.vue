<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FlashMessage from '../../../Components/FlashMessage.vue';

const props = defineProps({
    products: Array, // each has id, sku, name and unit.symbol
    warehouses: Array,
    selected: Object, // product_id / warehouse_id from the URL, or null
});

// Use a preselected value only if it really exists in the list.
function pick(list, id) {
    return list.some((item) => item.id === id) ? id : '';
}

// Form 1: opening stock (INV-05). Allowed once per product and warehouse.
const opening = useForm({
    product_id: pick(props.products, props.selected?.product_id),
    warehouse_id: pick(props.warehouses, props.selected?.warehouse_id),
    quantity: '',
    note: '',
});

// Form 2: manual adjustment (INV-06). "direction" exists only on this page: before sending,
// it is turned into the sign of the quantity the server expects.
const adjustment = useForm({
    product_id: pick(props.products, props.selected?.product_id),
    warehouse_id: pick(props.warehouses, props.selected?.warehouse_id),
    direction: '',
    quantity: '',
    note: '',
});

function submitOpening() {
    // The server checks everything again. Errors come back into opening.errors.
    opening.post('/stock/opening', {
        preserveScroll: true,
        onSuccess: () => opening.reset('quantity', 'note'),
    });
}

function submitAdjustment() {
    // Phone number keyboards often have no minus sign, so the user picks "add" or "remove"
    // and types a plain positive number. We add the "-" ourselves.
    if (!adjustment.direction) {
        adjustment.setError('direction', 'Choose whether to add or remove stock.');
        return;
    }

    adjustment
        .transform((data) => ({
            product_id: data.product_id,
            warehouse_id: data.warehouse_id,
            // Text, not a number: the decimals stay exact. "remove 3.5" becomes "-3.5".
            quantity: (data.direction === 'remove' ? '-' : '') + data.quantity.trim(),
            note: data.note,
        }))
        .post('/stock/adjustments', {
            preserveScroll: true,
            onSuccess: () => adjustment.reset('quantity', 'note', 'direction'),
        });
}

function label(product) {
    return `${product.sku} — ${product.name}${product.unit ? ` (${product.unit.symbol})` : ''}`;
}
</script>

<template>
    <Head title="Adjust stock" />

    <div class="max-w-2xl">
        <Link href="/stock" class="text-sm text-gray-600 underline">← Stock overview</Link>
        <h1 class="mt-2 text-2xl font-semibold">Adjust stock</h1>

        <FlashMessage class="mt-4" />

        <!-- Opening stock -->
        <section class="mt-6 rounded border bg-white p-4">
            <h2 class="text-lg font-semibold">Opening stock</h2>
            <p class="mt-1 text-sm text-gray-500">
                The starting quantity of a product in a warehouse. It can be entered only once for each product and warehouse.
                Later changes are adjustments.
            </p>

            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submitOpening">
                <div>
                    <label for="opening_product" class="block text-sm font-medium">Product</label>
                    <select id="opening_product" v-model="opening.product_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Choose a product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ label(product) }}</option>
                    </select>
                    <p v-if="opening.errors.product_id" class="mt-1 text-sm text-red-700">{{ opening.errors.product_id }}</p>
                </div>

                <div>
                    <label for="opening_warehouse" class="block text-sm font-medium">Warehouse</label>
                    <select id="opening_warehouse" v-model="opening.warehouse_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Choose a warehouse</option>
                        <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                    </select>
                    <p v-if="opening.errors.warehouse_id" class="mt-1 text-sm text-red-700">{{ opening.errors.warehouse_id }}</p>
                </div>

                <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot for decimals: 2.5 -->
                <div>
                    <label for="opening_quantity" class="block text-sm font-medium">Quantity</label>
                    <input id="opening_quantity" v-model="opening.quantity" type="text" inputmode="decimal" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                    <p v-if="opening.errors.quantity" class="mt-1 text-sm text-red-700" role="alert">{{ opening.errors.quantity }}</p>
                </div>

                <div>
                    <label for="opening_note" class="block text-sm font-medium">Note (optional)</label>
                    <input id="opening_note" v-model="opening.note" type="text" maxlength="1000" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                    <p v-if="opening.errors.note" class="mt-1 text-sm text-red-700">{{ opening.errors.note }}</p>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" :disabled="opening.processing" class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50">
                        {{ opening.processing ? 'Saving…' : 'Record opening stock' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Manual adjustment -->
        <section class="mt-6 rounded border bg-white p-4">
            <h2 class="text-lg font-semibold">Adjustment</h2>
            <p class="mt-1 text-sm text-gray-500">
                A correction after a count, damage or loss. A note is required, and stock can never go below zero.
            </p>

            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submitAdjustment">
                <div>
                    <label for="adjust_product" class="block text-sm font-medium">Product</label>
                    <select id="adjust_product" v-model="adjustment.product_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Choose a product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">{{ label(product) }}</option>
                    </select>
                    <p v-if="adjustment.errors.product_id" class="mt-1 text-sm text-red-700">{{ adjustment.errors.product_id }}</p>
                </div>

                <div>
                    <label for="adjust_warehouse" class="block text-sm font-medium">Warehouse</label>
                    <select id="adjust_warehouse" v-model="adjustment.warehouse_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Choose a warehouse</option>
                        <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                    </select>
                    <p v-if="adjustment.errors.warehouse_id" class="mt-1 text-sm text-red-700">{{ adjustment.errors.warehouse_id }}</p>
                </div>

                <div>
                    <label for="adjust_direction" class="block text-sm font-medium">Change</label>
                    <select id="adjust_direction" v-model="adjustment.direction" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                        <option value="" disabled>Add or remove?</option>
                        <option value="add">Add stock</option>
                        <option value="remove">Remove stock</option>
                    </select>
                    <p v-if="adjustment.errors.direction" class="mt-1 text-sm text-red-700">{{ adjustment.errors.direction }}</p>
                </div>

                <div>
                    <label for="adjust_quantity" class="block text-sm font-medium">Quantity</label>
                    <input id="adjust_quantity" v-model="adjustment.quantity" type="text" inputmode="decimal" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                    <!-- This is where "Insufficient stock: available 2.000, requested 2.500" appears (INV-07) -->
                    <p v-if="adjustment.errors.quantity" class="mt-1 text-sm text-red-700" role="alert">{{ adjustment.errors.quantity }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="adjust_note" class="block text-sm font-medium">Note (required)</label>
                    <input id="adjust_note" v-model="adjustment.note" type="text" maxlength="1000" placeholder="For example: damaged in storage" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                    <p v-if="adjustment.errors.note" class="mt-1 text-sm text-red-700">{{ adjustment.errors.note }}</p>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" :disabled="adjustment.processing" class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50">
                        {{ adjustment.processing ? 'Saving…' : 'Save adjustment' }}
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>