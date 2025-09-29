<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import * as icons from 'lucide-vue-next'
import { debounce } from 'lodash'
import { route } from 'ziggy-js'
import { usePermissions } from '@/composables/usePermissions'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog'

const props = defineProps<{
  completedRequests: {
    data: Array<{
      id: number,
      type: string,
      form_name: string,
      avaliado: string,
      avaliador: string,
      created_at: string,
      score: string | number,
      status: string,
      can_delete: boolean,
      can_invalidate: boolean,
    }>,
    links: Array<any>
  },
  filters: {
    search: string | null,
    type?: string | null,
    form?: string | null,
    status?: string | null
  },
  availableTypes?: string[],
  availableForms?: string[]
}>()

const isDeleteDialogOpen = ref(false)
const evaluationToDelete = ref<number | null>(null)
const isInvalidateDialogOpen = ref(false)
const evaluationToInvalidate = ref<number | null>(null)
const invalidationReason = ref('')
const isInvalidationDetailsDialogOpen = ref(false)
const invalidationDetails = ref<{invalidated_by: string, invalidated_at: string, invalidation_reason?: string} | null>(null)
const search = ref(props.filters.search ?? '')
const filterType = ref(props.filters.type ?? '')
const filterForm = ref(props.filters.form ?? '')
const filterStatus = ref(props.filters.status ?? '')
const showFilters = ref(false)

const { can } = usePermissions()
const canGeneratePDF = computed(() => can('evaluations.completed.pdf'))

// Função para aplicar filtros no servidor
const applyFilters = debounce(() => {
  const filters: any = {}
  
  if (search.value) filters.search = search.value
  if (filterType.value) filters.type = filterType.value
  if (filterForm.value) filters.form = filterForm.value
  if (filterStatus.value) filters.status = filterStatus.value
  
  router.get(route('evaluations.completed'), filters, {
    preserveState: true,
    replace: true,
  })
}, 300)

// Função para limpar todos os filtros
const clearAllFilters = () => {
  search.value = ''
  filterType.value = ''
  filterForm.value = ''
  filterStatus.value = ''
  
  router.get(route('evaluations.completed'), {}, {
    preserveState: true,
    replace: true,
  })
}

// Função para contar filtros ativos
const activeFiltersCount = computed(() => {
  let count = 0
  if (search.value) count++
  if (filterType.value) count++
  if (filterForm.value) count++
  if (filterStatus.value) count++
  return count
})

// Watchers para aplicar filtros quando mudarem
watch(search, applyFilters)
watch(filterType, applyFilters)
watch(filterForm, applyFilters)
watch(filterStatus, applyFilters)

function confirmDelete(id: number) {
  evaluationToDelete.value = id
  isDeleteDialogOpen.value = true
}

function handleDelete() {
  if (!evaluationToDelete.value) return

  router.delete(route('evaluations.completed.delete', { id: evaluationToDelete.value }), {
    onSuccess: () => {
      isDeleteDialogOpen.value = false
      evaluationToDelete.value = null
    }
  })
}

function confirmInvalidate(id: number) {
  evaluationToInvalidate.value = id
  invalidationReason.value = '' // Limpar o campo de justificativa
  isInvalidateDialogOpen.value = true
}

function handleInvalidate() {
  if (!evaluationToInvalidate.value) return

  // Validar se a justificativa foi preenchida
  if (!invalidationReason.value.trim()) {
    alert('Por favor, informe o motivo da anulação.')
    return
  }

  const formData = {
    invalidation_reason: invalidationReason.value.trim()
  }

  router.post(route('evaluations.completed.invalidate', { id: evaluationToInvalidate.value }), formData, {
    onSuccess: () => {
      isInvalidateDialogOpen.value = false
      evaluationToInvalidate.value = null
      invalidationReason.value = ''
    },
    onError: () => {
      // Manter o modal aberto em caso de erro para que o usuário possa tentar novamente
    }
  })
}

async function showInvalidationDetails(id: number) {
  try {
    const response = await fetch(route('evaluations.completed.invalidation-details', { id: id }))
    if (response.ok) {
      const data = await response.json()
      invalidationDetails.value = data
      isInvalidationDetailsDialogOpen.value = true
    } else {
      console.error('Erro ao buscar detalhes da invalidação')
    }
  } catch (error) {
    console.error('Erro ao buscar detalhes da invalidação:', error)
  }
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back()
  } else {
    router.get(route('dashboard'))
  }
}
</script>

<template>

  <Head title="Avaliações Concluídas e Anuladas" />
  <DashboardLayout pageTitle="Avaliações Concluídas e Anuladas">
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">Avaliações Concluídas e Anuladas</h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
          Todas
        </span>
      </div>
      <button @click="goBack" class="back-btn">
        <icons.ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <div class="mb-6 space-y-4">
      <!-- Barra de pesquisa principal -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="relative w-full sm:w-96">
          <input
            v-model="search"
            type="text"
            placeholder="Pesquisar por avaliado, avaliador..."
            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors duration-200"
          />
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <icons.SearchIcon class="size-4 text-gray-400" />
          </div>
          <div v-if="search" class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <button
              @click="search = ''"
              class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
              type="button"
            >
              <icons.XIcon class="size-4" />
            </button>
          </div>
        </div>
        
        <div class="flex items-center gap-3">
          <!-- Botão de filtros -->
          <button
            @click="showFilters = !showFilters"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
            :class="{ 'bg-indigo-50 border-indigo-300 text-indigo-700': showFilters }"
          >
            <icons.FilterIcon class="size-4 mr-2" />
            Filtros
            <span v-if="activeFiltersCount > 0" class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full">
              {{ activeFiltersCount }}
            </span>
          </button>
          
          <!-- Contador de resultados -->
          <div class="text-sm text-gray-600">
            <span class="font-medium">{{ props.completedRequests.data.length }}</span> 
            {{ props.completedRequests.data.length === 1 ? 'avaliação' : 'avaliações' }}
          </div>
        </div>
      </div>

      <!-- Painel de filtros -->
      <div v-show="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4 transition-all duration-200">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Filtro por Tipo -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <icons.TagIcon class="size-4 inline mr-1" />
              Tipo de Avaliação
            </label>
            <select
              v-model="filterType"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="">Todos os tipos</option>
              <option v-for="type in props.availableTypes" :key="type" :value="type" class="capitalize">
                {{ type }}
              </option>
            </select>
          </div>

          <!-- Filtro por Formulário -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <icons.FileTextIcon class="size-4 inline mr-1" />
              Formulário
            </label>
            <select
              v-model="filterForm"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="">Todos os formulários</option>
              <option v-for="form in props.availableForms" :key="form" :value="form">
                {{ form }}
              </option>
            </select>
          </div>

          <!-- Filtro por Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <icons.InfoIcon class="size-4 inline mr-1" />
              Status
            </label>
            <select
              v-model="filterStatus"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="">Todos os status</option>
              <option value="completed">Concluídas</option>
              <option value="invalidated">Anuladas</option>
            </select>
          </div>

          <!-- Ações dos filtros -->
          <div class="flex items-end">
            <button
              @click="clearAllFilters"
              class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
              :disabled="activeFiltersCount === 0"
              :class="{ 'opacity-50 cursor-not-allowed': activeFiltersCount === 0 }"
            >
              <icons.XIcon class="size-4 inline mr-1" />
              Limpar Filtros
            </button>
          </div>
        </div>
        
        <!-- Resumo de filtros ativos -->
        <div v-if="activeFiltersCount > 0" class="mt-4 pt-4 border-t border-gray-200">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600">Filtros aplicados:</span>
            
            <span v-if="filterType" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              Tipo: {{ filterType }}
              <button @click="filterType = ''" class="ml-1 text-blue-600 hover:text-blue-800">
                <icons.XIcon class="size-3" />
              </button>
            </span>
            
            <span v-if="filterForm" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Formulário: {{ filterForm }}
              <button @click="filterForm = ''" class="ml-1 text-green-600 hover:text-green-800">
                <icons.XIcon class="size-3" />
              </button>
            </span>
            
            <span v-if="filterStatus" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
              Status: {{ filterStatus === 'completed' ? 'Concluídas' : filterStatus === 'invalidated' ? 'Anuladas' : filterStatus }}
              <button @click="filterStatus = ''" class="ml-1 text-indigo-600 hover:text-indigo-800">
                <icons.XIcon class="size-3" />
              </button>
            </span>
            
            <span v-if="search" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
              Busca: "{{ search }}"
              <button @click="search = ''" class="ml-1 text-purple-600 hover:text-purple-800">
                <icons.XIcon class="size-3" />
              </button>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="props.completedRequests.data.length === 0" class="text-center py-12">
      <icons.ClipboardListIcon class="mx-auto h-12 w-12 text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma avaliação encontrada</h3>
      <p class="text-gray-500 max-w-md mx-auto mb-4">
        {{ activeFiltersCount > 0 ? 'Tente ajustar os filtros para encontrar outras avaliações.' : 'Não há avaliações concluídas ou anuladas no momento.' }}
      </p>
      <button
        v-if="activeFiltersCount > 0"
        @click="clearAllFilters"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-600 bg-indigo-100 hover:bg-indigo-200 transition-colors duration-200"
      >
        <icons.XIcon class="size-4 mr-2" />
        Limpar todos os filtros
      </button>
    </div>
    <div v-else class="overflow-x-auto">
      <!-- Versão Desktop -->
      <table class="hidden sm:table min-w-full bg-white shadow rounded-lg">
        <thead>
          <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.UserCheckIcon class="size-4" />
                Avaliador
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.UserIcon class="size-4" />
                Avaliado
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.TagIcon class="size-4" />
                Tipo
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.FileTextIcon class="size-4" />
                Formulário
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.TrendingUpIcon class="size-4" />
                Nota   
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.InfoIcon class="size-4" />
                Status
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.SettingsIcon class="size-4" />
                Ações
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in props.completedRequests.data" :key="req.id" class="border-t hover:bg-gray-50 transition-colors duration-200">
            <td class="table-cell">
              <div class="text-gray-700">{{ req.avaliador }}</div>
            </td>
            <td class="table-cell">
              <div class="font-medium text-gray-900">{{ req.avaliado }}</div>
            </td>
            <td class="table-cell">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                    :class="{
                      'bg-blue-100 text-blue-800': req.type === 'servidor',
                      'bg-green-100 text-green-800': req.type === 'gestor',
                      'bg-purple-100 text-purple-800': req.type === 'comissionado',
                      'bg-yellow-100 text-yellow-800': req.type.includes('auto'),
                      'bg-gray-100 text-gray-800': !['servidor', 'gestor', 'comissionado'].includes(req.type) && !req.type.includes('auto')
                    }">
                {{ req.type }}
              </span>
            </td>
            <td class="table-cell">
              <div class="text-gray-700">{{ req.form_name }}</div>
            </td>
            <td class="table-cell">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ml-2"
                    :class="{
                      'bg-red-100 text-red-800': typeof req.score === 'number' && req.score < 60,
                      'bg-yellow-100 text-yellow-800': typeof req.score === 'number' && req.score >= 60 && req.score < 80,
                      'bg-green-100 text-green-800': typeof req.score === 'number' && req.score >= 80,
                      'bg-gray-100 text-gray-600': req.score === '-' || req.score === null
                    }">
                {{ req.score === '-' || req.score === null ? 'N/A' : req.score }}
              </span>
            </td>
            <td class="table-cell">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                    :class="{
                      'bg-green-100 text-green-800': req.status === 'completed',
                      'bg-orange-100 text-orange-800': req.status === 'invalidated'
                    }">
                {{ req.status === 'completed' ? 'Concluída' : req.status === 'invalidated' ? 'Anulada' : req.status }}
              </span>
            </td>
            <td class="table-cell">
              <div class="flex items-center gap-2">
                <a 
                  v-if="canGeneratePDF"
                  :href="route('evaluations.completed.pdf', { id: req.id })"
                  target="_blank"
                  class="inline-flex items-center px-2.5 py-1.5 border border-indigo-300 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                  title="Baixar PDF"
                >
                  <icons.Download class="size-3 mr-1" /> 
                  PDF
                </a>
                <button 
                  v-if="req.can_invalidate" 
                  @click="confirmInvalidate(req.id)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-orange-300 text-xs font-medium rounded text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition-colors duration-200"
                  title="Anular avaliação"
                >
                  <icons.XCircle class="size-3 mr-1" /> 
                  Anular
                </button>
                <button 
                  v-if="req.status === 'invalidated'" 
                  @click="showInvalidationDetails(req.id)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                  title="Ver detalhes da anulação"
                >
                  <icons.Info class="size-3" /> 
                </button>
                <button 
                  v-if="req.can_delete" 
                  @click="confirmDelete(req.id)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                  title="Excluir avaliação"
                >
                  <icons.Trash2 class="size-3 mr-1" /> 
                  Excluir
                </button>
                <span v-if="!req.can_delete && !req.can_invalidate && req.status !== 'invalidated' && !canGeneratePDF" class="text-xs text-gray-400">Sem ações</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Versão Mobile -->
      <div class="sm:hidden space-y-4">
        <div v-for="req in props.completedRequests.data" :key="req.id" 
             class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-medium text-gray-900 truncate">{{ req.avaliado }}</h3>
              <p class="text-sm text-gray-500 mt-1">Avaliado</p>
            </div>
            <div class="flex items-center gap-2 ml-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize"
                    :class="{
                      'bg-blue-100 text-blue-800': req.type === 'servidor',
                      'bg-green-100 text-green-800': req.type === 'gestor',
                      'bg-purple-100 text-purple-800': req.type === 'comissionado',
                      'bg-yellow-100 text-yellow-800': req.type.includes('auto'),
                      'bg-gray-100 text-gray-800': !['servidor', 'gestor', 'comissionado'].includes(req.type) && !req.type.includes('auto')
                    }">
                {{ req.type }}
              </span>
              <a 
                v-if="canGeneratePDF"
                :href="route('evaluations.completed.pdf', { id: req.id })"
                target="_blank"
                class="inline-flex items-center p-1.5 border border-indigo-300 rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
                title="Baixar PDF"
              >
                <icons.Download class="size-3" />
              </a>
              <button 
                v-if="req.can_invalidate" 
                @click="confirmInvalidate(req.id)"
                class="inline-flex items-center p-1.5 border border-orange-300 rounded text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition-colors duration-200"
                title="Anular avaliação"
              >
                <icons.XCircle class="size-3" />
              </button>
              <button 
                v-if="req.status === 'invalidated'" 
                @click="showInvalidationDetails(req.id)"
                class="inline-flex items-center p-1.5 border border-blue-300 rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                title="Ver detalhes da anulação"
              >
                <icons.Info class="size-3" />
              </button>
              <button 
                v-if="req.can_delete" 
                @click="confirmDelete(req.id)"
                class="inline-flex items-center p-1.5 border border-red-300 rounded text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors duration-200"
                title="Excluir avaliação"
              >
                <icons.Trash2 class="size-3" />
              </button>
            </div>
          </div>
          <div class="space-y-2">
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Avaliador:</span>
              <span class="text-sm text-gray-900 ml-2">{{ req.avaliador }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Formulário:</span>
              <span class="text-sm text-gray-900 ml-2">{{ req.form_name }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nota:</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ml-2"
                    :class="{
                      'bg-red-100 text-red-800': typeof req.score === 'number' && req.score < 60,
                      'bg-yellow-100 text-yellow-800': typeof req.score === 'number' && req.score >= 60 && req.score < 80,
                      'bg-green-100 text-green-800': typeof req.score === 'number' && req.score >= 80,
                      'bg-gray-100 text-gray-600': req.score === '-' || req.score === null
                    }">
                {{ req.score === '-' || req.score === null ? 'N/A' : req.score }}
              </span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status:</span>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ml-2"
                    :class="{
                      'bg-green-100 text-green-800': req.status === 'completed',
                      'bg-orange-100 text-orange-800': req.status === 'invalidated'
                    }">
                {{ req.status === 'completed' ? 'Concluída' : req.status === 'invalidated' ? 'Anulada' : req.status }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginação -->
    <div v-if="props.completedRequests.links.length > 3" class="mt-8 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <template v-for="(link, key) in props.completedRequests.links" :key="key">
          <div
            v-if="link.url === null"
            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-300 cursor-default"
            :class="{
              'rounded-l-md': key === 0,
              'rounded-r-md': key === props.completedRequests.links.length - 1
            }"
            v-html="link.label"
          />
          <Link
            v-else
            class="relative inline-flex items-center px-3 py-2 text-sm font-medium border border-gray-300 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-colors duration-200"
            :class="{
              'bg-indigo-50 border-indigo-500 text-indigo-600': link.active,
              'bg-white text-gray-500 hover:text-gray-700': !link.active,
              'rounded-l-md': key === 0,
              'rounded-r-md': key === props.completedRequests.links.length - 1
            }"
            :href="link.url"
            v-html="link.label"
          />
        </template>
      </nav>
    </div>

    <!-- Informação sobre filtros aplicados -->
    <div v-if="activeFiltersCount > 0" class="mt-6 text-center">
      <p class="text-sm text-gray-500">
        Filtros aplicados: {{ activeFiltersCount }} 
        {{ activeFiltersCount === 1 ? 'filtro ativo' : 'filtros ativos' }}
      </p>
    </div>

    <Dialog :open="isDeleteDialogOpen" @update:open="isDeleteDialogOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900">Excluir Avaliação</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Tem certeza que deseja excluir esta avaliação? Esta ação não pode ser desfeita.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
          <DialogClose as-child>
            <button
              type="button"
              class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            >
              Cancelar
            </button>
          </DialogClose>
          <button
            @click="handleDelete"
            type="button"
            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
          >
            Excluir
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="isInvalidateDialogOpen" @update:open="isInvalidateDialogOpen = $event">
      <DialogContent class="sm:max-w-lg bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900">Anular Avaliação</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Atenção: Tem certeza que deseja anular esta avaliação? Esta ação não poderá ser desfeita. 
          </DialogDescription>
        </DialogHeader>
        
        <div class="mt-4">
          <label for="invalidation-reason" class="block text-sm font-medium text-gray-700 mb-2">
            Motivo da anulação *
          </label>
          <textarea
            id="invalidation-reason"
            v-model="invalidationReason"
            rows="4"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm"
            placeholder="Descreva o motivo da anulação desta avaliação..."
            required
          ></textarea>
          <p class="mt-1 text-xs text-gray-500">
            Este campo é obrigatório e será registrado junto com a anulação.
          </p>
        </div>

        <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
          <DialogClose as-child>
            <button
              type="button"
              class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
              @click="invalidationReason = ''"
            >
              Cancelar
            </button>
          </DialogClose>
          <button
            @click="handleInvalidate"
            type="button"
            :disabled="!invalidationReason.trim()"
            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors"
            :class="{
              'bg-orange-600 hover:bg-orange-700': invalidationReason.trim(),
              'bg-gray-400 cursor-not-allowed': !invalidationReason.trim()
            }"
          >
            Anular
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="isInvalidationDetailsDialogOpen" @update:open="isInvalidationDetailsDialogOpen = $event">
      <DialogContent class="sm:max-w-lg bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900">Detalhes da Anulação</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Informações sobre quem anulou a avaliação e quando foi anulada.
          </DialogDescription>
        </DialogHeader>
        <div v-if="invalidationDetails" class="mt-4 space-y-3">
          <div class="bg-gray-50 p-3 rounded-lg">
            <div class="text-sm font-medium text-gray-700">Anulado por:</div>
            <div class="text-sm text-gray-900 mt-1">{{ invalidationDetails.invalidated_by }}</div>
          </div>
          <div class="bg-gray-50 p-3 rounded-lg">
            <div class="text-sm font-medium text-gray-700">Data da anulação:</div>
            <div class="text-sm text-gray-900 mt-1">{{ invalidationDetails.invalidated_at }}</div>
          </div>
          <div class="bg-gray-50 p-3 rounded-lg">
            <div class="text-sm font-medium text-gray-700">Motivo da anulação:</div>
            <div class="text-sm text-gray-900 mt-1 whitespace-pre-wrap">{{ invalidationDetails.invalidation_reason }}</div>
          </div>
        </div>
        <DialogFooter class="mt-6">
          <DialogClose as-child>
            <button
              type="button"
              class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            >
              Fechar
            </button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </DashboardLayout>
</template>
