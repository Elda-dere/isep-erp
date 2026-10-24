<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    product: Object, // null when creating, the product when editing
    categories: Array,
    units: Array,
});

const isEdit = computed(() => props.product !== null);

// useForm keeps the values, sends them, and remembers the server's validation errors.
// Prices stay as TEXT ("10.50"): this keeps the decimals exact.
const form = useForm({
    category_id: props.product?.category_id ?? '',
    unit_id: props.product?.unit_id ?? '',
    sku: props.product?.sku ?? '',
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    cost_price: props.product?.cost_price ?? '0.00',
    selling_price: props.product?.selling_price ?? '0.00',
    reorder_level: props.product?.reorder_level ?? '0.000',
    is_active: props.product?.is_active ?? true,
});

// PRD-06: warn (do not block) when the selling price is lower than the cost price.
// Number() is used only for this on-screen warning.
const belowCost = computed(() => {
    const cost = Number(form.cost_price);
    const price = Number(form.selling_price);
    return form.cost_price !== '' && form.selling_price !== '' && price < cost;
});

// Edit sends PUT /products/{id}; create sends POST /products.
// On a validation error the server sends us back here with the messages in form.errors.
function submit() {
    if (isEdit.value) {
        form.put(`/products/${props.product.id}`);
    } else {
        form.post('/products');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit product' : 'New product'" />

    <div class="max-w-2xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit product' : 'New product' }}</h1>

        <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div>
                <label for="sku" class="block text-sm font-medium">SKU</label>
                <input id="sku" v-model="form.sku" type="text" maxlength="100" class="mt-1 h-11 w-full rounded border border-gray-300 px-3 font-mono" />
                <p v-if="form.errors.sku" class="mt-1 text-sm text-red-700">{{ form.errors.sku }}</p>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" maxlength="180" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium">Category</label>
                <select id="category_id" v-model="form.category_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                    <option value="" disabled>Choose a category</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-700">{{ form.errors.category_id }}</p>
            </div>

            <div>
                <label for="unit_id" class="block text-sm font-medium">Unit</label>
                <select id="unit_id" v-model="form.unit_id" class="mt-1 h-11 w-full rounded border border-gray-300 px-3">
                    <option value="" disabled>Choose a unit</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                </select>
                <p v-if="form.errors.unit_id" class="mt-1 text-sm text-red-700">{{ form.errors.unit_id }}</p>
            </div>

            <!-- inputmode="decimal" shows the number keyboard on phones (NFR-MOB-02) -->
            <div>
                <label for="cost_price" class="block text-sm font-medium">Cost price (ETB)</label>
                <input id="cost_price" v-model="form.cost_price" type="text" inputmode="decimal" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.cost_price" class="mt-1 text-sm text-red-700">{{ form.errors.cost_price }}</p>
            </div>

            <div>
                <label for="selling_price" class="block text-sm font-medium">Selling price (ETB)</label>
                <input id="selling_price" v-model="form.selling_price" type="text" inputmode="decimal" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.selling_price" class="mt-1 text-sm text-red-700">{{ form.errors.selling_price }}</p>
                <p v-if="belowCost" class="mt-1 text-sm text-amber-800" role="status">
                    Warning: the selling price is lower than the cost price.
                </p>
            </div>

            <div>
                <label for="reorder_level" class="block text-sm font-medium">Reorder level</label>
                <input id="reorder_level" v-model="form.reorder_level" type="text" inputmode="decimal" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.reorder_level" class="mt-1 text-sm text-red-700">{{ form.errors.reorder_level }}</p>
            </div>

            <div class="flex items-end">
                <label for="is_active" class="flex min-h-11 items-center gap-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="h-5 w-5" />
                    <span class="text-sm font-medium">Active</span>
                </label>
            </div>

            <div class="sm:col-span-2">
                <label for="description" class="block text-sm font-medium">Description (optional)</label>
                <textarea id="description" v-model="form.description" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-700">{{ form.errors.description }}</p>
            </div>

            <div class="flex gap-3 sm:col-span-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
                <Link href="/products" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>