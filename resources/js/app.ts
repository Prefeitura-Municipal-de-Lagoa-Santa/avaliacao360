import '../css/app.css';
import '../css/custom.css';

import { createApp, h, DefineComponent } from 'vue';
import { createInertiaApp, Link, Head, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { useFlashModal } from '@/composables/useFlashModal'; // Importe nosso controlador

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Listener global que vai funcionar em TODAS as páginas
router.on('navigate', (event: any) => {
    const page = event.detail.page;
    if (!page.props.flash) return;

    const { showFlashModal } = useFlashModal();
    const flash = page.props.flash as { success?: string, error?: string };

    if (flash.success) {
      showFlashModal('success', flash.success);
      page.props.flash.success = null; // Limpa para não reaparecer
    }
    if (flash.error) {
      showFlashModal('error', flash.error);
      page.props.flash.error = null; // Limpa para não reaparecer
    }
});