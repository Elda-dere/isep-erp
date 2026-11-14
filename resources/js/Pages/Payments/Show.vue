<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import FlashMessage from '../../Components/FlashMessage.vue';
import PaymentLink from '../../Components/PaymentLink.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { formatDateTime, formatMoney } from '../../format.js';

const props = defineProps({
    payment: Object,
    events: Array,
    flags: Array,
    canViewPayload: Boolean,
});

const page = usePage();

// The button is only a convenience: the server checks payments.verify again.
const canVerify = computed(() => page.props.auth?.permissions?.includes('payments.verify') && props.payment.status !== 'success');
const verifyError = computed(() => page.props.errors?.verify ?? null);

function verify() {
    router.post(`/payments/${props.payment.id}/verify`, {}, { preserveScroll: true });
}

// Plain words for the reasons PaymentService records when it flags a payment (PAY-10).
const reasonText = {
    amount: 'The amount Chapa reported is different from the amount of the sale.',
    currency: 'The currency Chapa reported is different from the sale.',
    mode: 'Test mode and live mode do not match.',
    tx_ref: 'The reference Chapa reported is not this payment\'s reference.',
    sale_not_pending: 'The money arrived after the sale was cancelled. A person must decide what to do.',
    sale_already_paid: 'The sale was already paid by another payment, so this money is extra.',
};
</script>

<template>
    <Head :title="`Payment ${payment.sale.sale_number}`" />

    <div>
        <Link href="/payments" class="text-sm text-gray-600 underline">← Payments</Link>

        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ formatMoney(payment.amount, payment.currency) }}</h1>
            <StatusBadge :status="payment.status" />
        </div>

        <FlashMessage class="mt-4" />
        <p v-if="verifyError" role="alert" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ verifyError }}</p>

        <div v-if="flags.length" class="mt-4 rounded border border-amber-300 bg-amber-50 p-3" role="alert">
            <p class="font-medium text-amber-900">This payment needs a person's attention</p>
            <div v-for="(flag, index) in flags" :key="index" class="mt-2 text-sm text-amber-900">
                <p v-for="reason in flag.reasons" :key="reason">{{ reasonText[reason] ?? reason }}</p>
                <p v-if="flag.received?.amount" class="text-xs">
                    Expected {{ flag.expected?.amount }} {{ flag.expected?.currency }}, Chapa reported {{ flag.received.amount }} {{ flag.received.currency }}.
                </p>
            </div>
        </div>

        <button
            v-if="canVerify"
            type="button"
            class="mt-4 flex min-h-11 items-center rounded border px-4"
            @click="verify"
        >
            Check with Chapa again
        </button>

        <PaymentLink v-if="payment.checkout_url" :url="payment.checkout_url" class="mt-4 block" />

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Sale</dt>
                <dd>
                    <Link :href="`/sales/${payment.sale.id}`" class="font-mono font-medium underline">{{ payment.sale.sale_number }}</Link>
                </dd>
                <dd class="text-gray-600">{{ payment.sale.customer }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Created</dt>
                <dd class="font-medium">{{ formatDateTime(payment.created_at) }}</dd>
                <dd class="text-gray-600">Paid: {{ payment.paid_at ? formatDateTime(payment.paid_at) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Reference</dt>
                <dd class="break-all font-mono text-xs">{{ payment.tx_ref }}</dd>
                <dd v-if="payment.provider_transaction_id" class="break-all font-mono text-xs text-gray-600">Chapa: {{ payment.provider_transaction_id }}</dd>
            </div>
        </dl>

        <h2 class="mt-8 text-lg font-semibold">History</h2>
        <p v-if="events.length === 0" class="mt-2 text-sm text-gray-500">Nothing has been received from Chapa yet.</p>

        <ol class="mt-2 space-y-3">
            <li v-for="event in events" :key="event.id" class="rounded border bg-white p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-mono text-sm font-medium">{{ event.event_type }}</p>
                    <p class="text-xs text-gray-500">received {{ formatDateTime(event.received_at) }}</p>
                </div>
                <p class="text-xs text-gray-500">{{ event.processed_at ? `processed ${formatDateTime(event.processed_at)}` : 'not processed yet' }}</p>
                <!-- Raw payloads hold customer details: the server only sends them with payments.view_payload. -->
                <pre v-if="canViewPayload && event.payload" class="mt-2 overflow-x-auto rounded bg-gray-50 p-2 text-xs">{{ JSON.stringify(event.payload, null, 2) }}</pre>
            </li>
        </ol>
        <p v-if="!canViewPayload && events.length" class="mt-2 text-xs text-gray-500">Raw Chapa messages are shown only to people with the payload permission.</p>
    </div>
</template>