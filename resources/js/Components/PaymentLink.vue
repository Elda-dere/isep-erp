<script setup>
import { ref } from 'vue';
import QrCode from './QrCode.vue';

defineProps({
    url: String,
});

const copied = ref(false);

// PAY-04: a copy button. Copying needs HTTPS (or localhost), so there is a fallback.
async function copy(url) {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        window.prompt('Copy this link:', url);
    }
}
</script>

<template>
    <div class="rounded border bg-white p-3">
        <p class="text-sm text-gray-600">Give the customer this link, or let them scan the code with their phone.</p>
        <a :href="url" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all text-sm underline">{{ url }}</a>
        <button type="button" class="mt-2 flex min-h-11 items-center rounded border px-4" @click="copy(url)">
            {{ copied ? 'Copied' : 'Copy link' }}
        </button>
        <QrCode :value="url" class="mt-3" />
    </div>
</template>