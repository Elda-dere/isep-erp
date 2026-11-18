<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import PaymentLink from './PaymentLink.vue';
import StatusBadge from './StatusBadge.vue';
import { formatDateTime, formatMoney } from '../format.js';

const props = defineProps({
    sale: Object,
    payments: Array,
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);

// The buttons are only a convenience: the server checks every permission again.
const canStart = computed(() => permissions.value.includes('payments.start') && props.sale.status === 'pending');
const canOpen = computed(() => permissions.value.includes('payments.view'));

// The server sends this message when it refuses (for example, "Chapa is not configured ...").
const error = computed(() => page.props.errors?.payment ?? null);

const form = useForm({});

function start() {
    form.post(`/sales/${props.sale.id}/payments`, { preserveScroll: true });
}

// ---- Live update ----
// The customer pays on THEIR phone, so nothing on this page changes by itself. While a payment is
// waiting, we ask the server for fresh data every 4 seconds. This only reads our own database (it does
// not call Chapa): the sale turns "Paid" as soon as the webhook, the customer's return page or the
// reconciliation job has settled the payment. It stops by itself when nothing is waiting.
const waiting = computed(
    () => props.sale.status === 'pending' && props.payments.some((payment) => payment.status === 'pending'),
);

let timer = null;

function poll() {
    // Do not ask while the tab is hidden: nobody is looking.
    if (document.visibilityState === 'visible') {
        router.reload({ only: ['sale', 'payments'], preserveScroll: true });
    }
}

function sync() {
    if (waiting.value && timer === null) {
        timer = setInterval(poll, 4000);
    } else if (!waiting.value && timer !== null) {
        clearInterval(timer);
        timer = null;
    }
}

onMounted(sync);
watch(waiting, sync);
onBeforeUnmount(() => {
    if (timer !== null) clearInterval(timer);
});
</script>

<template>
    <section v-if="canStart || payments.length" class="mt-8">
        <h2 class="text-lg font-semibold">Payment</h2>

        <p v-if="error" role="alert" class="mt-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ error }}</p>

        <button
            v-if="canStart"
            type="button"
            :disabled="form.processing"
            class="mt-2 flex min-h-11 items-center rounded bg-gray-900 px-4 text-white disabled:opacity-50"
            @click="start"
        >
            {{ form.processing ? 'Creating the link…' : 'Pay with Chapa' }}
        </button>

        <p v-if="waiting" role="status" class="mt-3 text-sm text-gray-600">
            Waiting for the customer to pay… this page updates by itself.
        </p>

        <ul class="mt-3 space-y-3">
            <li v-for="payment in payments" :key="payment.id" class="rounded border bg-white p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <StatusBadge :status="payment.status" />
                        <span class="text-sm">{{ formatMoney(payment.amount, payment.currency) }}</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ formatDateTime(payment.paid_at ?? payment.created_at) }}</span>
                </div>

                <PaymentLink v-if="payment.checkout_url" :url="payment.checkout_url" class="mt-3 block" />

                <Link v-if="canOpen" :href="`/payments/${payment.id}`" class="mt-2 flex min-h-11 items-center text-sm underline">
                    Payment details
                </Link>
            </li>
        </ul>
    </section>
</template>