<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    payment: Object, // only: sale_number, amount, currency, status
});

const pending = computed(() => props.payment.status === 'pending');

// While the payment is pending, ask the server again every 5 seconds (it checks with Chapa), for up to 2 minutes.
const asked = ref(0);
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        if (!pending.value || asked.value >= 24) {
            clearInterval(timer);
            return;
        }

        asked.value++;
        router.reload({ only: ['payment'] });
    }, 5000);
});

onBeforeUnmount(() => clearInterval(timer));

const message = computed(() => {
    switch (props.payment.status) {
        case 'success':
            return 'Payment received. Thank you!';
        case 'failed':
        case 'cancelled':
        case 'expired':
            return 'The payment was not completed. Please ask the shop for a new payment link.';
        default:
            return asked.value >= 24
                ? 'Still processing. You can close this page: the shop will see your payment as soon as it is confirmed.'
                : 'Waiting for your payment to be confirmed…';
    }
});
</script>

<template>
    <Head title="Payment" />

    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center p-6">
        <h1 class="text-2xl font-bold">Payment</h1>

        <dl class="mt-6 space-y-2 rounded border bg-white p-4">
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Sale</dt>
                <dd class="font-mono">{{ payment.sale_number }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">Amount</dt>
                <dd class="font-medium">{{ payment.currency }} {{ payment.amount }}</dd>
            </div>
        </dl>

        <p role="status" class="mt-6 text-lg">{{ message }}</p>
    </main>
</template>