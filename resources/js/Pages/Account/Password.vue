<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Change password" />

    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-bold">Change password</h1>
        <p class="mt-1 text-gray-600">Use at least 10 characters.</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="current_password" class="block text-sm font-medium">Current password</label>
                <input
                    id="current_password"
                    v-model="form.current_password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3"
                />
                <p v-if="form.errors.current_password" class="mt-1 text-sm text-red-700">
                    {{ form.errors.current_password }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">New password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-700">
                    {{ form.errors.password }}
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Repeat new password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-11 w-full rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50"
            >
                Save new password
            </button>

            <p v-if="form.recentlySuccessful" role="status" class="text-sm text-green-800">
                Password updated.
            </p>
        </form>
    </div>
</template>