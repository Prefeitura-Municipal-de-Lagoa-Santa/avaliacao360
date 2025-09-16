<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import * as icons from 'lucide-vue-next'
import { debounce } from 'lodash'
import { route } from 'ziggy-js'

const props = defineProps<{
  completedPdis: {
    data: Array<{
      id: number,
      pdi_year: number,
      form_name: string,
      person_name: string,
      manager_name: string,
      created_at: string,
    }>,
    links: Array<any>
  },
  filters: {
    search: string | null,
    year?: number | null
  },
  availableYears?: number[]
}>()

const search = ref(props.filters.search ?? '')
const filterYear = ref(props.filters.year ?? '')
const showFilters = ref(false)

// Função para aplicar filtros no servidor
const applyFilters = debounce(() => {
  const filters: any = {}
  
  if (search.value) filters.search = search.value
  if (filterYear.value) filters.year = filterYear.value
  
  router.get(route('pdi.completed'), filters, {
    preserveState: true,
    replace: true,
  })
}, 300)

// Função para limpar todos os filtros
const clearAllFilters = () => {
  search.value = ''
  filterYear.value = ''
  
  router.get(route('pdi.completed'), {}, {
    preserveState: true,
    replace: true,
  })
}

// Função para contar filtros ativos
const activeFiltersCount = computed(() => {
  let count = 0
  if (search.value) count++
  if (filterYear.value) count++
  return count
})

// Watchers para aplicar filtros quando mudarem
watch(search, applyFilters)
watch(filterYear, applyFilters)

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
</script>

<template>
  <Head title="PDIs Concluídos" />
  <DashboardLayout pageTitle="PDIs Concluídos">
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">PDIs Concluídos</h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
          Concluídos
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
            <span class="font-medium">{{ props.completedPdis.data.length }}</span> 
            {{ props.completedPdis.data.length === 1 ? 'PDI' : 'PDIs' }}
          </div>
        </div>
      </div>

      <!-- Painel de filtros -->
      <div v-show="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4 transition-all duration-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            
            <span v-if="filterYear" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              Ano: {{ filterYear }}
              <button @click="filterYear = ''" class="ml-1 text-blue-600 hover:text-blue-800">
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

    <div v-if="props.completedPdis.data.length === 0" class="text-center py-12">
      <icons.FileCheckIcon class="mx-auto h-12 w-12 text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum PDI concluído encontrado</h3>
      <p class="text-gray-500 max-w-md mx-auto mb-4">
        {{ activeFiltersCount > 0 ? 'Tente ajustar os filtros para encontrar outros PDIs.' : 'Não há PDIs concluídos no momento.' }}
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
          <tr v-for="pdi in props.completedPdis.data" :key="pdi.id" class="border-t hover:bg-gray-50 transition-colors duration-200">
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
              <div class="text-gray-700">{{ pdi.form_name }}</div>
            </td>
            <td class="table-cell">
              <button 
                @click="viewPdi(pdi.id)"
                class="inline-flex items-center px-2.5 py-1.5 border border-blue-300 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                title="Visualizar PDI"
              >
                <icons.EyeIcon class="size-3 mr-1" /> 
                Visualizar
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Versão Mobile -->
      <div class="sm:hidden space-y-4">
        <div v-for="pdi in props.completedPdis.data" :key="pdi.id" 
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
              <button 
                @click="viewPdi(pdi.id)"
                class="inline-flex items-center p-1.5 border border-blue-300 rounded text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200"
                title="Visualizar PDI"
              >
                <icons.EyeIcon class="size-3" />
              </button>
            </div>
          </div>
          
          <div class="space-y-2">
            <div>
              <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Gestor:</span>
              <span class="text-sm text-gray-900 ml-2">{{ pdi.manager_name }}</span>
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
    <div v-if="props.completedPdis.links.length > 3" class="mt-8 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <template v-for="(link, key) in props.completedPdis.links" :key="key">
          <div
            v-if="link.url === null"
            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-300 cursor-default"
            :class="{
              'rounded-l-md': key === 0,
              'rounded-r-md': key === props.completedPdis.links.length - 1
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
              'rounded-r-md': key === props.completedPdis.links.length - 1
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
</template>
