<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center p-6">
        <h1 class="text-2xl font-bold">Integrated ERP</h1>
        <p class="mt-1 text-gray-600">Sign in to continue</p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-700">
                    {{ form.errors.email }}
                </p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-700">
                    {{ form.errors.password }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-11 w-full rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50"
            >
                Sign in
            </button>
        </form>
    </main>
</template>