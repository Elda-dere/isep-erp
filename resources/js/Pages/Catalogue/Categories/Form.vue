<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import Card from '../../../Components/Card.vue';
import FormField from '../../../Components/FormField.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    category: {
        type: Object,
        default: null,
    },
});

const isEdit = computed(() => props.category !== null);

const form = useForm({
    name: props.category?.name ?? '',
    description: props.category?.description ?? '',
});

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

    <div class="max-w-2xl space-y-6">
        <PageHeader
            :title="isEdit ? 'Edit category' : 'New category'"
            :subtitle="isEdit ? 'Update category name and description.' : 'Create a new category to organize catalogue products.'"
            back-href="/categories"
            back-label="Categories"
        />

        <Card padding="md">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField
                    for-id="category_name"
                    label="Category name"
                    required
                    :error="form.errors.name"
                >
                    <TextInput
                        id="category_name"
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        placeholder="e.g. Beverages"
                        autocomplete="off"
                        :disabled="form.processing"
                        autofocus
                    />
                </FormField>

                <FormField
                    for-id="category_description"
                    label="Description"
                    hint="Optional summary or notes about products in this category"
                    :error="form.errors.description"
                >
                    <textarea
                        id="category_description"
                        v-model="form.description"
                        rows="3"
                        maxlength="255"
                        placeholder="e.g. Bottled water, juices, and soft drinks"
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
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create category') }}
                    </AppButton>

                    <AppButton
                        variant="secondary"
                        href="/categories"
                        :disabled="form.processing"
                    >
                        Cancel
                    </AppButton>
                </div>
            </form>
        </Card>
    </div>
</template>
