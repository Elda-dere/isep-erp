<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    supplier: Object, // null when creating, the supplier when editing
});

const isEdit = computed(() => props.supplier !== null);

// useForm keeps the values, sends them, and remembers the server's validation errors.
const form = useForm({
    name: props.supplier?.name ?? '',
    phone: props.supplier?.phone ?? '',
    email: props.supplier?.email ?? '',
    address: props.supplier?.address ?? '',
});

// Edit sends PUT /suppliers/{id}; create sends POST /suppliers.
function submit() {
    if (isEdit.value) {
        form.put(`/suppliers/${props.supplier.id}`);
    } else {
        form.post('/suppliers');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit supplier' : 'New supplier'" />

    <div class="max-w-xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit supplier' : 'New supplier' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" maxlength="180" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
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
                <Link href="/suppliers" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>