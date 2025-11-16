import './bootstrap';
import '../css/app.css';

// Bootstrap
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});

// Listener global para Google Tag Manager en navegaciones Inertia
router.on('navigate', (event) => {
    if (window.dataLayer) {
        window.dataLayer.push({
            event: 'inertia_page_view',
            path: window.location.pathname + window.location.search,
            title: document.title,
        });
    }
});
