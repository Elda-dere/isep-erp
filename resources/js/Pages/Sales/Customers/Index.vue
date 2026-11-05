<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import FlashMessage from "../../../Components/FlashMessage.vue";
import PagerLinks from "../../../Components/PagerLinks.vue";

const props = defineProps({
    customers: Object, // a Laravel paginator: .data holds rows with sales_count and is_walk_in
    filters: Object,
});

const page = usePage();

// The server sends this message when a delete is refused (a customer with sales, or the Walk-in customer).
const deleteError = computed(() => page.props.errors?.delete ?? null);

// The search box starts with the value from the URL, so a reload keeps it.
const search = ref(props.filters?.search ?? "");

// Wait 300 ms after the last keystroke, then ask the server again.
// The searching itself happens in CustomerController::index (SQL), not here.
let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            "/customers",
            { search: search.value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});

function remove(customer) {
    // Destructive actions ask for confirmation (SRS section 6.1).
    if (
        !confirm(
            `Delete the customer "${customer.name}"? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(`/customers/${customer.id}`, { preserveScroll: true });
}

// Instead of a Delete button, say why the customer cannot be deleted. The server checks this again.
function lockReason(customer) {
    if (customer.is_walk_in) return "Default customer";
    if (customer.sales_count > 0) return "Has sales";
    return null;
}
</script>

<template>
    <Head title="Customers" />

    <div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Customers</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ customers.total }} customer(s)
                </p>
            </div>
            <!-- No permission check on the buttons: this whole screen needs customers.manage already. -->
            <Link
                href="/customers/create"
                class="flex min-h-11 items-center rounded bg-gray-900 px-4 text-white"
            >
                New customer
            </Link>
        </div>

        <FlashMessage class="mt-4" />
        <p
            v-if="deleteError"
            role="alert"
            class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
        >
            {{ deleteError }}
        </p>

        <div class="mt-4 max-w-md">
            <label for="search" class="sr-only"
                >Search name, phone or email</label
            >
            <input
                id="search"
                v-model="search"
                type="search"
                placeholder="Search name, phone or email"
                class="h-11 w-full rounded border border-gray-300 px-3"
            />
        </div>

        <p
            v-if="customers.data.length === 0"
            class="mt-8 text-center text-gray-500"
        >
            No customers found.
        </p>

        <!-- Table: from the "sm" breakpoint (640 px) upward -->
        <table v-else class="mt-4 hidden w-full text-left text-sm sm:table">
            <thead class="border-b text-gray-500">
                <tr>
                    <th class="py-2">Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th class="text-right">Sales</th>
                    <th><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="customer in customers.data"
                    :key="customer.id"
                    class="border-b align-top"
                >
                    <td class="py-3">
                        <p class="font-medium">{{ customer.name }}</p>
                        <p
                            v-if="customer.address"
                            class="text-xs text-gray-500"
                        >
                            {{ customer.address }}
                        </p>
                    </td>
                    <td>{{ customer.phone }}</td>
                    <td>{{ customer.email }}</td>
                    <td class="text-right">{{ customer.sales_count }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <Link
                                :href="`/customers/${customer.id}/edit`"
                                :aria-label="`Edit ${customer.name}`"
                                class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                            >
                                Edit
                            </Link>
                            <button
                                v-if="!lockReason(customer)"
                                type="button"
                                :aria-label="`Delete ${customer.name}`"
                                class="flex min-h-11 items-center rounded px-3 text-red-700 hover:bg-red-50"
                                @click="remove(customer)"
                            >
                                Delete
                            </button>
                            <span
                                v-else
                                class="flex min-h-11 items-center px-2 text-gray-400"
                                >{{ lockReason(customer) }}</span
                            >
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Cards: below 640 px (phones). Same data, stacked. -->
        <div v-if="customers.data.length" class="mt-4 space-y-3 sm:hidden">
            <div
                v-for="customer in customers.data"
                :key="customer.id"
                class="rounded border bg-white p-3"
            >
                <p class="font-medium">{{ customer.name }}</p>
                <p v-if="customer.phone" class="text-sm text-gray-600">
                    {{ customer.phone }}
                </p>
                <p v-if="customer.email" class="text-sm text-gray-600">
                    {{ customer.email }}
                </p>
                <p v-if="customer.address" class="text-sm text-gray-500">
                    {{ customer.address }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ customer.sales_count }} sale(s)
                </p>
                <div class="mt-2 flex gap-2">
                    <Link
                        :href="`/customers/${customer.id}/edit`"
                        :aria-label="`Edit ${customer.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="!lockReason(customer)"
                        type="button"
                        :aria-label="`Delete ${customer.name}`"
                        class="flex min-h-11 flex-1 items-center justify-center rounded border border-red-300 text-red-700"
                        @click="remove(customer)"
                    >
                        Delete
                    </button>
                    <span
                        v-else
                        class="flex min-h-11 flex-1 items-center justify-center text-sm text-gray-400"
                        >{{ lockReason(customer) }}</span
                    >
                </div>
            </div>
        </div>

        <PagerLinks :meta="customers" />
    </div>
</template>
