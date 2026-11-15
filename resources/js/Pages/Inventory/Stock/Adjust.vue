<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import ErrorAlert from '../../../Components/ErrorAlert.vue';
import FlashMessage from '../../../Components/FlashMessage.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import SelectInput from '../../../Components/SelectInput.vue';
import TextInput from '../../../Components/TextInput.vue';

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
    return `${product.sku} — ${product.name}${product.unit?.symbol ? ` (${product.unit.symbol})` : ''}`;
}

const productOptions = computed(() =>
    (props.products ?? []).map((product) => ({
        value: product.id,
        label: label(product),
    })),
);

const warehouseOptions = computed(() =>
    (props.warehouses ?? []).map((warehouse) => ({
        value: warehouse.id,
        label: warehouse.name,
    })),
);

const directionOptions = [
    { value: 'add', label: 'Add stock' },
    { value: 'remove', label: 'Remove stock' },
];

const openingErrors = computed(() => Object.values(opening.errors).filter(Boolean));
const adjustmentErrors = computed(() => Object.values(adjustment.errors).filter(Boolean));
</script>

<template>
    <Head title="Adjust stock" />

    <div class="max-w-4xl space-y-6">
        <PageHeader
            title="Adjust stock"
            subtitle="Record initial opening balances or adjust stock quantities for count discrepancies, damage, or found items."
            back-href="/stock"
            back-label="Stock overview"
        />

        <FlashMessage />

        <!-- Opening stock -->
        <Card
            title="Opening stock"
            description="The starting quantity of a product in a warehouse. It can be entered only once for each product and warehouse. Later changes are adjustments."
            padding="md"
        >
            <ErrorAlert
                v-if="openingErrors.length > 0"
                :messages="openingErrors"
                class="mb-4"
            />

            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitOpening">
                <FormField
                    for-id="opening_product"
                    label="Product"
                    required
                    :error="opening.errors.product_id"
                >
                    <SelectInput
                        id="opening_product"
                        v-model="opening.product_id"
                        :options="productOptions"
                        placeholder="Choose a product"
                        :invalid="Boolean(opening.errors.product_id)"
                    />
                </FormField>

                <FormField
                    for-id="opening_warehouse"
                    label="Warehouse"
                    required
                    :error="opening.errors.warehouse_id"
                >
                    <SelectInput
                        id="opening_warehouse"
                        v-model="opening.warehouse_id"
                        :options="warehouseOptions"
                        placeholder="Choose a warehouse"
                        :invalid="Boolean(opening.errors.warehouse_id)"
                    />
                </FormField>

                <!-- inputmode="decimal" shows the number keyboard on phones. Use a dot for decimals: 2.5 -->
                <FormField
                    for-id="opening_quantity"
                    label="Quantity"
                    required
                    hint="Positive amount with up to 3 decimal places (e.g. 10.000)"
                    :error="opening.errors.quantity"
                >
                    <TextInput
                        id="opening_quantity"
                        v-model="opening.quantity"
                        type="text"
                        inputmode="decimal"
                        placeholder="e.g. 10.000"
                        :invalid="Boolean(opening.errors.quantity)"
                    />
                </FormField>

                <FormField
                    for-id="opening_note"
                    label="Note"
                    hint="Optional context or reference"
                    :error="opening.errors.note"
                >
                    <TextInput
                        id="opening_note"
                        v-model="opening.note"
                        type="text"
                        maxlength="1000"
                        placeholder="Optional note"
                        :invalid="Boolean(opening.errors.note)"
                    />
                </FormField>

                <div class="pt-2 sm:col-span-2">
                    <AppButton
                        type="submit"
                        :loading="opening.processing"
                        :disabled="opening.processing"
                    >
                        {{ opening.processing ? 'Saving…' : 'Record opening stock' }}
                    </AppButton>
                </div>
            </form>
        </Card>

        <!-- Manual adjustment -->
        <Card
            title="Adjustment"
            description="A correction after a count, damage or loss. A note is required, and stock can never go below zero."
            padding="md"
        >
            <ErrorAlert
                v-if="adjustmentErrors.length > 0"
                :messages="adjustmentErrors"
                class="mb-4"
            />

            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitAdjustment">
                <FormField
                    for-id="adjust_product"
                    label="Product"
                    required
                    :error="adjustment.errors.product_id"
                >
                    <SelectInput
                        id="adjust_product"
                        v-model="adjustment.product_id"
                        :options="productOptions"
                        placeholder="Choose a product"
                        :invalid="Boolean(adjustment.errors.product_id)"
                    />
                </FormField>

                <FormField
                    for-id="adjust_warehouse"
                    label="Warehouse"
                    required
                    :error="adjustment.errors.warehouse_id"
                >
                    <SelectInput
                        id="adjust_warehouse"
                        v-model="adjustment.warehouse_id"
                        :options="warehouseOptions"
                        placeholder="Choose a warehouse"
                        :invalid="Boolean(adjustment.errors.warehouse_id)"
                    />
                </FormField>

                <FormField
                    for-id="adjust_direction"
                    label="Change"
                    required
                    :error="adjustment.errors.direction"
                >
                    <SelectInput
                        id="adjust_direction"
                        v-model="adjustment.direction"
                        :options="directionOptions"
                        placeholder="Add or remove?"
                        :invalid="Boolean(adjustment.errors.direction)"
                    />
                </FormField>

                <!-- This is where "Insufficient stock: available 2.000, requested 2.500" appears (INV-07) -->
                <FormField
                    for-id="adjust_quantity"
                    label="Quantity"
                    required
                    hint="Positive amount with up to 3 decimal places"
                    :error="adjustment.errors.quantity"
                >
                    <TextInput
                        id="adjust_quantity"
                        v-model="adjustment.quantity"
                        type="text"
                        inputmode="decimal"
                        placeholder="e.g. 3.500"
                        :invalid="Boolean(adjustment.errors.quantity)"
                    />
                </FormField>

                <FormField
                    for-id="adjust_note"
                    label="Note"
                    required
                    hint="State the reason (e.g. count correction, damaged goods)"
                    :error="adjustment.errors.note"
                    class="sm:col-span-2"
                >
                    <TextInput
                        id="adjust_note"
                        v-model="adjustment.note"
                        type="text"
                        maxlength="1000"
                        placeholder="For example: damaged in storage"
                        :invalid="Boolean(adjustment.errors.note)"
                    />
                </FormField>

                <div class="pt-2 sm:col-span-2">
                    <AppButton
                        type="submit"
                        :loading="adjustment.processing"
                        :disabled="adjustment.processing"
                    >
                        {{ adjustment.processing ? 'Saving…' : 'Save adjustment' }}
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
