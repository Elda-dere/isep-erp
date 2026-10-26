<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    warehouse: Object, // null when creating, the warehouse when editing
});

const isEdit = computed(() => props.warehouse !== null);

// useForm keeps the values, sends them, and remembers the server's validation errors.
const form = useForm({
    name: props.warehouse?.name ?? '',
    location: props.warehouse?.location ?? '',
});

// Edit sends PUT /warehouses/{id}; create sends POST /warehouses.
function submit() {
    if (isEdit.value) {
        form.put(`/warehouses/${props.warehouse.id}`);
    } else {
        form.post('/warehouses');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit warehouse' : 'New warehouse'" />

    <div class="max-w-xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit warehouse' : 'New warehouse' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" maxlength="120" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="location" class="block text-sm font-medium">Location (optional)</label>
                <input id="location" v-model="form.location" type="text" maxlength="255" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.location" class="mt-1 text-sm text-red-700">{{ form.errors.location }}</p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
                <Link href="/warehouses" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>