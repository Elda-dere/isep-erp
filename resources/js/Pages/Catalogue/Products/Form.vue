<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        required: true,
    },
    units: {
        type: Array,
        required: true,
    },
});

const isEdit = computed(() => props.product !== null);

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

const categoryOptions = computed(() =>
    props.categories.map((c) => ({
        value: c.id,
        label: c.name,
    })),
);

const unitOptions = computed(() =>
    props.units.map((u) => ({
        value: u.id,
        label: `${u.name} (${u.symbol})`,
    })),
);

// Exact string-to-scaled-integer parser (scale 2 for ETB currency).
// Zero floating-point math or Number() precision loss.
function parseScaledCents(value) {
    if (typeof value !== 'string') {
        value = String(value ?? '').trim();
    } else {
        value = value.trim();
    }

    if (!value || !/^\d+(?:\.\d{1,2})?$/.test(value)) {
        return null;
    }

    const [wholeStr, fracStr = ''] = value.split('.');
    const paddedFrac = fracStr.padEnd(2, '0').slice(0, 2);

    try {
        return BigInt(wholeStr) * 100n + BigInt(paddedFrac);
    } catch {
        return null;
    }
}

// PRD-06: warn (do not block) when the selling price is lower than the cost price.
// Calculated using exact scaled integer comparison without floating-point arithmetic.
const belowCost = computed(() => {
    if (!form.cost_price || !form.selling_price) {
        return false;
    }

    const cost = parseScaledCents(form.cost_price);
    const price = parseScaledCents(form.selling_price);

    if (cost === null || price === null) {
        return false;
    }

    return price < cost;
});

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

    <div class="max-w-3xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit product' : 'New product'"
            :subtitle="isEdit ? `Update catalogue details for ${product.name}.` : 'Add a new product item to the inventory catalogue.'"
            back-href="/products"
            back-label="Products"
        />

        <Card padding="md">
            <form class="space-y-6" @submit.prevent="submit">
                <!-- Top Row: SKU and Name -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        for-id="sku"
                        label="SKU"
                        required
                        hint="Unique product code"
                        :error="form.errors.sku"
                    >
                        <TextInput
                            id="sku"
                            v-model="form.sku"
                            type="text"
                            maxlength="100"
                            placeholder="e.g. BEV-001"
                            autocomplete="off"
                            :mono="true"
                            :disabled="form.processing"
                            autofocus
                        />
                    </FormField>

                    <FormField
                        for-id="name"
                        label="Product name"
                        required
                        :error="form.errors.name"
                    >
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            maxlength="180"
                            placeholder="e.g. Bottled Spring Water 1L"
                            autocomplete="off"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>

                <!-- Classification: Category and Unit -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        for-id="category_id"
                        label="Category"
                        required
                        :error="form.errors.category_id"
                    >
                        <SelectInput
                            id="category_id"
                            v-model="form.category_id"
                            :options="categoryOptions"
                            placeholder="Choose a category"
                            :disabled="form.processing"
                        />
                    </FormField>

                    <FormField
                        for-id="unit_id"
                        label="Unit of measure"
                        required
                        :error="form.errors.unit_id"
                    >
                        <SelectInput
                            id="unit_id"
                            v-model="form.unit_id"
                            :options="unitOptions"
                            placeholder="Choose a unit"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>

                <!-- Pricing & Stock Thresholds -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <FormField
                        for-id="cost_price"
                        label="Cost price (ETB)"
                        required
                        hint="Decimal up to 2 places"
                        :error="form.errors.cost_price"
                    >
                        <TextInput
                            id="cost_price"
                            v-model="form.cost_price"
                            type="text"
                            inputmode="decimal"
                            placeholder="0.00"
                            autocomplete="off"
                            :mono="true"
                            :disabled="form.processing"
                        />
                    </FormField>

                    <FormField
                        for-id="selling_price"
                        label="Selling price (ETB)"
                        required
                        hint="Decimal up to 2 places"
                        :error="form.errors.selling_price"
                    >
                        <TextInput
                            id="selling_price"
                            v-model="form.selling_price"
                            type="text"
                            inputmode="decimal"
                            placeholder="0.00"
                            autocomplete="off"
                            :mono="true"
                            :disabled="form.processing"
                        />
                    </FormField>

                    <FormField
                        for-id="reorder_level"
                        label="Reorder level"
                        required
                        hint="Decimal up to 3 places"
                        :error="form.errors.reorder_level"
                    >
                        <TextInput
                            id="reorder_level"
                            v-model="form.reorder_level"
                            type="text"
                            inputmode="decimal"
                            placeholder="0.000"
                            autocomplete="off"
                            :mono="true"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>

                <!-- Warning banner: selling price below cost price -->
                <div
                    v-if="belowCost"
                    role="status"
                    class="rounded border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-900 shadow-2xs"
                >
                    <div class="flex items-start gap-2.5">
                        <svg class="h-5 w-5 shrink-0 text-amber-700 mt-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="font-medium">Selling price is lower than cost price</p>
                            <p class="mt-0.5 text-xs text-amber-800">
                                Warning: Selling this item at this price may result in negative gross margins.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Status Checkbox -->
                <div class="rounded border border-border-subtle bg-stone/40 p-4">
                    <label for="is_active" class="flex min-h-11 cursor-pointer items-start gap-3 select-none">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            :disabled="form.processing"
                            class="mt-1 h-4 w-4 rounded border-border text-forest focus:ring-forest focus:ring-offset-1"
                        />
                        <div>
                            <span class="text-sm font-medium text-ink">Active product</span>
                            <p class="mt-0.5 text-xs text-ink-muted">
                                Active products appear in catalog searches, stock counts, point of sale, and purchase orders.
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Description -->
                <FormField
                    for-id="description"
                    label="Description"
                    hint="Optional notes, specifications, or packaging details"
                    :error="form.errors.description"
                >
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Additional details about the product..."
                        :disabled="form.processing"
                        class="block w-full rounded border border-border bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint shadow-2xs transition-colors focus:border-forest focus:outline-none focus:ring-1 focus:ring-forest disabled:bg-stone disabled:opacity-75"
                    />
                </FormField>

                <!-- Actions -->
                <div class="flex flex-wrap items-center gap-3 border-t border-border-subtle pt-4">
                    <AppButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create product') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/products"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
