<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    warehouse: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => props.warehouse !== null);

const form = useForm({
    name: props.warehouse?.name ?? '',
    location: props.warehouse?.location ?? '',
});

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

    <div class="max-w-2xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit warehouse' : 'New warehouse'"
            :subtitle="isEdit ? 'Update warehouse details and location.' : 'Add a new warehouse location for tracking inventory.'"
            back-href="/warehouses"
            back-label="Warehouses"
        />

        <Card padding="md">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField
                    for-id="warehouse_name"
                    label="Warehouse name"
                    required
                    :error="form.errors.name"
                >
                    <TextInput
                        id="warehouse_name"
                        v-model="form.name"
                        type="text"
                        maxlength="120"
                        placeholder="e.g. Central Distribution Hub"
                        autocomplete="off"
                        :disabled="form.processing"
                        autofocus
                    />
                </FormField>

                <FormField
                    for-id="warehouse_location"
                    label="Location"
                    hint="Physical address, city, or site designation (optional)"
                    :error="form.errors.location"
                >
                    <TextInput
                        id="warehouse_location"
                        v-model="form.location"
                        type="text"
                        maxlength="255"
                        placeholder="e.g. Bole Sub-city, Addis Ababa"
                        autocomplete="off"
                        :disabled="form.processing"
                    />
                </FormField>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <AppButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create warehouse') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/warehouses"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
