<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PagerLinks from '../../../Components/PagerLinks.vue';

const props = defineProps({
    product: Object,
    movements: Object, // each row has quantity (signed) and running_balance, calculated by MySQL
    filters: Object,
    warehouses: Array,
    types: Array,
});

const page = usePage();

// Validation messages for the filters (for example "to" earlier than "from").
const errors = computed(() => page.props.errors ?? {});

const warehouseId = ref(props.filters?.warehouse_id ?? '');
const type = ref(props.filters?.type ?? '');
const from = ref(props.filters?.from ?? '');
const to = ref(props.filters?.to ?? '');

function applyFilters() {
    router.get(
        `/stock/card/${props.product.id}`,
        {
            warehouse_id: warehouseId.value || undefined,
            type: type.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch([warehouseId, type, from, to], applyFilters);

// Friendly names for the stored movement types (INV-04).
const typeLabels = {
    opening: 'Opening stock',
    receipt: 'Goods receipt',
    sale: 'Sale',
    sale_reversal: 'Sale reversal',
    adjustment: 'Adjustment',
    transfer_in: 'Transfer in',
    transfer_out: 'Transfer out',
};

// The database stores UTC, but people read Addis Ababa time (SRS: UTC stored, Africa/Addis_Ababa shown).
function formatDate(value) {
    return new Date(value).toLocaleString('en-GB', {
        timeZone: 'Africa/Addis_Ababa',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

// Quantities are exact strings. A leading "-" means stock went out.
function isOut(movement) {
    return movement.quantity.startsWith('-');
}

function signed(movement) {
    return isOut(movement) ? movement.quantity : `+${movement.quantity}`;
}

// The document that caused the movement, if any. Links to those documents come with
// the procurement and sales screens.
function linkedDocument(movement) {
    if (movement.goods_receipt_item_id) return `Receipt line #${movement.goods_receipt_item_id}`;
    if (movement.sale_item_id) return `Sale line #${movement.sale_item_id}`;
    return '';
}
</script>

<template>
    <Head :title="`Stock card: ${product.name}`" />

    <div>
        <Link href="/stock" class="text-sm text-gray-600 underline">← Stock overview</Link>

        <h1 class="mt-2 text-2xl font-semibold">Stock card: {{ product.name }}</h1>
        <p class="text-sm text-gray-500">
            <span class="font-mono">{{ product.sku }}</span> · {{ product.category?.name }} · unit: {{ product.unit?.symbol }}
        </p>

        <!-- Filters. Labels are visible here because "From" and "To" need them. -->
        <div class="mt-4 grid gap-3 sm:grid-cols-4">
            <div>
                <label for="warehouse" class="block text-xs text-gray-500">Warehouse</label>
                <select id="warehouse" v-model="warehouseId" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All warehouses</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                </select>
            </div>
            <div>
                <label for="type" class="block text-xs text-gray-500">Type</label>
                <select id="type" v-model="type" class="h-11 w-full rounded border border-gray-300 px-3">
                    <option value="">All types</option>
                    <option v-for="option in types" :key="option" :value="option">{{ typeLabels[option] ?? option }}</option>
                </select>
            </div>
            <div>
                <label for="from" class="block text-xs text-gray-500">From</label>
                <input id="from" v-model="from" type="date" class="h-11 w-full rounded border border-gray-300 px-3" />
            </div>
            <div>
                <label for="to" class="block text-xs text-gray-500">To</label>
                <input id="to" v-model="to" type="date" class="h-11 w-full rounded border border-gray-300 px-3" />
            </div>
        </div>
        <p v-if="errors.to || errors.from || errors.type" class="mt-2 text-sm text-red-700" role="alert">
            {{ errors.to || errors.from || errors.type }}
        </p>

        <p class="mt-3 text-xs text-gray-500">
            The balance on each line is the real running balance, even when a filter hides other lines.
            With a warehouse chosen, it is that warehouse's balance.
        </p>

        <p v-if="movements.data.length === 0" class="mt-8 text-center text-gray-500">No movements found.</p>

        <!-- Table: from 640 px upward, newest first -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Date</th>
                    <th>Type</th>
                    <th>Warehouse</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Balance</th>
                    <th class="pl-4">By</th>
                    <th>Document</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="movement in movements.data" :key="movement.id" class="border-b align-top">
                    <td class="py-3">{{ formatDate(movement.created_at) }}</td>
                    <td>
                        {{ typeLabels[movement.movement_type] ?? movement.movement_type }}
                        <p v-if="movement.note" class="text-xs text-gray-500">{{ movement.note }}</p>
                    </td>
                    <td>{{ movement.warehouse?.name }}</td>
                    <td class="text-right font-medium" :class="isOut(movement) ? 'text-red-700' : 'text-green-700'">{{ signed(movement) }}</td>
                    <td class="text-right">{{ movement.running_balance }}</td>
                    <td class="pl-4">{{ movement.performed_by?.name }}</td>
                    <td>{{ linkedDocument(movement) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px -->
        <div v-if="movements.data.length" class="mt-4 space-y-3 sm:hidden">
            <div v-for="movement in movements.data" :key="movement.id" class="rounded border bg-white p-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ typeLabels[movement.movement_type] ?? movement.movement_type }}</p>
                        <p class="text-xs text-gray-500">{{ formatDate(movement.created_at) }}</p>
                    </div>
                    <p class="font-medium" :class="isOut(movement) ? 'text-red-700' : 'text-green-700'">{{ signed(movement) }}</p>
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ movement.warehouse?.name }} · balance {{ movement.running_balance }}</p>
                <p v-if="movement.note" class="text-sm text-gray-500">{{ movement.note }}</p>
                <p class="text-xs text-gray-500">
                    {{ movement.performed_by?.name }}<span v-if="linkedDocument(movement)"> · {{ linkedDocument(movement) }}</span>
                </p>
            </div>
        </div>

        <PagerLinks :meta="movements" />
    </div>
</template>