<script setup lang="ts">
import { ref, computed } from 'vue'; // Removido defineProps e PropType pois não estavam sendo usados diretamente aqui para props complexas
import { Link, usePage } from '@inertiajs/vue3';

interface NavItem {
  label: string;
  href: string; // href é obrigatório para navegação com Link
  routeName: string; // Nome da rota para verificação com route().current()
  action?: () => void; // Para itens que disparam ações JS (não usado neste exemplo de nav)
}

// Props para o layout, se necessário (ex: título da página)
// Se você precisar passar o título da página do controller, descomente e defina as props
// const props = defineProps({
//   pageTitle: {
//     type: String,
//     default: 'Dashboard',
//   },
// });

const navItems = ref<NavItem[]>([
  { label: 'Dashboard', href: '/dashboard', routeName: 'dashboard' },
  { label: 'Avaliação', href: '/evalutions', routeName: 'evalutions' }, // Assumindo que as rotas têm esses nomes
  { label: 'Relatórios', href: '/reports', routeName: 'reports' },
  { label: 'Calendário', href: '/calendar', routeName: 'calendar' },
  { label: 'Configurações', href: '/configs', routeName: 'configs' },
]);

// Função global route() injetada pelo Ziggy
// Esta declaração ajuda o TypeScript a entender que route() existe globalmente
// Você precisará ter o Ziggy configurado corretamente no seu projeto.
declare function route(name?: string, params?: any, absolute?: boolean, ziggy?: any): any;

const isActive = (itemRouteName: string, itemHref: string) => {
  // Verifica se a função route() está disponível
  if (typeof route === 'function' && route().current) {
    // Se o nome da rota for 'dashboard' e a rota atual for 'dashboard', é ativo.
    // Ou se o nome da rota atual corresponder ao itemRouteName.
    if (itemRouteName === 'dashboard' && route().current('dashboard')) {
        return true;
    }
    return route().current(itemRouteName);
  }
  // Fallback se route() não estiver disponível (menos preciso)
  const page = usePage();
  return page.url.startsWith(itemHref);
};


// Lógica para o menu mobile, notificações, etc. pode ser adicionada aqui
</script>

<template>
  <div class="font-inter bg-gray-100 min-h-screen flex justify-center items-start p-4 sm:p-6 md:p-8">
    <div class="app-container bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col">
      <header class="header bg-white p-5 sm:p-6 border-b border-gray-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <svg class="w-6 h-6 text-gray-600 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
          <div class="text-xl font-semibold text-gray-800">Software</div>
        </div>
        <div class="flex items-center gap-4">
          <svg class="w-6 h-6 text-gray-500 cursor-pointer hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
          <svg class="w-6 h-6 text-gray-500 cursor-pointer hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <svg class="w-6 h-6 text-gray-500 cursor-pointer hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </div>
      </header>

      <div class="px-6 sm:px-8 py-4 bg-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-2 sm:mb-0">Avaliação de desempenho 360°</h1>
        <button class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded-lg font-medium text-sm hover:bg-indigo-200 transition-colors">
          Ver mais
        </button>
      </div>

      <nav class="nav-bar bg-gray-50 p-3 sm:p-4 border-b border-gray-200 flex gap-3 sm:gap-4 overflow-x-auto">
        <template v-for="(item) in navItems" :key="item.routeName">
          <Link
            :href="item.href"
            class="nav-item px-3 py-2 sm:px-4 sm:py-2 rounded-xl font-medium text-sm sm:text-base whitespace-nowrap cursor-pointer transition-all duration-200 ease-in-out"
            :class="{
              'bg-indigo-100 text-indigo-700 font-semibold': isActive(item.routeName, item.href),
              'text-gray-600 hover:bg-gray-200 hover:text-gray-800': !isActive(item.routeName, item.href)
            }"
          >
            {{ item.label }}
          </Link>
        </template>
      </nav>

      <main class="content p-6 sm:p-8 flex-grow">
        <slot /> </main>
    </div>
  </div>
</template>

<style scoped>
/* Estilos específicos do layout, se necessário. Tailwind é priorizado. */
.app-container {
  min-height: calc(100vh - 4rem); /* Ajuste para padding do body */
}

.nav-bar::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Opera */
}
.nav-bar {
  -ms-overflow-style: none;  /* IE and Edge */
  scrollbar-width: none; /* Firefox */
}
</style>