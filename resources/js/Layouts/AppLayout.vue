<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { canAccess, navigationGroups } from '../navigation.js';
import NavIcon from '../Components/NavIcon.vue';

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
const permissions = computed(() => page.props.auth?.permissions ?? []);

// Sidebar collapsed state on desktop (persisted in localStorage)
const isCollapsed = ref(false);

// Mobile slide-out drawer state
const mobileOpen = ref(false);

// User profile dropdown menu state
const userMenuOpen = ref(false);

// Template refs for drawer focus restoration
const mobileTriggerRef = ref(null);
const drawerCloseRef = ref(null);

// Filtered groups: only show sections and items the user has permission to see
const visibleGroups = computed(() => {
    return navigationGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => canAccess(item, permissions.value)),
        }))
        .filter((group) => group.items.length > 0);
});

const currentPath = computed(() => {
    const raw = page.url || '/';
    return raw.split('?')[0].split('#')[0];
});

const allHrefs = navigationGroups.flatMap((group) => group.items.map((item) => item.href));

// Accurate active route detection without duplicate sibling highlighting
function isActive(href) {
    const path = currentPath.value;

    if (path === href) {
        return true;
    }

    if (href === '/dashboard') {
        return path === '/' || path.startsWith('/dashboard/');
    }

    // Overview-only routes whose sub-routes live on separate screens
    if (href === '/stock') {
        return false;
    }

    // Goods receiving from a PO belongs under Goods receipts rather than Purchase orders
    if (href === '/purchase-orders' && /^\/purchase-orders\/[^/]+\/receive$/.test(path)) {
        return false;
    }
    if (href === '/goods-receipts' && /^\/purchase-orders\/[^/]+\/receive$/.test(path)) {
        return true;
    }

    // Avoid highlighting parent items when a more specific sibling item matches (e.g. /sales vs /sales/create)
    const hasMoreSpecificSibling = allHrefs.some(
        (other) => other !== href && other.startsWith(href + '/') && (path === other || path.startsWith(other + '/')),
    );
    if (hasMoreSpecificSibling) {
        return false;
    }

    return path.startsWith(href + '/');
}

const subRouteContexts = [
    { test: (p) => /^\/purchase-orders\/[^/]+\/receive$/.test(p), group: 'Procurement', item: 'Receive Goods' },
    { test: (p) => p.startsWith('/sales/'), group: 'Commercial & Sales', item: 'Sale Detail' },
    { test: (p) => p.startsWith('/customers/'), group: 'Commercial & Sales', item: 'Customers' },
    { test: (p) => p.startsWith('/stock/card/'), group: 'Catalogue & Stock', item: 'Stock Card' },
    { test: (p) => p.startsWith('/products/'), group: 'Catalogue & Stock', item: 'Products' },
    { test: (p) => p.startsWith('/categories/'), group: 'Catalogue & Stock', item: 'Categories' },
    { test: (p) => p.startsWith('/units/'), group: 'Catalogue & Stock', item: 'Units' },
    { test: (p) => p.startsWith('/warehouses/'), group: 'Catalogue & Stock', item: 'Warehouses' },
    { test: (p) => p.startsWith('/purchase-orders/'), group: 'Procurement', item: 'Purchase Order' },
    { test: (p) => p.startsWith('/goods-receipts/'), group: 'Procurement', item: 'Goods Receipt' },
    { test: (p) => p.startsWith('/suppliers/'), group: 'Procurement', item: 'Suppliers' },
    { test: (p) => p.startsWith('/employees/'), group: 'Workforce & Attendance', item: 'Employees' },
    { test: (p) => p.startsWith('/departments/'), group: 'Workforce & Attendance', item: 'Departments' },
    { test: (p) => p.startsWith('/positions/'), group: 'Workforce & Attendance', item: 'Positions' },
    { test: (p) => p.startsWith('/attendance/'), group: 'Workforce & Attendance', item: 'Attendance' },
    { test: (p) => p.startsWith('/users/'), group: 'System & Security', item: 'Users' },
    { test: (p) => p.startsWith('/roles/'), group: 'System & Security', item: 'Roles' },
    { test: (p) => p.startsWith('/audit-logs/'), group: 'System & Security', item: 'Audit log' },
    { test: (p) => p.startsWith('/networks/'), group: 'System & Security', item: 'Networks' },
    { test: (p) => p.startsWith('/account/password'), group: 'Account', item: 'Change Password' },
];

// Current active item for breadcrumb/title bar
const currentContext = computed(() => {
    const path = currentPath.value;

    for (const group of navigationGroups) {
        for (const item of group.items) {
            if (path === item.href || (item.href === '/dashboard' && path === '/')) {
                return { group: group.title, item: item.label };
            }
        }
    }

    const fallback = subRouteContexts.find((entry) => entry.test(path));
    if (fallback) {
        return { group: fallback.group, item: fallback.item };
    }

    for (const group of navigationGroups) {
        for (const item of group.items) {
            if (isActive(item.href)) {
                return { group: group.title, item: item.label };
            }
        }
    }

    return { group: 'ERP', item: 'Overview' };
});

function toggleCollapse() {
    isCollapsed.value = !isCollapsed.value;
    try {
        localStorage.setItem('isep_sidebar_collapsed', String(isCollapsed.value));
    } catch {
        // LocalStorage may be unavailable in private mode
    }
}

function closeMobile() {
    mobileOpen.value = false;
}

function dismissMobile() {
    mobileOpen.value = false;
    nextTick(() => {
        mobileTriggerRef.value?.focus();
    });
}

function logout() {
    userMenuOpen.value = false;
    mobileOpen.value = false;
    router.post('/logout');
}

// Handle Esc key to close popups and mobile menu
function handleKeydown(event) {
    if (event.key !== 'Escape') {
        return;
    }

    userMenuOpen.value = false;

    if (mobileOpen.value) {
        dismissMobile();
    }
}

// Handle click outside user dropdown
function handleClickOutside(event) {
    const target = event.target;
    if (userMenuOpen.value && target instanceof Element && !target.closest('#user-menu-container')) {
        userMenuOpen.value = false;
    }
}

watch(mobileOpen, (open) => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = open ? 'hidden' : '';
    }
    if (open) {
        nextTick(() => {
            drawerCloseRef.value?.focus();
        });
    }
});

let removeNavigateListener = null;
let desktopMediaQuery = null;

function handleDesktopBreakpoint(event) {
    if (event.matches) {
        mobileOpen.value = false;
    }
}

onMounted(() => {
    try {
        isCollapsed.value = localStorage.getItem('isep_sidebar_collapsed') === 'true';
    } catch {
        isCollapsed.value = false;
    }

    removeNavigateListener = router.on('navigate', () => {
        mobileOpen.value = false;
        userMenuOpen.value = false;
    });

    if (typeof window.matchMedia === 'function') {
        desktopMediaQuery = window.matchMedia('(min-width: 1024px)');
        if (desktopMediaQuery.matches) {
            mobileOpen.value = false;
        }
        if (typeof desktopMediaQuery.addEventListener === 'function') {
            desktopMediaQuery.addEventListener('change', handleDesktopBreakpoint);
        } else if (typeof desktopMediaQuery.addListener === 'function') {
            desktopMediaQuery.addListener(handleDesktopBreakpoint);
        }
    }

    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    if (typeof removeNavigateListener === 'function') {
        removeNavigateListener();
    }
    if (desktopMediaQuery) {
        if (typeof desktopMediaQuery.removeEventListener === 'function') {
            desktopMediaQuery.removeEventListener('change', handleDesktopBreakpoint);
        } else if (typeof desktopMediaQuery.removeListener === 'function') {
            desktopMediaQuery.removeListener(handleDesktopBreakpoint);
        }
    }
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="min-h-screen bg-canvas text-ink flex flex-col antialiased">
        <!-- Skip link for WCAG keyboard accessibility -->
        <a
            href="#content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded focus:bg-forest focus:px-4 focus:py-2 focus:text-white focus:shadow-md"
        >
            Skip to main content
        </a>

        <!-- Desktop Shell Wrapper -->
        <div class="flex-1 flex min-h-screen">
            <!-- ========================================================= -->
            <!-- DESKTOP SIDEBAR -->
            <!-- ========================================================= -->
            <aside
                v-if="user"
                class="hidden lg:flex flex-col shrink-0 bg-surface border-r border-border transition-all duration-200 select-none z-20"
                :class="isCollapsed ? 'w-18' : 'w-64'"
                aria-label="Sidebar Navigation"
            >
                <!-- Brand Header -->
                <div
                    class="h-16 flex items-center border-b border-border"
                    :class="isCollapsed ? 'justify-center px-2' : 'justify-between px-3 gap-2'"
                >
                    <Link
                        v-if="!isCollapsed"
                        href="/dashboard"
                        class="flex items-center gap-3 overflow-hidden rounded min-h-11 px-1"
                    >
                        <!-- Monogram Badge -->
                        <div class="h-9 w-9 rounded bg-forest text-white flex items-center justify-center font-semibold tracking-tight text-sm shrink-0 shadow-xs">
                            IE
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-semibold tracking-tight leading-tight text-ink truncate">
                                Integrated ERP
                            </span>
                            <span class="text-[11px] text-ink-muted leading-tight truncate">
                                Enterprise Edition
                            </span>
                        </div>
                    </Link>

                    <!-- Collapse / Expand Toggle Button -->
                    <button
                        type="button"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded text-ink-muted hover:text-ink hover:bg-stone transition-colors"
                        :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                        @click="toggleCollapse"
                    >
                        <svg
                            class="h-4 w-4 transition-transform duration-200"
                            :class="isCollapsed ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.75"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M12 15l-5-5 5-5" />
                        </svg>
                    </button>
                </div>

                <!-- Scrollable Navigation Groups -->
                <nav
                    class="flex-1 overflow-y-auto px-2 py-4 space-y-6"
                    aria-label="Application Sections"
                >
                    <div
                        v-for="(group, gIdx) in visibleGroups"
                        :key="group.title"
                        class="space-y-1"
                    >
                        <!-- Group Header (Hidden when collapsed) -->
                        <div
                            v-if="!isCollapsed"
                            class="px-2.5 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-ink-muted/80"
                        >
                            {{ group.title }}
                        </div>
                        <div v-else-if="gIdx > 0" class="my-2 border-t border-border-subtle mx-2" />

                        <!-- Nav Items -->
                        <ul class="space-y-0.5">
                            <li v-for="item in group.items" :key="item.href">
                                <Link
                                    :href="item.href"
                                    class="group relative flex items-center rounded text-sm transition-colors min-h-11"
                                    :class="[
                                        isActive(item.href)
                                            ? 'bg-soft-green text-forest font-semibold shadow-xs'
                                            : 'text-ink-muted hover:text-ink hover:bg-stone',
                                        isCollapsed ? 'justify-center px-0 w-full' : 'px-2.5 gap-3'
                                    ]"
                                    :title="isCollapsed ? item.label : undefined"
                                    :aria-current="isActive(item.href) ? 'page' : undefined"
                                >
                                    <!-- Active Left Indicator Bar -->
                                    <span
                                        v-if="isActive(item.href)"
                                        class="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r bg-forest"
                                        aria-hidden="true"
                                    />

                                    <!-- Icon -->
                                    <NavIcon
                                        :name="item.icon"
                                        :class-name="[
                                            'transition-colors',
                                            isActive(item.href) ? 'text-forest' : 'text-ink-muted group-hover:text-ink',
                                            isCollapsed ? 'h-5 w-5' : 'h-4 w-4'
                                        ]"
                                    />

                                    <!-- Label -->
                                    <span v-if="!isCollapsed" class="truncate flex-1">
                                        {{ item.label }}
                                    </span>

                                    <!-- Badge (e.g., POS, Kiosk) -->
                                    <span
                                        v-if="!isCollapsed && item.badge"
                                        class="ml-auto rounded px-1.5 py-0.5 text-[10px] font-semibold tracking-wide"
                                        :class="isActive(item.href) ? 'bg-forest/10 text-forest' : 'bg-stone text-ink-muted'"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- Sidebar Footer: User Status & Environment -->
                <div class="p-3 border-t border-border bg-stone/40">
                    <div v-if="!isCollapsed" class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-full bg-forest/10 text-forest border border-forest/20 flex items-center justify-center font-semibold text-xs shrink-0">
                            {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-ink truncate leading-tight">{{ user.name }}</p>
                            <p class="text-[11px] text-ink-muted truncate leading-tight">{{ user.email }}</p>
                        </div>
                    </div>
                    <div v-else class="flex justify-center">
                        <div class="h-8 w-8 rounded-full bg-forest/10 text-forest border border-forest/20 flex items-center justify-center font-semibold text-xs" :title="user.name">
                            {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ========================================================= -->
            <!-- MAIN CONTENT AREA & TOPBAR -->
            <!-- ========================================================= -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- TOP APPLICATION BAR -->
                <header class="h-16 bg-surface border-b border-border sticky top-0 z-10 shrink-0">
                    <div class="h-full px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4">
                        <!-- Left: Mobile Menu Trigger + Breadcrumb -->
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Mobile Hamburger Button (44px target) -->
                            <button
                                v-if="user"
                                ref="mobileTriggerRef"
                                type="button"
                                class="flex lg:hidden h-11 w-11 shrink-0 items-center justify-center rounded border border-border text-ink hover:bg-stone transition-colors"
                                :aria-expanded="mobileOpen"
                                aria-controls="mobile-navigation-drawer"
                                aria-label="Open main navigation"
                                @click="mobileOpen = true"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M3 5h14M3 10h14M3 15h14" />
                                </svg>
                            </button>

                            <!-- Mobile Brand (on screens below lg) -->
                            <Link href="/dashboard" class="flex lg:hidden min-h-11 px-1 items-center gap-2 min-w-0">
                                <span class="h-7 w-7 rounded bg-forest text-white flex items-center justify-center font-semibold text-xs shrink-0">
                                    IE
                                </span>
                                <span class="font-semibold text-sm text-ink truncate">Integrated ERP</span>
                            </Link>

                            <!-- Desktop Context Breadcrumb -->
                            <nav aria-label="Breadcrumb" class="hidden lg:flex items-center gap-2 text-sm">
                                <span class="text-ink-muted font-medium">{{ currentContext.group }}</span>
                                <svg class="h-3.5 w-3.5 text-ink-faint" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M7 5l5 5-5 5" />
                                </svg>
                                <span class="font-semibold text-ink">{{ currentContext.item }}</span>
                            </nav>
                        </div>

                        <!-- Right: Timezone Badge & User Profile Menu -->
                        <div v-if="user" class="flex items-center gap-3 shrink-0">
                            <!-- System Timezone / Operational Location Badge -->
                            <div class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-border bg-stone/50 text-[11px] text-ink-muted font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-forest" aria-hidden="true" />
                                <span>Addis Ababa (EAT)</span>
                            </div>

                            <!-- User Dropdown Menu -->
                            <div id="user-menu-container" class="relative">
                                <button
                                    type="button"
                                    class="flex items-center gap-2 min-h-11 px-2.5 rounded hover:bg-stone border border-transparent hover:border-border transition-colors text-left"
                                    :aria-expanded="userMenuOpen"
                                    aria-haspopup="true"
                                    @click="userMenuOpen = !userMenuOpen"
                                >
                                    <div class="h-8 w-8 rounded-full bg-forest text-white flex items-center justify-center font-semibold text-xs shrink-0 shadow-xs">
                                        {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                    </div>
                                    <span class="hidden lg:inline-block text-sm font-medium text-ink max-w-[130px] truncate">
                                        {{ user.name }}
                                    </span>
                                    <svg
                                        class="h-4 w-4 text-ink-muted transition-transform duration-150"
                                        :class="userMenuOpen ? 'rotate-180' : ''"
                                        viewBox="0 0 20 20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.75"
                                        aria-hidden="true"
                                    >
                                        <path d="M5 8l5 5 5-5" />
                                    </svg>
                                </button>

                                <!-- Dropdown Card -->
                                <div
                                    v-if="userMenuOpen"
                                    class="absolute right-0 mt-1 w-56 rounded border border-border bg-surface py-1 shadow-md z-30"
                                    role="menu"
                                    aria-orientation="vertical"
                                >
                                    <div class="px-4 py-2 border-b border-border-subtle">
                                        <p class="text-xs font-semibold text-ink leading-tight">{{ user.name }}</p>
                                        <p class="text-[11px] text-ink-muted truncate leading-tight mt-0.5">{{ user.email }}</p>
                                    </div>

                                    <Link
                                        href="/account/password"
                                        class="flex min-h-11 items-center gap-2.5 px-4 py-2.5 text-sm text-ink hover:bg-stone transition-colors"
                                        role="menuitem"
                                        @click="userMenuOpen = false"
                                    >
                                        <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <rect x="5" y="8" width="10" height="9" rx="1.5" />
                                            <path d="M7 8V6a3 3 0 016 0v2" />
                                        </svg>
                                        Change password
                                    </Link>

                                    <div class="border-t border-border-subtle my-1" />

                                    <button
                                        type="button"
                                        class="flex w-full min-h-11 items-center gap-2.5 px-4 py-2.5 text-sm text-red-700 hover:bg-red-50 text-left transition-colors"
                                        role="menuitem"
                                        @click="logout"
                                    >
                                        <svg class="h-4 w-4 text-red-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path d="M12 4h4a1 1 0 011 1v10a1 1 0 01-1 1h-4M9 13l3-3-3-3M12 10H3" />
                                        </svg>
                                        Sign out
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- MAIN WORKSPACE CONTENT -->
                <main id="content" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <slot />
                </main>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MOBILE OFF-CANVAS SLIDE-OUT DRAWER -->
        <!-- ========================================================= -->
        <Teleport to="body">
            <Transition name="drawer">
                <div
                    v-if="mobileOpen && user"
                    id="mobile-navigation-drawer"
                    class="fixed inset-0 z-50 lg:hidden flex"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Navigation Menu"
                >
                    <!-- Backdrop overlay -->
                    <div
                        class="fixed inset-0 bg-ink/30 backdrop-blur-xs"
                        aria-hidden="true"
                        @click="closeMobile"
                    />

                    <!-- Slide Drawer Panel -->
                    <div class="drawer-panel relative w-full max-w-xs bg-surface border-r border-border h-full flex flex-col shadow-xl z-10">
                        <!-- Drawer Header -->
                        <div class="h-16 flex items-center justify-between px-4 border-b border-border bg-stone/30">
                            <div class="flex items-center gap-2.5">
                                <div class="h-8 w-8 rounded bg-forest text-white flex items-center justify-center font-semibold text-sm shadow-xs">
                                    IE
                                </div>
                                <span class="font-semibold text-sm text-ink">Integrated ERP</span>
                            </div>

                            <!-- Close Button (44px target) -->
                            <button
                                ref="drawerCloseRef"
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded border border-border text-ink hover:bg-stone transition-colors"
                                aria-label="Close navigation"
                                @click="dismissMobile"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M5 5l10 10M15 5L5 15" />
                                </svg>
                            </button>
                        </div>

                        <!-- Scrollable Navigation in Drawer -->
                        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6" aria-label="Mobile Navigation">
                            <div
                                v-for="group in visibleGroups"
                                :key="group.title"
                                class="space-y-1"
                            >
                                <p class="px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-wider text-ink-muted">
                                    {{ group.title }}
                                </p>
                                <ul class="space-y-1">
                                    <li v-for="item in group.items" :key="item.href">
                                        <Link
                                            :href="item.href"
                                            class="flex min-h-11 items-center gap-3 px-3 rounded text-sm transition-colors"
                                            :class="isActive(item.href) ? 'bg-soft-green text-forest font-semibold shadow-xs' : 'text-ink-muted hover:text-ink hover:bg-stone'"
                                            :aria-current="isActive(item.href) ? 'page' : undefined"
                                            @click="closeMobile"
                                        >
                                            <NavIcon
                                                :name="item.icon"
                                                :class-name="[
                                                    'h-4 w-4 shrink-0',
                                                    isActive(item.href) ? 'text-forest' : 'text-ink-muted',
                                                ]"
                                            />
                                            <span class="truncate flex-1">{{ item.label }}</span>
                                            <span
                                                v-if="item.badge"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold tracking-wide"
                                                :class="isActive(item.href) ? 'bg-forest/10 text-forest' : 'bg-stone text-ink-muted'"
                                            >
                                                {{ item.badge }}
                                            </span>
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </nav>

                        <!-- Drawer Footer: User Profile Actions -->
                        <div class="p-4 border-t border-border bg-stone/40 space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-full bg-forest text-white flex items-center justify-center font-semibold text-sm shrink-0 shadow-xs">
                                    {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-ink truncate">{{ user.name }}</p>
                                    <p class="text-[11px] text-ink-muted truncate">{{ user.email }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <Link
                                    href="/account/password"
                                    class="flex min-h-11 items-center justify-center rounded border border-border bg-surface text-xs font-medium text-ink hover:bg-stone transition-colors"
                                    @click="closeMobile"
                                >
                                    Password
                                </Link>
                                <button
                                    type="button"
                                    class="flex min-h-11 items-center justify-center rounded border border-red-200 bg-red-50 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors"
                                    @click="logout"
                                >
                                    Sign out
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 200ms ease;
}

.drawer-enter-active .drawer-panel,
.drawer-leave-active .drawer-panel {
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
}

.drawer-enter-from .drawer-panel,
.drawer-leave-to .drawer-panel {
    transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
    .drawer-enter-active,
    .drawer-leave-active,
    .drawer-enter-active .drawer-panel,
    .drawer-leave-active .drawer-panel {
        transition: none;
    }
}
</style>
