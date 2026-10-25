<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    roles: Array,
});

const editing = computed(() => props.user !== null && props.user !== undefined);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user?.role_ids ?? [],
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    if (editing.value) {
        form
            .transform((data) => ({ name: data.name, email: data.email, roles: data.roles }))
            .put(`/users/${props.user.id}`);
    } else {
        form.post('/users');
    }
}

function resetPassword() {
    passwordForm.put(`/users/${props.user.id}/password`, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="editing ? 'Edit user' : 'New user'" />

    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-bold">{{ editing ? 'Edit user' : 'New user' }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" v-model="form.name" type="text" required class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" v-model="form.email" type="email" required autocomplete="off" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-700">{{ form.errors.email }}</p>
            </div>

            <template v-if="!editing">
                <div>
                    <label for="password" class="block text-sm font-medium">Password (at least 10 characters)</label>
                    <input id="password" v-model="form.password" type="password" required autocomplete="new-password" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-700">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">Repeat password</label>
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                </div>
            </template>

            <fieldset>
                <legend class="text-sm font-medium">Roles</legend>
                <div v-for="role in roles" :key="role.id">
                    <label class="flex min-h-11 items-center gap-2">
                        <input v-model="form.roles" type="checkbox" :value="role.id" class="h-5 w-5" />
                        {{ role.name }}
                    </label>
                </div>
                <p v-if="form.errors.roles" class="mt-1 text-sm text-red-700">{{ form.errors.roles }}</p>
            </fieldset>

            <div class="flex gap-2">
                <button type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editing ? 'Save changes' : 'Create user' }}
                </button>
                <Link href="/users" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">Cancel</Link>
            </div>
        </form>

        <form v-if="editing" class="mt-10 space-y-4 border-t border-gray-200 pt-6" @submit.prevent="resetPassword">
            <h2 class="text-xl font-bold">Set a new password</h2>
            <p class="text-sm text-gray-600">Use at least 10 characters. Tell the person through a safe channel.</p>

            <div>
                <label for="new_password" class="block text-sm font-medium">New password</label>
                <input id="new_password" v-model="passwordForm.password" type="password" required autocomplete="new-password" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
                <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-700">{{ passwordForm.errors.password }}</p>
            </div>

            <div>
                <label for="new_password_confirmation" class="block text-sm font-medium">Repeat new password</label>
                <input id="new_password_confirmation" v-model="passwordForm.password_confirmation" type="password" required autocomplete="new-password" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3" />
            </div>

            <button type="submit" :disabled="passwordForm.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                Save new password
            </button>

            <p v-if="passwordForm.recentlySuccessful" role="status" class="text-sm text-green-800">Password updated.</p>
        </form>
    </div>
</template>