<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import * as icons from 'lucide-vue-next'
import { route } from 'ziggy-js'

const props = defineProps<{
  pendingPdis: {
    data: Array<{
      id: number,
      pdi_year: number,
      form_name: string,
      person_name: string,
      manager_name: string,
      status: string,
      created_at: string,
      is_out_of_deadline: boolean,
      can_interact: boolean,
      exception_date_first: string | null,
      exception_date_end: string | null,
    }>,
    links: Array<any>
  },
  filters: {
    search: string | null,
    status?: string | null,
    year?: number | null
  },
  availableYears?: number[],
  availableStatuses?: string[],
  canReleasePdis?: boolean
}>()
console.log(props);

const search = ref(props.filters.search ?? '')
const filterStatus = ref(props.filters.status ?? '')
const filterYear = ref(props.filters.year ?? '')
const showFilters = ref(false)

// Variáveis para modal de liberação
const showReleaseModal = ref(false)
const selectedPdi = ref<any>(null)
const releaseForm = ref({
  exceptionDateFirst: '',
  exceptionDateEnd: ''
})
const isSubmitting = ref(false)

// Variáveis para modal de visualização das datas liberadas
const showReleasedDatesModal = ref(false)
const selectedReleasedPdi = ref<any>(null)

// Função para aplicar filtros no servidor
const applyFilters = () => {
  const filters: any = {}
  
  if (search.value) filters.search = search.value
  if (filterStatus.value) filters.status = filterStatus.value
  if (filterYear.value) filters.year = filterYear.value
  
  router.get(route('pdi.pending'), filters, {
    preserveState: true,
    replace: true,
  })
}

// Função para limpar todos os filtros
const clearAllFilters = () => {
  search.value = ''
  filterStatus.value = ''
  filterYear.value = ''
  
  router.get(route('pdi.pending'), {}, {
    preserveState: true,
    replace: true,
  })
}

// Função para contar filtros ativos
const activeFiltersCount = computed(() => {
  let count = 0
  if (search.value) count++
  if (filterStatus.value) count++
  if (filterYear.value) count++
  return count
})

// Watchers para aplicar filtros quando mudarem
watch([search, filterStatus, filterYear], applyFilters)

function goBack() {
  if (window.history.length > 1) {
    window.history.back()
  } else {
    router.get(route('dashboard'))
  }
}

function viewPdi(id: number) {
  router.get(route('pdi.show', id))
}

function openReleaseModal(pdi: any) {
  selectedPdi.value = pdi
  // Se já tem datas de exceção, pre-preencher
  if (pdi.exception_date_first && pdi.exception_date_end) {
    releaseForm.value.exceptionDateFirst = pdi.exception_date_first
    releaseForm.value.exceptionDateEnd = pdi.exception_date_end
  } else {
    // Limpar o formulário
    releaseForm.value.exceptionDateFirst = ''
    releaseForm.value.exceptionDateEnd = ''
  }
  showReleaseModal.value = true
}

function closeReleaseModal() {
  showReleaseModal.value = false
  selectedPdi.value = null
  releaseForm.value.exceptionDateFirst = ''
  releaseForm.value.exceptionDateEnd = ''
}

function submitRelease() {
  if (!selectedPdi.value || !releaseForm.value.exceptionDateFirst || !releaseForm.value.exceptionDateEnd) {
    return
  }

  isSubmitting.value = true

  router.post(route('pdi.release'), {
    requestId: selectedPdi.value.id,
    exceptionDateFirst: releaseForm.value.exceptionDateFirst,
    exceptionDateEnd: releaseForm.value.exceptionDateEnd,
  }, {
    onSuccess: () => {
      closeReleaseModal()
    },
    onFinish: () => {
      isSubmitting.value = false
    }
  })
}

function getStatusLabel(status: string): string {
  const statusMap: Record<string, string> = {
    'pending_manager_fill': 'Aguardando Preenchimento do Gestor',
    'pending_employee_signature': 'Aguardando Assinatura do Servidor'
  }
  return statusMap[status] || status
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'pending_manager_fill':
      return 'bg-orange-100 text-orange-800'
    case 'pending_employee_signature':
      return 'bg-blue-100 text-blue-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

// Funções para modal de visualização das datas liberadas
function openReleasedDatesModal(pdi: any) {
  selectedReleasedPdi.value = pdi
  showReleasedDatesModal.value = true
}

function closeReleasedDatesModal() {
  showReleasedDatesModal.value = false
  selectedReleasedPdi.value = null
}

// Função para formatar data
function formatDate(dateString: string | null): string {
  if (!dateString) return 'Não definida'
  return new Date(dateString).toLocaleDateString('pt-BR')
}
</script>

<template>
  <Head title="PDIs Pendentes" />
  <DashboardLayout pageTitle="PDIs Pendentes">
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">PDIs Pendentes</h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
          Pendentes
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
            placeholder="Pesquisar por pessoa, gestor..."
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
            <span class="font-medium">{{ props.pendingPdis.data.length }}</span> 
            {{ props.pendingPdis.data.length === 1 ? 'PDI' : 'PDIs' }}
          </div>
        </div>
      </div>

      <!-- Painel de filtros -->
      <div v-show="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4 transition-all duration-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Filtro por Status -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <icons.TagIcon class="size-4 inline mr-1" />
              Status
            </label>
            <select
              v-model="filterStatus"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="">Todos os status</option>
              <option v-for="status in props.availableStatuses" :key="status" :value="status">
                {{ getStatusLabel(status) }}
              </option>
            </select>
          </div>

          <!-- Filtro por Ano -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              <icons.CalendarIcon class="size-4 inline mr-1" />
              Ano
            </label>
            <select
              v-model="filterYear"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
            >
              <option value="">Todos os anos</option>
              <option v-for="year in props.availableYears" :key="year" :value="year">
                {{ year }}
              </option>
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
            
            <span v-if="filterStatus" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              Status: {{ getStatusLabel(filterStatus) }}
              <button @click="filterStatus = ''" class="ml-1 text-blue-600 hover:text-blue-800">
                <icons.XIcon class="size-3" />
              </button>
            </span>
            
            <span v-if="filterYear" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              Ano: {{ filterYear }}
              <button @click="filterYear = ''" class="ml-1 text-green-600 hover:text-green-800">
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

    <div v-if="props.pendingPdis.data.length === 0" class="text-center py-12">
      <icons.FileTextIcon class="mx-auto h-12 w-12 text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum PDI pendente encontrado</h3>
      <p class="text-gray-500 max-w-md mx-auto mb-4">
        {{ activeFiltersCount > 0 ? 'Tente ajustar os filtros para encontrar outros PDIs.' : 'Não há PDIs pendentes no momento.' }}
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
                <icons.UserIcon class="size-4" />
                Servidor
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.UserCheckIcon class="size-4" />
                Gestor
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.CalendarIcon class="size-4" />
                Ano
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.TagIcon class="size-4" />
                Status
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
                <icons.SettingsIcon class="size-4" />
                Ações
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pdi in props.pendingPdis.data" :key="pdi.id" class="border-t hover:bg-gray-50 transition-colors duration-200">
            <td class="table-cell">
              <div class="font-medium text-gray-900">{{ pdi.person_name }}</div>
            </td>
            <td class="table-cell">
              <div class="text-gray-700">{{ pdi.manager_name }}</div>
            </td>
            <td class="table-cell">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ pdi.pdi_year }}
              </span>
            </td>
            <td class="table-cell">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="getStatusColor(pdi.status)">
                {{ getStatusLabel(pdi.status) }}
              </span>
            </td>
            <td class="table-cell">
              <div class="text-gray-700">{{ pdi.form_name }}</div>
            </td>
            <td class="table-cell">
              <div class="flex gap-2">
                <!-- Botão Liberado quando tem datas de exceção definidas -->
                <button 
                  v-if="pdi.exception_date_first && pdi.exception_date_end && pdi.status === 'pending_manager_fill'"
                  @click="openReleasedDatesModal(pdi)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                  title="Ver datas de liberação"
                >
                  <icons.CheckCircleIcon class="size-3 mr-1" /> 
                  Liberado
                </button>

                <!-- Botão Visualizar quando pode interagir (sem datas de exceção) -->
                <button 
                  v-else-if="pdi.can_interact && pdi.status === 'pending_manager_fill'"
                  @click="viewPdi(pdi.id)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                  title="Preencher PDI"
                >
                  <icons.PenIcon class="size-3 mr-1" /> 
                  Visualizar
                </button>

                <!-- Botão Assinar quando pode interagir -->
                <button 
                  v-if="pdi.can_interact && pdi.status === 'pending_employee_signature'"
                  @click="viewPdi(pdi.id)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-green-300 text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                  title="Assinar PDI"
                >
                  <icons.PenIcon class="size-3 mr-1" /> 
                  Assinar
                </button>
                
                <!-- Botão Liberar quando não pode interagir e está fora do prazo -->
                <button 
                  v-if="!pdi.can_interact && pdi.is_out_of_deadline && pdi.status === 'pending_manager_fill' || !pdi.can_interact && pdi.is_out_of_deadline && pdi.status === 'pending_employee_signature' && props.canReleasePdis"
                  @click="openReleaseModal(pdi)"
                  class="inline-flex items-center px-2.5 py-1.5 border border-orange-300 text-xs font-medium rounded text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition-colors duration-200"
                  title="Liberar PDI com novo prazo"
                >
                  <icons.ClockIcon class="size-3 mr-1" /> 
                  Liberar
                </button>
                
                <!-- Mensagem quando não pode interagir -->
                <span 
                  v-if="!pdi.can_interact && !pdi.is_out_of_deadline"
                  class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-gray-500 bg-gray-100 rounded"
                >
                  <icons.ClockIcon class="size-3 mr-1" />
                  Aguardando prazo
                </span>

                <!-- Mensagem quando está fora do prazo e não foi liberado 
                <span 
                  v-if="!pdi.can_interact && pdi.is_out_of_deadline && pdi.status === 'pending_employee_signature'"
                  class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-red-500 bg-red-100 rounded"
                >
                  <icons.XIcon class="size-3 mr-1" />
                  Prazo expirado
                </span> -->
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Versão Mobile -->
      <div class="sm:hidden space-y-4">
        <div v-for="pdi in props.pendingPdis.data" :key="pdi.id" 
             class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-medium text-gray-900 truncate">{{ pdi.person_name }}</h3>
              <p class="text-sm text-gray-500 mt-1">Servidor</p>
            </div>
            <div class="flex items-center gap-2 ml-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ pdi.pdi_year }}
              </span>
              <!-- Botão condicional para mobile -->
              <!-- Botão Liberado quando tem datas de exceção definidas -->
              <button 
                v-if="pdi.exception_date_first && pdi.exception_date_end && pdi.status === 'pending_manager_fill'"
                @click="openReleasedDatesModal(pdi)"
                class="inline-flex items-center p-1.5 border border-green-300 rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                title="Ver datas de liberação"
              >
                <icons.CheckCircleIcon class="size-3" />
              </button>
              
              <!-- Botão Preencher quando pode interagir (sem datas de exceção) -->
              <button 
                v-else-if="pdi.can_interact && pdi.status === 'pending_manager_fill'"
                @click="viewPdi(pdi.id)"
                class="inline-flex items-center p-1.5 border border-blue-300 rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                title="Preencher PDI"
              >
                <icons.PenIcon class="size-3" />
              </button>
              
              <!-- Botão Assinar quando pode interagir -->
              <button 
                v-else-if="pdi.can_interact && pdi.status === 'pending_employee_signature'"
                @click="viewPdi(pdi.id)"
                class="inline-flex items-center p-1.5 border border-green-300 rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors duration-200"
                title="Assinar PDI"
              >
                <icons.PenIcon class="size-3" />
              </button>
              
              <!-- Botão Liberar quando não pode interagir e está fora do prazo -->
              <button 
                v-else-if="!pdi.can_interact && pdi.is_out_of_deadline && pdi.status === 'pending_manager_fill' && props.canReleasePdis"
                @click="openReleaseModal(pdi)"
                class="inline-flex items-center p-1.5 border border-orange-300 rounded text-orange-700 bg-orange-50 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500 transition-colors duration-200"
                title="Liberar PDI com novo prazo"
              >
                <icons.ClockIcon class="size-3" />
              </button>
              
              <!-- Status quando não pode interagir -->
              <span 
                v-else-if="!pdi.can_interact && !pdi.is_out_of_deadline"
                class="inline-flex items-center p-1.5 text-gray-400 bg-gray-100 rounded"
                title="Aguardando prazo"
              >
                <icons.ClockIcon class="size-3" />
              </span>
              
              <!-- Status quando está fora do prazo e não foi liberado -->
              <span 
                v-else-if="!pdi.can_interact && pdi.is_out_of_deadline && pdi.status === 'pending_employee_signature'"
                class="inline-flex items-center p-1.5 text-red-400 bg-red-100 rounded"
                title="Prazo expirado"
              >
                <icons.XIcon class="size-3" />
              </span>
            </div>
          </div>
          
          <div class="space-y-2">
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gestor:</span>
              <span class="text-sm text-gray-900 ml-2">{{ pdi.manager_name }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status:</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ml-2" :class="getStatusColor(pdi.status)">
                {{ getStatusLabel(pdi.status) }}
              </span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Formulário:</span>
              <span class="text-sm text-gray-900 ml-2">{{ pdi.form_name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginação -->
    <div v-if="props.pendingPdis.links.length > 3" class="mt-8 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <template v-for="(link, key) in props.pendingPdis.links" :key="key">
          <div
            v-if="link.url === null"
            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-300 cursor-default"
            :class="{
              'rounded-l-md': key === 0,
              'rounded-r-md': key === props.pendingPdis.links.length - 1
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
              'rounded-r-md': key === props.pendingPdis.links.length - 1
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

  </DashboardLayout>

  <!-- Modal de Liberação de PDI -->
  <div v-if="showReleaseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeReleaseModal">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" @click.stop>
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            Liberar PDI
          </h3>
          <button 
            @click="closeReleaseModal"
            class="text-gray-400 hover:text-gray-600 focus:outline-none"
          >
            <icons.XIcon class="size-6" />
          </button>
        </div>
        
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">
            Servidor: <span class="font-medium">{{ selectedPdi?.person_name }}</span>
          </p>
          <p class="text-sm text-gray-600 mb-4">
            Este PDI está fora do prazo. Defina um novo período de exceção:
          </p>
        </div>

        <form @submit.prevent="submitRelease" class="space-y-4">
          <div>
            <label for="exception_date_first" class="block text-sm font-medium text-gray-700 mb-1">
              Data de Início
            </label>
            <input
              type="date"
              id="exception_date_first"
              v-model="releaseForm.exceptionDateFirst"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>
          
          <div>
            <label for="exception_date_end" class="block text-sm font-medium text-gray-700 mb-1">
              Data de Término
            </label>
            <input
              type="date"
              id="exception_date_end"
              v-model="releaseForm.exceptionDateEnd"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button
              type="button"
              @click="closeReleaseModal"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"
              :disabled="isSubmitting"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm font-medium text-white bg-orange-600 border border-transparent rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isSubmitting"
            >
              {{ isSubmitting ? 'Liberando...' : 'Liberar PDI' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Visualização das Datas Liberadas -->
  <div v-if="showReleasedDatesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click="closeReleasedDatesModal">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" @click.stop>
      <div class="mt-3">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            PDI Liberado
          </h3>
          <button 
            @click="closeReleasedDatesModal"
            class="text-gray-400 hover:text-gray-600 focus:outline-none"
          >
            <icons.XIcon class="size-6" />
          </button>
        </div>
        
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">
            Servidor: <span class="font-medium">{{ selectedReleasedPdi?.person_name }}</span>
          </p>
          <p class="text-sm text-gray-600 mb-4">
            Este PDI foi liberado com o seguinte período de exceção:
          </p>
        </div>

        <div class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center mb-3">
              <icons.CheckCircleIcon class="size-5 text-green-600 mr-2" />
              <span class="font-medium text-green-800">Período de Liberação</span>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <label class="block text-gray-600 font-medium mb-1">Data de Início:</label>
                <p class="text-gray-900 bg-white border border-green-200 rounded px-3 py-2">
                  {{ formatDate(selectedReleasedPdi?.exception_date_first) }}
                </p>
              </div>
              
              <div>
                <label class="block text-gray-600 font-medium mb-1">Data de Término:</label>
                <p class="text-gray-900 bg-white border border-green-200 rounded px-3 py-2">
                  {{ formatDate(selectedReleasedPdi?.exception_date_end) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-6">
          <button
            @click="closeReleasedDatesModal"
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
          >
            Fechar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
