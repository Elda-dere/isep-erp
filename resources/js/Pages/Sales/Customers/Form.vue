<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    customer: Object, // null when creating, the customer when editing
});

const isEdit = computed(() => props.customer !== null);
const isWalkIn = computed(() => props.customer?.is_walk_in === true);

// useForm keeps the values, sends them, and remembers the server's validation errors.
const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
});

// Edit sends PUT /customers/{id}; create sends POST /customers.
function submit() {
    if (isEdit.value) {
        form.put(`/customers/${props.customer.id}`);
    } else {
        form.post('/customers');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit customer' : 'New customer'" />

    <div class="max-w-xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit customer' : 'New customer' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    maxlength="180"
                    :disabled="isWalkIn"
                    class="mt-1 h-11 w-full rounded border border-gray-300 px-3 disabled:bg-gray-100"
                />
                <p v-if="isWalkIn" class="mt-1 text-xs text-gray-500">
                    This is the default customer for anonymous sales, so its name cannot be changed.
                </p>
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700" role="alert">{{ form.errors.name }}</p>
            </div>

            <!-- type="tel" shows the telephone keyboard on phones (NFR-MOB-02) -->
            <div>
                <label for="phone" class="block text-sm font-medium">Phone (optional)</label>
                <input id="phone" v-model="form.phone" type="tel" maxlength="30" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-700">{{ form.errors.phone }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Email (optional)</label>
                <input id="email" v-model="form.email" type="email" maxlength="190" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-700">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="address" class="block text-sm font-medium">Address (optional)</label>
                <textarea id="address" v-model="form.address" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></textarea>
                <p v-if="form.errors.address" class="mt-1 text-sm text-red-700">{{ form.errors.address }}</p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
                <Link href="/customers" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>