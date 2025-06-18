<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'; // Adicionado watch
// Adicionado 'router' para requisições programáticas com Inertia
import { Link, usePage, router } from '@inertiajs/vue3';
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog'; // Seus imports do Dialog

onMounted(() => {
  document.documentElement.classList.remove('dark');
});

// Import icons (adjust path if needed)
import { Menu as MenuIcon, X as XIcon } from 'lucide-vue-next';

interface NavItem {
  label: string;
  href: string;
  routeName: string;
  action?: () => void;
}

const navItems = ref<NavItem[]>([
  { label: 'Dashboard', href: '/dashboard', routeName: 'dashboard' },
  { label: 'Avaliação', href: '/evaluations', routeName: 'evaluations' },
  { label: 'PDI', href: '/pdi', routeName: 'pdi' },
  { label: 'Relatórios', href: '/reports', routeName: 'reports' },
  { label: 'Calendário', href: '/calendar', routeName: 'calendar' },
  { label: 'Configurações', href: '/configs', routeName: 'configs' },
]);

declare function route(name?: string, params?: any, absolute?: boolean, ziggy?: any): any;

const isActive = (itemRouteName: string, itemHref: string) => {
  if (typeof route === 'function' && route().current) {
    if (itemRouteName === 'dashboard' && route().current('dashboard')) {
      return true;
    }
    return route().current(itemRouteName);
  }
  const page = usePage();
  return page.url.startsWith(itemHref);
};

// Função para executar o logout após confirmação no Dialog
const executeLogout = () => {
  router.post(route('logout'), {
    onSuccess: () => {
      if (isMobileMenuOpen.value) { // Fechar menu mobile se estiver aberto
        closeMobileMenu();
      }
    },
    onError: (errors) => {
      console.error('Erro ao fazer logout:', errors);
    },
  });
};

// Estado para o menu off-canvas
const isMobileMenuOpen = ref(false);

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false;
};

// Impedir scroll do body quando o menu mobile estiver aberto
watch(isMobileMenuOpen, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});


</script>

<template>
  <div class="font-inter body min-h-screen flex justify-center items-start p-4 sm:p-6 md:p-8">
    <div class="app-container bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col">
      <header class="header bg-white p-5 sm:p-6 border-b border-gray-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <div>
            <img src="/images/logo.png" alt="Logo da Prefeitura de Lagoa Santa" class="w-auto h-8 cursor-pointer">
          </div>
          <div class="text-xl font-semibold text-gray-800">Prefeitura Municipal de Lagoa Santa</div>
        </div>
        <div class="flex items-center gap-4">
          <svg class="w-6 h-6 text-gray-500 cursor-pointer hover:text-indigo-600 transition-colors hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
          <svg class="w-6 h-6 text-gray-500 cursor-pointer hover:text-indigo-600 transition-colors hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <button @click="toggleMobileMenu" class="sm:hidden p-2 text-gray-600 hover:text-indigo-600">
            <MenuIcon class="w-6 h-6" />
          </button>
        </div>
      </header>

      <div class="px-6 sm:px-8 py-4 bg-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-2 sm:mb-0">Avaliação de desempenho</h1>
      </div>
      <!-- CORRIGIDO: A barra de navegação agora usa 'justify-center' e tem posição relativa -->
      <nav class="nav-bar bg-gray-200 p-3 sm:p-2 border-b border-gray-200 hidden sm:flex justify-center items-center gap-2 relative">
        <!-- Itens de navegação centralizados -->
        <div class="flex items-center justify-center gap-2 mr-50">
            <template v-for="(item) in navItems" :key="item.routeName">
              <Link
                :href="item.href"
                class="nav-item px-3 py-2 sm:px-4 sm:py-2 rounded-lg font-bold text-sm sm:text-base whitespace-nowrap cursor-pointer transition-all duration-200 ease-in-out"
                :class="{
                  'bg-blue-600 text-white shadow-sm': isActive(item.routeName, item.href),
                  'text-gray-600 hover:bg-blue-600 hover:text-white': !isActive(item.routeName, item.href)
                }"
              >
                {{ item.label }}
              </Link>
            </template>
        </div>

        <!-- Botão de logout posicionado de forma absoluta à direita -->
        <div class="button-logout absolute right-4 top-1/2 -translate-y-1/2 mr-6">
           <Dialog>
              <DialogTrigger as-child>
                <button
                  class="px-4 py-2 bg-red-500 text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors card-icon-logout flex items-center"
                  title="Sair do sistema"
                >
                  Sair
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ml-1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                  </svg>
                </button>
              </DialogTrigger>
              <DialogContent class="sm:max-w-md bg-white">
                <DialogHeader>
                  <DialogTitle class="text-lg font-semibold text-gray-900">Confirmar</DialogTitle>
                  <DialogDescription class="mt-2 text-sm text-gray-600">
                    Tem certeza que deseja sair do sistema?
                  </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                  <DialogClose as-child>
                    <button
                      type="button"
                      class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0"
                    >
                      Cancelar
                    </button>
                  </DialogClose>
                  <button
                    @click="executeLogout"
                    type="button"
                    class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 mt-3 sm:mt-0"
                  >
                    Sim, Sair
                  </button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
        </div>
      </nav>
      <main class="content p-6 sm:p-8 flex-grow bg-gray-50">
        <slot />
      </main>
    </div>

    <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 flex sm:hidden" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" @click="closeMobileMenu"></div>

      <div class="fixed top-0 left-0 h-full w-3/4 max-w-xs bg-white shadow-xl p-6 z-50 flex flex-col">
        <div class="flex justify-between items-center mb-8">
          <div class="flex items-center gap-2">
            <img src="/images/logo.png" alt="Logo" class="w-auto h-7">
            <span class="text-md font-semibold text-gray-700">Menu</span>
           </div>
          <button @click="closeMobileMenu" class="p-1 text-gray-500 hover:text-gray-800">
            <XIcon class="w-6 h-6" />
          </button>
        </div>

        <nav class="flex flex-col space-y-2">
          <template v-for="item in navItems" :key="item.routeName + '-mobile'">
            <Link
              :href="item.href"
              @click="closeMobileMenu"
              class="nav-item px-3 py-3 rounded-lg font-medium text-base"
              :class="{
                'bg-indigo-100 text-indigo-700 font-semibold': isActive(item.routeName, item.href),
                'text-gray-700 hover:bg-gray-100 hover:text-gray-900': !isActive(item.routeName, item.href)
              }"
            >
              {{ item.label }}
            </Link>
          </template>
        </nav>
        <div class="mt-auto pt-6 border-t border-gray-200">
            <Dialog>
                <DialogTrigger as-child>
                    <button class="w-full px-3 py-3 bg-red-50 text-red-600 rounded-lg font-medium text-base hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        Sair
                    </button>
                </DialogTrigger>
                 <DialogContent class="sm:max-w-md bg-white">
                    <DialogHeader>
                        <DialogTitle class="text-lg font-semibold text-gray-900">Confirmar Saída</DialogTitle>
                        <DialogDescription class="mt-2 text-sm text-gray-600">
                            Tem certeza que deseja sair do sistema?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                        <DialogClose as-child>
                            <button type="button" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                Cancelar
                            </button>
                        </DialogClose>
                        <button @click="executeLogout" type="button" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">
                            Sim, Sair
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
      </div>
     </div>
  </div>
</template>
