<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    category: Object, // null when creating, the category when editing
});

const isEdit = computed(() => props.category !== null);

// useForm keeps the values, sends them, and remembers the server's validation errors.
const form = useForm({
    name: props.category?.name ?? '',
    description: props.category?.description ?? '',
});

// Edit sends PUT /categories/{id}; create sends POST /categories.
function submit() {
    if (isEdit.value) {
        form.put(`/categories/${props.category.id}`);
    } else {
        form.post('/categories');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit category' : 'New category'" />

    <div class="max-w-xl">
        <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit category' : 'New category' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" maxlength="100" class="mt-1 h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium">Description (optional)</label>
                <textarea id="description" v-model="form.description" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2"></textarea>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-700">{{ form.errors.description }}</p>
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex min-h-11 items-center rounded bg-gray-900 px-5 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </button>
                <Link href="/categories" class="flex min-h-11 items-center rounded border px-5">Cancel</Link>
            </div>
        </form>
    </div>
</template>