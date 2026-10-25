<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    role: Object,
    groups: Array,
});

const editing = computed(() => props.role !== null && props.role !== undefined);
const locked = computed(() => props.role?.locked === true);

const form = useForm({
    name: props.role?.name ?? '',
    permissions: props.role?.permission_ids ?? [],
});

function submit() {
    if (locked.value) {
        return;
    }

    if (editing.value) {
        form.put(`/roles/${props.role.id}`);
    } else {
        form.post('/roles');
    }
}

function label(name) {
    return name.replaceAll('_', ' ');
}
</script>

<template>
    <Head :title="editing ? 'Edit role' : 'New role'" />

    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold">{{ editing ? 'Edit role' : 'New role' }}</h1>

        <p v-if="locked" role="status" class="mt-3 rounded bg-gray-100 p-3">
            The Administrator role is locked. It always has every permission and cannot be renamed.
        </p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <div>
                <label for="name" class="block text-sm font-medium">Role name</label>
                <input id="name" v-model="form.name" type="text" required :disabled="locked" class="mt-1 block min-h-11 w-full rounded border border-gray-300 px-3 disabled:bg-gray-100" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-700">{{ form.errors.name }}</p>
            </div>

            <fieldset v-for="group in groups" :key="group.group" class="rounded border border-gray-200 bg-white p-3">
                <legend class="px-1 text-sm font-semibold capitalize">{{ label(group.group) }}</legend>
                <div v-for="permission in group.permissions" :key="permission.id">
                    <label class="flex min-h-11 items-center gap-2">
                        <input v-model="form.permissions" type="checkbox" :value="permission.id" :disabled="locked" class="h-5 w-5" />
                        {{ permission.name }}
                    </label>
                </div>
            </fieldset>

            <p v-if="form.errors['permissions.0']" class="text-sm text-red-700">One of the chosen permissions is not valid.</p>

            <div class="flex gap-2">
                <button v-if="!locked" type="submit" :disabled="form.processing" class="min-h-11 rounded bg-gray-900 px-4 font-medium text-white disabled:opacity-50">
                    {{ editing ? 'Save changes' : 'Create role' }}
                </button>
                <Link href="/roles" class="flex min-h-11 items-center rounded border border-gray-300 bg-white px-4">
                    {{ locked ? 'Back' : 'Cancel' }}
                </Link>
            </div>
        </form>
    </div>
</template>