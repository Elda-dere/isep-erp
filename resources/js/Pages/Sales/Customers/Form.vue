<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    customer: {
        type: Object, // null when creating, the customer when editing
        default: null,
    },
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

    <div class="max-w-2xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit customer' : 'New customer'"
            :subtitle="isEdit ? `Update contact and account details for ${customer.name}.` : 'Add a new customer to the commercial customer directory.'"
            back-href="/customers"
            back-label="Customers"
        />

        <Card padding="md">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField
                    for-id="name"
                    label="Customer name"
                    required
                    :error="form.errors.name"
                >
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        maxlength="180"
                        placeholder="e.g. Abebe Kebede"
                        autocomplete="off"
                        :disabled="isWalkIn || form.processing"
                        :autofocus="!isWalkIn"
                    />
                    <p
                        v-if="isWalkIn"
                        class="mt-1.5 text-xs text-ink-muted"
                    >
                        This is the default customer for anonymous sales, so its name cannot be changed.
                    </p>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        for-id="phone"
                        label="Phone"
                        hint="Optional (max 30 chars)"
                        :error="form.errors.phone"
                    >
                        <TextInput
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="30"
                            placeholder="e.g. 0911000111"
                            autocomplete="tel"
                            :disabled="form.processing"
                        />
                    </FormField>

                    <FormField
                        for-id="email"
                        label="Email"
                        hint="Optional (max 190 chars)"
                        :error="form.errors.email"
                    >
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            maxlength="190"
                            placeholder="e.g. customer@example.com"
                            autocomplete="email"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>

                <FormField
                    for-id="address"
                    label="Address"
                    hint="Optional billing address or delivery location"
                    :error="form.errors.address"
                >
                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="3"
                        placeholder="e.g. Bole Subcity, Kebele 03, Addis Ababa"
                        :disabled="form.processing"
                        class="block w-full rounded border border-border bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint shadow-2xs transition-colors focus:border-forest focus:outline-none focus:ring-1 focus:ring-forest disabled:bg-stone disabled:opacity-75"
                    />
                </FormField>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <AppButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create customer') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/customers"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
