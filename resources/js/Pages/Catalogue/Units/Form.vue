<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    unit: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => props.unit !== null);

const form = useForm({
    name: props.unit?.name ?? '',
    symbol: props.unit?.symbol ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(`/units/${props.unit.id}`);
    } else {
        form.post('/units');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit unit' : 'New unit'" />

    <div class="max-w-2xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit unit' : 'New unit'"
            :subtitle="isEdit ? 'Update unit name and symbol.' : 'Add a new unit of measure for tracking item quantities.'"
            back-href="/units"
            back-label="Units"
        />

        <Card padding="md">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField
                    for-id="unit_name"
                    label="Unit name"
                    required
                    :error="form.errors.name"
                >
                    <TextInput
                        id="unit_name"
                        v-model="form.name"
                        type="text"
                        maxlength="50"
                        placeholder="e.g. Kilogram"
                        autocomplete="off"
                        :disabled="form.processing"
                        autofocus
                    />
                </FormField>

                <FormField
                    for-id="unit_symbol"
                    label="Symbol"
                    required
                    hint="Short abbreviation used on stock cards, inventory reports, and receipts"
                    :error="form.errors.symbol"
                >
                    <TextInput
                        id="unit_symbol"
                        v-model="form.symbol"
                        type="text"
                        maxlength="20"
                        placeholder="e.g. kg"
                        autocomplete="off"
                        :mono="true"
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
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create unit') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/units"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
