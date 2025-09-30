<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { computed, ref } from 'vue';
import { Bell, BellOff, Eye, EyeOff, Trash2, ArrowLeft, History } from 'lucide-vue-next';
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

interface NotificationData {
  title: string;
  content: string;
  url?: string;
}

interface Notification {
  id: string;
  type: string;
  data: NotificationData;
  read_at: string | null;
  created_at: string;
  updated_at: string;
}

interface NotificationsPagination {
  data: Notification[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}

interface Props {
  notifications: NotificationsPagination;
}

const props = defineProps<Props>();

const selectedNotifications = ref<Set<string>>(new Set());
const showOnlyUnread = ref(false);
const isDeleteModalOpen = ref(false);

// Computed properties
const filteredNotifications = computed(() => {
  if (!showOnlyUnread.value) {
    return props.notifications.data;
  }
  return props.notifications.data.filter(n => !n.read_at);
});

const unreadCount = computed(() => {
  return props.notifications.data.filter(n => !n.read_at).length;
});

const isAllSelected = computed(() => {
  return filteredNotifications.value.length > 0 && 
         filteredNotifications.value.every(n => selectedNotifications.value.has(n.id));
});

// Methods
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function toggleNotificationSelection(id: string) {
  if (selectedNotifications.value.has(id)) {
    selectedNotifications.value.delete(id);
  } else {
    selectedNotifications.value.add(id);
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedNotifications.value.clear();
  } else {
    filteredNotifications.value.forEach(n => selectedNotifications.value.add(n.id));
  }
}

function markAsRead(notification: Notification) {
  if (notification.read_at) return;
  
  router.patch(`/notifications/${notification.id}/read`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      // Atualiza localmente
      notification.read_at = new Date().toISOString();
    }
  });
}

function markSelectedAsRead() {
  const unreadSelected = Array.from(selectedNotifications.value)
    .filter(id => {
      const notification = props.notifications.data.find(n => n.id === id);
      return notification && !notification.read_at;
    });

  if (unreadSelected.length === 0) return;

  router.post('/notifications/mark-selected-read', {
    notification_ids: unreadSelected
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedNotifications.value.clear();
      router.reload({ only: ['notifications'] });
    }
  });
}

function markAllAsRead() {
  router.post('/notifications/mark-all-read', {}, {
    preserveScroll: true,
    onSuccess: () => {
      selectedNotifications.value.clear();
      router.reload({ only: ['notifications'] });
    }
  });
}

function deleteSelected() {
  if (selectedNotifications.value.size === 0) return;
  isDeleteModalOpen.value = true;
}

function confirmDelete() {
  router.post('/notifications/delete-selected', {
    notification_ids: Array.from(selectedNotifications.value)
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedNotifications.value.clear();
      isDeleteModalOpen.value = false;
      router.reload({ only: ['notifications'] });
    }
  });
}

function goToNotificationUrl(notification: Notification) {
  if (notification.data.url) {
    if (!notification.read_at) {
      markAsRead(notification);
    }
    window.location.href = notification.data.url;
  }
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get('/dashboard');
  }
}
</script>

<template>
  <Head title="Histórico de Notificações" />
  <DashboardLayout page-title="Histórico de Notificações">
    <div class="max-w-4xl mx-auto">
      <!-- Header -->
      <div class="bg-white rounded-lg shadow-sm border mb-6 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center gap-3">
            <button @click="goBack" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 transition-colors">
              <ArrowLeft class="w-5 h-5" />
              <span>Voltar</span>
            </button>
            <div class="h-6 w-px bg-gray-300"></div>
            <div class="flex items-center gap-2">
              <History class="w-6 h-6 text-indigo-600" />
              <h1 class="text-2xl font-bold text-gray-900">Histórico de Notificações</h1>
            </div>
          </div>
          
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <Bell class="w-4 h-4" />
            <span>{{ unreadCount }} não lidas de {{ notifications.total }} total</span>
          </div>
        </div>
      </div>

      <!-- Filters and Actions -->
      <div class="bg-white rounded-lg shadow-sm border mb-6 p-4">
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
          <div class="flex flex-wrap gap-3">
            <!-- Filter Toggle -->
            <label class="flex items-center gap-2 cursor-pointer">
              <input 
                type="checkbox" 
                v-model="showOnlyUnread"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              >
              <span class="text-sm text-gray-700">Mostrar apenas não lidas</span>
            </label>

            <!-- Select All -->
            <label class="flex items-center gap-2 cursor-pointer">
              <input 
                type="checkbox" 
                :checked="isAllSelected"
                @change="toggleSelectAll"
                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              >
              <span class="text-sm text-gray-700">Selecionar todas</span>
            </label>
          </div>

          <!-- Actions -->
          <div class="flex gap-2">
            <button 
              @click="markSelectedAsRead"
              :disabled="selectedNotifications.size === 0"
              class="flex items-center gap-2 px-3 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <Eye class="w-4 h-4" />
              Marcar como lidas
            </button>

            <button 
              @click="markAllAsRead"
              :disabled="unreadCount === 0"
              class="flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <BellOff class="w-4 h-4" />
              Marcar todas como lidas
            </button>

            <button 
              @click="deleteSelected"
              :disabled="selectedNotifications.size === 0"
              class="flex items-center gap-2 px-3 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <Trash2 class="w-4 h-4" />
              Excluir selecionadas
            </button>
          </div>
        </div>

        <div v-if="selectedNotifications.size > 0" class="mt-3 text-sm text-gray-600">
          {{ selectedNotifications.size }} notificação(ões) selecionada(s)
        </div>
      </div>

      <!-- Notifications List -->
      <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div v-if="filteredNotifications.length === 0" class="p-8 text-center">
          <Bell class="w-12 h-12 text-gray-400 mx-auto mb-4" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">
            {{ showOnlyUnread ? 'Nenhuma notificação não lida' : 'Nenhuma notificação encontrada' }}
          </h3>
          <p class="text-gray-600">
            {{ showOnlyUnread ? 'Todas as suas notificações foram lidas.' : 'Você ainda não recebeu nenhuma notificação.' }}
          </p>
        </div>

        <div v-else class="divide-y divide-gray-200">
          <div 
            v-for="notification in filteredNotifications" 
            :key="notification.id"
            class="p-4 hover:bg-gray-50 transition-colors"
            :class="{ 
              'bg-blue-50 border-l-4 border-l-blue-500': !notification.read_at,
              'bg-white': notification.read_at 
            }"
          >
            <div class="flex items-start gap-3">
              <!-- Checkbox -->
              <input 
                type="checkbox"
                :checked="selectedNotifications.has(notification.id)"
                @change="toggleNotificationSelection(notification.id)"
                class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
              >

              <!-- Icon -->
              <div class="flex-shrink-0 mt-1">
                <Bell 
                  :class="[
                    'w-5 h-5',
                    notification.read_at ? 'text-gray-400' : 'text-indigo-600'
                  ]" 
                />
              </div>

              <!-- Content -->
              <div class="flex-grow min-w-0">
                <div class="flex items-start justify-between gap-4">
                  <div class="flex-grow">
                    <h3 
                      class="font-semibold text-gray-900 mb-1"
                      :class="{ 'text-blue-900': !notification.read_at }"
                    >
                      {{ notification.data.title }}
                    </h3>
                    <p class="text-gray-700 text-sm mb-2">
                      {{ notification.data.content }}
                    </p>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                      <span>{{ formatDate(notification.created_at) }}</span>
                      <span v-if="notification.read_at" class="flex items-center gap-1">
                        <EyeOff class="w-3 h-3" />
                        Lida em {{ formatDate(notification.read_at) }}
                      </span>
                      <span v-else class="flex items-center gap-1 text-blue-600 font-medium">
                        <Eye class="w-3 h-3" />
                        Não lida
                      </span>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="flex items-center gap-2">
                    <!-- Ações removidas conforme solicitado -->
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="notifications.last_page > 1" class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700">
          Mostrando {{ notifications.from }} a {{ notifications.to }} de {{ notifications.total }} notificações
        </div>
        
        <div class="flex gap-2">
          <Link 
            v-if="notifications.prev_page_url"
            :href="notifications.prev_page_url"
            class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Anterior
          </Link>
          
          <span class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg">
            {{ notifications.current_page }}
          </span>
          
          <Link 
            v-if="notifications.next_page_url"
            :href="notifications.next_page_url"
            class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Próxima
          </Link>
        </div>
      </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <Dialog :open="isDeleteModalOpen" @update:open="isDeleteModalOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <Trash2 class="w-5 h-5 text-red-600" />
            Confirmar Exclusão
          </DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Tem certeza que deseja excluir {{ selectedNotifications.size }} notificação(ões) selecionada(s)? 
            Esta ação não pode ser desfeita.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
          <DialogClose as-child>
            <button 
              type="button" 
              class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors"
            >
              Cancelar
            </button>
          </DialogClose>
          <button 
            @click="confirmDelete" 
            type="button" 
            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 transition-colors"
          >
            Sim, Excluir
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </DashboardLayout>
</template>

<style scoped>
/* Custom scrollbar for the main content if needed */
.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>
