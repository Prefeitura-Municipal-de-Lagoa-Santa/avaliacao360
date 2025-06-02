import '../css/app.css'; // Seu CSS principal, incluindo Tailwind

import { createApp, h, DefineComponent } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
// import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m'; // Se estiver usando Ziggy para rotas

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .component('Link', Link) // Registrar globalmente se usado frequentemente
            .component('Head', Head) // Registrar globalmente
            // .use(ZiggyVue) // Se estiver usando Ziggy
            .mount(el);
    },
    progress: {
        color: '#4F46E5', // Cor da barra de progresso do Inertia
        showSpinner: true,
    },
});