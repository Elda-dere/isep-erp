import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

createInertiaApp({
    // Turns a page name like "Hello" into the file resources/js/Pages/Hello.vue
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        // Every page sits inside AppLayout, except sign-in and public pages.
        if (page && !name.startsWith('Auth/') && !name.startsWith('Public/')) {
            page.default.layout = page.default.layout || AppLayout;
        }

        return page;
    },

    // Starts Vue and mounts the page inside the root template.
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});