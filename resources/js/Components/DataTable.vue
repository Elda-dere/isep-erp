<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    columns: {
        type: Array,
        required: true,
    },
    rowKey: {
        type: [String, Function],
        default: 'id',
    },
    breakpoint: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md'].includes(value),
    },
    surface: {
        type: Boolean,
        default: true,
    },
    mobileHref: {
        type: Function,
        default: null,
    },
    emptyTitle: {
        type: String,
        default: 'No records found.',
    },
    emptyDescription: {
        type: String,
        default: '',
    },
    emptyIcon: {
        type: String,
        default: '',
    },
});

const mobileHrefs = computed(() =>
    props.items.map((item) => (typeof props.mobileHref === 'function' ? props.mobileHref(item) || null : null)),
);

function resolveRowKey(item, index) {
    if (typeof props.rowKey === 'function') {
        return props.rowKey(item, index);
    }
    return item?.[props.rowKey] ?? index;
}

function headerAlignmentClass(column) {
    return column.align === 'right' ? 'text-right' : 'text-left';
}

function cellAlignmentClass(column) {
    return column.align === 'right' ? 'text-right tabular-nums' : 'text-left';
}
</script>

<template>
    <div v-if="items.length === 0">
        <slot name="empty">
            <EmptyState
                :title="emptyTitle"
                :description="emptyDescription"
                :icon="emptyIcon"
            />
        </slot>
    </div>

    <div v-else>
        <!-- Desktop table -->
        <div
            :class="[
                !$slots['mobile-card']
                    ? 'block'
                    : breakpoint === 'md'
                        ? 'hidden md:block'
                        : 'hidden sm:block',
                surface && 'rounded border border-border bg-surface shadow-xs',
            ]"
        >
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-border bg-stone/60 text-xs font-semibold text-ink-muted">
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                scope="col"
                                class="px-4 py-3"
                                :class="[headerAlignmentClass(column), column.headerClass]"
                            >
                                <span v-if="column.srOnly" class="sr-only">{{ column.label }}</span>
                                <template v-else>{{ column.label }}</template>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(item, index) in items"
                            :key="resolveRowKey(item, index)"
                            class="border-b border-border-subtle align-top transition-colors hover:bg-stone/40"
                            :class="{ 'last:border-b-0': !$slots.footer }"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-3 text-ink"
                                :class="[cellAlignmentClass(column), column.cellClass]"
                            >
                                <slot
                                    :name="`cell-${column.key}`"
                                    :item="item"
                                    :value="item?.[column.key]"
                                    :index="index"
                                >
                                    {{ item?.[column.key] }}
                                </slot>
                            </td>
                        </tr>
                    </tbody>

                    <tfoot v-if="$slots.footer" class="border-t border-border bg-stone/40 font-medium text-ink">
                        <slot name="footer" />
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Mobile card stack -->
        <div
            v-if="$slots['mobile-card']"
            class="space-y-3"
            :class="breakpoint === 'md' ? 'md:hidden' : 'sm:hidden'"
        >
            <template v-for="(item, index) in items" :key="resolveRowKey(item, index)">
                <Link
                    v-if="mobileHrefs[index]"
                    :href="mobileHrefs[index]"
                    class="block rounded border border-border bg-surface p-4 shadow-xs transition-colors hover:bg-stone/40"
                >
                    <slot name="mobile-card" :item="item" :index="index" />
                </Link>

                <div
                    v-else
                    class="rounded border border-border bg-surface p-4 shadow-xs"
                >
                    <slot name="mobile-card" :item="item" :index="index" />
                </div>
            </template>
        </div>
    </div>
</template>
