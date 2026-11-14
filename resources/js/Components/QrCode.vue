<script setup>
import { ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    value: String,
    size: { type: Number, default: 200 },
});

// The code is drawn in the browser: the link never leaves this page.
const image = ref(null);

watch(
    () => props.value,
    async (value) => {
        try {
            image.value = await QRCode.toDataURL(value, { width: props.size, margin: 1 });
        } catch {
            image.value = null;
        }
    },
    { immediate: true },
);
</script>

<template>
    <img v-if="image" :src="image" :width="size" :height="size" alt="QR code of the payment link" />
</template>