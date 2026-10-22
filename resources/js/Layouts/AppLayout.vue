<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { menu } from '../navigation.js';

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const permissions = computed(() => page.props.auth?.permissions ?? []);

// Only the entries this user is allowed to open.
const items = computed(() =>
    menu.filter((item) => !item.permission || permissions.value.includes(item.permission)),
);

const open = ref(false);

function isActive(href) {
    return page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <a
            href="#content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:rounded focus:bg-white focus:px-3 focus:py-2"
        >
            Skip to content
        </a>

        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 md:flex md:items-center md:justify-between md:gap-4">
                <div class="flex items-center justify-between py-2">
                    <Link href="/dashboard" class="text-lg font-bold">Integrated ERP</Link>

                    <button
                        v-if="user"
                        type="button"
                        class="flex min-h-11 min-w-11 items-center justify-center rounded border border-gray-300 md:hidden"
                        :aria-expanded="open"
                        aria-controls="main-menu"
                        @click="open = !open"
                    >
                        <span class="sr-only">Menu</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M3 5h14M3 10h14M3 15h14" />
                        </svg>
                    </button>
                </div>

                <div
                    v-if="user"
                    id="main-menu"
                    :class="[open ? 'block' : 'hidden', 'pb-2 md:flex md:flex-1 md:items-center md:justify-between md:pb-0']"
                >
                    <nav aria-label="Main" class="flex flex-col md:flex-row md:gap-1">
                        <Link
                            v-for="item in items"
                            :key="item.href"
                            :href="item.href"
                            class="flex min-h-11 items-center rounded px-3"
                            :class="isActive(item.href) ? 'bg-gray-900 text-white' : 'hover:bg-gray-100'"
                            :aria-current="isActive(item.href) ? 'page' : undefined"
                            @click="open = false"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>

                    <div class="mt-2 flex flex-col border-t border-gray-200 pt-2 md:mt-0 md:flex-row md:items-center md:gap-3 md:border-0 md:pt-0">
                        <span class="px-3 text-sm text-gray-600 md:px-0">{{ user.name }}</span>
                        <Link
                            href="/account/password"
                            class="flex min-h-11 items-center rounded px-3 hover:bg-gray-100"
                            @click="open = false"
                        >
                            Change password
                        </Link>
                        <button
                            type="button"
                            class="flex min-h-11 items-center rounded px-3 text-left hover:bg-gray-100"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main id="content" class="mx-auto max-w-6xl p-4">
            <slot />
        </main>
    </div>
</template>