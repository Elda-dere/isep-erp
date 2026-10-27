<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    unit: Object, // null when creating, the unit when editing
});

const isEdit = computed(() => props.unit !== null);

const form = useForm({
    name: props.unit?.name ?? '',
    symbol: props.unit?.symbol ?? '',
});

// Edit sends PUT /units/{id}; create sends POST /units.
function submit() {
    if (isEdit.value) {
        form.put(`/units/${props.unit.id}`);
    } else {
        form.post('/units');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit unit' : 'New unit'" />

    <div class="max-w-xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit unit' : 'New unit' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" maxlength="50" placeholder="For example: Kilogram" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="symbol" class="block text-sm font-medium">Symbol</label>
                <input id="symbol" v-model="form.symbol" type="text" maxlength="20" placeholder="For example: kg" class="mt-1 h-11 w-full rounded border border-gray-300 px-3 font-mono" />
                <p v-if="form.errors.symbol" class="mt-1 text-sm text-red-700">{{ form.errors.symbol }}</p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
                <Link href="/units" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>
