<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    supplier: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => props.supplier !== null);

const form = useForm({
    name: props.supplier?.name ?? '',
    phone: props.supplier?.phone ?? '',
    email: props.supplier?.email ?? '',
    address: props.supplier?.address ?? '',
});

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

    <div class="max-w-2xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit supplier' : 'New supplier'"
            :subtitle="isEdit ? `Update contact and procurement details for ${supplier.name}.` : 'Add a new supplier to the procurement vendor directory.'"
            back-href="/suppliers"
            back-label="Suppliers"
        />

        <Card padding="md">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField
                    for-id="name"
                    label="Supplier name"
                    required
                    :error="form.errors.name"
                >
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        maxlength="180"
                        placeholder="e.g. Acme Trading PLC"
                        autocomplete="off"
                        :disabled="form.processing"
                        autofocus
                    />
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
                            placeholder="e.g. +251 91 100 0000"
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
                            placeholder="e.g. sales@acmetrading.com"
                            autocomplete="email"
                            :disabled="form.processing"
                        />
                    </FormField>
                </div>

                <FormField
                    for-id="address"
                    label="Address"
                    hint="Optional delivery address, warehouse location, or office notes"
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
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create supplier') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/suppliers"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
