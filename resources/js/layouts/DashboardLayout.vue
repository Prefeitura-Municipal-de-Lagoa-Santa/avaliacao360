<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import axios from 'axios';
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';
import { Menu as MenuIcon, X as XIcon } from 'lucide-vue-next';
import FlashModal from '@/components/FlashModal.vue';
import { useFlashModal } from '@/composables/useFlashModal';

const { can } = usePermissions();
const { isModalVisible, modalContent, hideFlashModal } = useFlashModal();

const isMobileMenuOpen = ref(false);
const toggleMobileMenu = () => { isMobileMenuOpen.value = !isMobileMenuOpen.value; };
const closeMobileMenu = () => { isMobileMenuOpen.value = false; };
watch(isMobileMenuOpen, (isOpen) => {
  document.body.style.overflow = isOpen ? 'hidden' : '';
});

onMounted(async () => {
  document.documentElement.classList.remove('dark');
  const res = await axios.get('/notifications');
  notifications.value = res.data;
  unreadCount.value = notifications.value.filter(n => !n.read_at).length;
});

const navItems = ref([
  { label: 'Dashboard', href: '/dashboard', routeName: 'dashboard' },
  { label: 'Avaliação', href: '/evaluations', routeName: 'evaluations' },
  { label: 'PDI', href: '/pdi', routeName: 'pdi' },
  { label: 'Relatórios', href: '/reports', routeName: 'reports' },
  { label: 'Recursos', href: '/recourse', routeName: 'recourse' },
  { label: 'Calendário', href: '/calendar', routeName: 'calendar' },
  { label: 'Configurações', href: '/configs', routeName: 'configs' },
  { label: 'Admin', href: '/admin', routeName: 'admin' },
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

async function markAsRead(id: string) {
  await axios.patch(`/notifications/${id}/read`);
  // Atualiza o estado local
  const notification = notifications.value.find(n => n.id === id);
  if (notification) {
    notification.read_at = new Date().toISOString();
  }
  unreadCount.value = notifications.value.filter(n => !n.read_at).length;
}

async function dismissNotification(id: string) {
  await axios.delete(`/notifications/${id}`);
  notifications.value = notifications.value.filter(n => n.id !== id);
  unreadCount.value = notifications.value.filter(n => !n.read_at).length;
}

const executeLogout = () => {
  router.post(route('logout'), {
    onSuccess: () => {
      if (isMobileMenuOpen.value) {
        closeMobileMenu();
      }
    },
    onError: (errors) => {
      console.error('Erro ao fazer logout:', errors);
    },
  });
};

// Notificações
const notifications = ref<any[]>([]);
const unreadCount = ref(0);
const showDropdown = ref(false);
const toggleDropdown = () => { showDropdown.value = !showDropdown.value; };

function formatNotificationDate(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));
  
  if (diffInMinutes < 1) return 'Agora';
  if (diffInMinutes < 60) return `${diffInMinutes}m atrás`;
  
  const diffInHours = Math.floor(diffInMinutes / 60);
  if (diffInHours < 24) return `${diffInHours}h atrás`;
  
  const diffInDays = Math.floor(diffInHours / 24);
  if (diffInDays < 7) return `${diffInDays}d atrás`;
  
  return date.toLocaleDateString('pt-BR');
}

function goToNotificationUrl(notification: any) {
  if (notification.data.url) {
    // Marca como lida primeiro
    if (!notification.read_at) {
      markAsRead(notification.id);
    }
    showDropdown.value = false;
    window.location.href = notification.data.url;
  }
}
</script>

<template>
  <div class="font-inter body min-h-screen flex justify-center items-start p-4 sm:p-6 md:p-8">
    <div class="app-container bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col">
      <header class="header bg-white p-5 sm:p-6 border-b border-gray-200 flex justify-between items-center">
        <div class="flex items-center gap-3">
          <img src="/images/logo.png" alt="Logo da Prefeitura de Lagoa Santa" class="w-auto h-8 cursor-pointer" />
          <div class="text-xl font-semibold text-gray-800">Prefeitura Municipal de Lagoa Santa</div>
        </div>
        <div class="flex items-center gap-4">
          <!-- Notificações -->
          <div class="relative">
            <button @click="toggleDropdown" class="relative">
              <svg class="w-6 h-6 text-gray-500 hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span v-if="unreadCount" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-1">
                {{ unreadCount }}
              </span>
            </button>
            <div v-if="showDropdown" class="absolute right-0 mt-2 w-80 bg-white shadow-xl rounded-lg z-50 max-h-96 overflow-y-auto">
              <!-- Link para o histórico no topo -->
              <div class="border-b border-gray-200 p-3">
                <Link 
                  :href="route('notifications.history')" 
                  class="flex items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 transition-colors"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Ver histórico completo
                </Link>
              </div>
              
              <ul>
                <li v-for="n in notifications" :key="n.id" 
                    class="border-b p-2 text-sm hover:bg-gray-50 flex justify-between items-start"
                    :class="{ 'opacity-60 bg-gray-50': n.read_at }"
                >
                  <div class="pr-2 flex-grow cursor-pointer" @click="goToNotificationUrl(n)">
                    <strong class="block text-gray-700" :class="{ 'font-normal': n.read_at }">{{ n.data.title }}</strong>
                    <span class="text-gray-600">{{ n.data.content }}</span>
                    <div class="text-xs text-gray-500 mt-1 flex items-center gap-2">
                      {{ formatNotificationDate(n.created_at) }}
                      <span v-if="n.read_at" class="text-green-600 text-xs">✓ Lida</span>
                    </div>
                  </div>
                  <div class="flex gap-1 flex-shrink-0 ml-2">
                    <button v-if="!n.read_at" @click="markAsRead(n.id)" 
                            class="text-gray-400 hover:text-green-500 text-xs" title="Marcar como lida">
                      ✓
                    </button>
                    <button @click="dismissNotification(n.id)" 
                            class="text-gray-400 hover:text-red-500 text-xs" title="Excluir notificação">
                      ✕
                    </button>
                  </div>
                </li>
                <li v-if="notifications.length === 0" class="p-4 text-center text-sm text-gray-500">
                  Nenhuma notificação recente
                </li>
              </ul>
            </div>
          </div>

          <!-- Mobile Menu -->
          <button @click="toggleMobileMenu" class="sm:hidden p-2 text-gray-600 hover:text-indigo-600">
            <MenuIcon class="w-6 h-6" />
          </button>
        </div>
      </header>

      <!-- Subheader -->
      <div class="px-6 sm:px-8 py-4 bg-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-2 sm:mb-0">Avaliação de desempenho</h1>
      </div>

      <!-- Navbar -->
      <nav class="nav-bar bg-gray-200 p-3 sm:p-2 border-b border-gray-200 hidden sm:flex justify-center items-center gap-2 relative">
        <div class="flex items-center justify-center gap-2 mr-28">
          <template v-for="(item) in navItems" :key="item.routeName">
            <Link
              v-if="can(item.routeName)"
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

        <div class="button-logout absolute right-4 top-1/2 -translate-y-1/2 mr-6">
          <Dialog>
            <DialogTrigger as-child>
              <button class="px-3 py-2 bg-red-500 text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors card-icon-logout flex items-center" title="Sair do sistema">
                Sair
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
              </button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-md bg-white">
              <DialogHeader>
                <DialogTitle class="text-lg font-semibold text-gray-900">Confirmar</DialogTitle>
                <DialogDescription class="mt-2 text-sm text-gray-600">Tem certeza que deseja sair do sistema?</DialogDescription>
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
      </nav>

      <!-- Conteúdo da página -->
      <main class="content p-6 sm:p-8 flex-grow bg-gray-50">
        <slot />
      </main>
    </div>

    <!-- Menu Mobile -->
    <div v-if="isMobileMenuOpen" class="fixed inset-0 z-40 flex sm:hidden" role="dialog" aria-modal="true">
      <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" @click="closeMobileMenu"></div>
      <div class="fixed top-0 left-0 h-full w-3/4 max-w-xs bg-white shadow-xl p-6 z-50 flex flex-col">
        <!-- Header do menu mobile -->
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
          <div class="flex items-center gap-2">
            <img src="/images/logo.png" alt="Logo" class="w-8 h-8" />
            <span class="text-lg font-semibold text-gray-800">Menu</span>
          </div>
          <button @click="closeMobileMenu" class="p-2 text-gray-400 hover:text-gray-600">
            <XIcon class="w-6 h-6" />
          </button>
        </div>

        <!-- Navegação mobile -->
        <nav class="flex-1 space-y-2">
          <template v-for="(item) in navItems" :key="item.routeName">
            <Link
              v-if="can(item.routeName)"
              :href="item.href"
              @click="closeMobileMenu"
              class="block px-4 py-3 rounded-lg font-medium text-gray-700 hover:bg-gray-100 hover:text-indigo-600 transition-colors"
              :class="{
                'bg-indigo-50 text-indigo-600 border-l-4 border-indigo-600': isActive(item.routeName, item.href),
                'text-gray-700': !isActive(item.routeName, item.href)
              }"
            >
              {{ item.label }}
            </Link>
          </template>
        </nav>

        <!-- Botão de logout no menu mobile -->
        <div class="pt-4 mt-4 border-t border-gray-200">
          <Dialog>
            <DialogTrigger as-child>
              <button class="w-full px-4 py-3 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition-colors flex items-center justify-center gap-2">
                Sair
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
              </button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-md bg-white">
              <DialogHeader>
                <DialogTitle class="text-lg font-semibold text-gray-900">Confirmar</DialogTitle>
                <DialogDescription class="mt-2 text-sm text-gray-600">Tem certeza que deseja sair do sistema?</DialogDescription>
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

    <!-- Modal flash -->
    <FlashModal
      :show="isModalVisible"
      :type="modalContent.type"
      :title="modalContent.title"
      :message="modalContent.message"
      @close="hideFlashModal"
    />
  </div>
</template>
