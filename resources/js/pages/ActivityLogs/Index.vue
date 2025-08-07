<template>
  <Head title="Logs de Atividade" />
  <DashboardLayout pageTitle="Logs de Atividade">
    <!-- Header com descrição -->
    <div class="mb-6">
      <p class="text-gray-600">
        Histórico completo de todas as alterações no sistema
      </p>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Filtros</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Busca geral -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Buscar
          </label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Usuário, IP, descrição..."
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- Ação -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Ação
          </label>
          <select
            v-model="filters.action"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Todas as ações</option>
            <option v-for="action in filterOptions.actions" :key="action" :value="action">
              {{ action }}
            </option>
          </select>
        </div>

        <!-- Botões de ação -->
        <div class="flex items-end space-x-2">
          <button
            @click="applyFilters"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            Filtrar
          </button>
          <button
            @click="clearFilters"
            class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500"
          >
            Limpar
          </button>
        </div>
      </div>
    </div>

    <!-- Tabela de logs -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Data/Hora
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Usuário
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Ação
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Descrição
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                IP
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatDateTime(log.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">
                  {{ log.user_name }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getActionBadgeClass(log.action)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900">
                {{ log.description }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ log.ip_address }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button
                  @click="viewDetails(log)"
                  class="text-blue-600 hover:text-blue-900"
                >
                  Ver detalhes
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="logs.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200">
        <div class="flex justify-between items-center">
          <p class="text-sm text-gray-700">
            Página {{ logs.current_page }} de {{ logs.last_page }} - Total: {{ logs.total }} registros
          </p>
          <div class="flex space-x-2">
            <button
              v-if="logs.prev_page_url"
              @click="goToPage(logs.current_page - 1)"
              class="px-3 py-1 border border-gray-300 text-sm rounded-md hover:bg-gray-50"
            >
              Anterior
            </button>
            <button
              v-if="logs.next_page_url"
              @click="goToPage(logs.current_page + 1)"
              class="px-3 py-1 border border-gray-300 text-sm rounded-md hover:bg-gray-50"
            >
              Próximo
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de detalhes -->
    <div v-if="selectedLog" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 flex items-center justify-center" @click="closeDetails">
      <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 max-h-96 overflow-y-auto" @click.stop>
        <h3 class="text-lg font-medium text-gray-900 mb-4">Detalhes do Log</h3>
        
        <div class="space-y-3">
          <div><strong>Data:</strong> {{ formatDateTime(selectedLog.created_at) }}</div>
          <div><strong>Usuário:</strong> {{ selectedLog.user_name }}</div>
          <div><strong>Ação:</strong> {{ selectedLog.action }}</div>
          <div><strong>IP:</strong> {{ selectedLog.ip_address }}</div>
          <div><strong>Descrição:</strong> {{ selectedLog.description }}</div>
          
          <div v-if="selectedLog.changes" class="bg-blue-50 p-3 rounded">
            <strong>Alterações:</strong>
            <pre class="text-sm mt-2">{{ JSON.stringify(selectedLog.changes, null, 2) }}</pre>
          </div>
        </div>
        
        <button @click="closeDetails" class="mt-4 px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
          Fechar
        </button>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { ref, reactive } from 'vue'
import { router, Head } from '@inertiajs/vue3'

interface ActivityLog {
  id: number
  model_type: string
  model_id: number
  action: string
  user_id: number | null
  user_name: string
  ip_address: string
  user_agent: string
  description: string
  old_values: any
  new_values: any
  changes: any
  created_at: string
}

interface PaginatedLogs {
  data: ActivityLog[]
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
  prev_page_url: string | null
  next_page_url: string | null
}

interface Props {
  logs: PaginatedLogs
  filters: Record<string, any>
  filterOptions: {
    actions: string[]
    modelTypes: Array<{ value: string; label: string }>
  }
}

const props = defineProps<Props>()

const filters = reactive({
  search: props.filters.search || '',
  action: props.filters.action || '',
})

const selectedLog = ref<ActivityLog | null>(null)

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString('pt-BR')
}

const getActionBadgeClass = (action: string) => {
  switch (action) {
    case 'created':
      return 'bg-green-100 text-green-800'
    case 'updated':
      return 'bg-yellow-100 text-yellow-800'
    case 'deleted':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

const applyFilters = () => {
  router.get(route('activity-logs.index'), filters, {
    preserveState: true,
    replace: true
  })
}

const clearFilters = () => {
  filters.search = ''
  filters.action = ''
  applyFilters()
}

const goToPage = (page: number) => {
  router.get(route('activity-logs.index'), { ...filters, page }, {
    preserveState: true,
    replace: true
  })
}

const viewDetails = (log: ActivityLog) => {
  selectedLog.value = log
}

const closeDetails = () => {
  selectedLog.value = null
}
</script>