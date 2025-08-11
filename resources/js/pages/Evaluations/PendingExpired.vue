<script setup lang="ts">
import { watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { debounce } from 'lodash';
import { route } from 'ziggy-js';
import { useFlashModal } from '@/composables/useFlashModal';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';

const props = defineProps<{
  pendingRequests: {
    data: Array<{
      id: number,
      type: string,
      form_name: string,
      avaliado: string,
      avaliador: string,
      created_at: string,
    }>,
    links: Array<any>
  },
  filters: {
    search: string | null,
    type?: string | null,
    form?: string | null
  },
  availableTypes?: string[],
  availableForms?: string[],
  canRelease: boolean,
}>();

const { showFlashModal } = useFlashModal();


const page = usePage();

function showFlashMessage(type: 'success' | 'error', message: string) {
  flash.value = { show: true, type, message };
  setTimeout(() => {
    flash.value.show = false;
  }, 5000);
}

// Observa mensagens de sucesso e usa o NOVO modal
watch(() => page.props.flash.success, (newMessage) => {
  if (newMessage) {
    
    showFlashModal('success', newMessage as string);
    page.props.flash.success = null;
  }
}, { immediate: true });

// Observa mensagens de erro e usa o NOVO modal
watch(() => page.props.flash.error, (newMessage) => {
  if (newMessage) {
    
    showFlashModal('error', newMessage as string);
    page.props.flash.error = null;
  }
}, { immediate: true });

const isReleaseDialogOpen = ref(false);
const selectedRequestId = ref<number | null>(null);
const exceptionDateFirst = ref('');
const exceptionDateEnd = ref('');
const search = ref(props.filters.search ?? '');
const filterType = ref(props.filters.type ?? '');
const filterForm = ref(props.filters.form ?? '');
const showFilters = ref(false);

const applyFilters = debounce(() => {
  const filters: any = {};
  
  if (search.value) filters.search = search.value;
  if (filterType.value) filters.type = filterType.value;
  if (filterForm.value) filters.form = filterForm.value;
  
  router.get(route('evaluations.unanswered'), filters, {
    preserveState: true,
    replace: true,
  });
}, 300);

const clearAllFilters = () => {
  search.value = '';
  filterType.value = '';
  filterForm.value = '';
  
  router.get(route('evaluations.unanswered'), {}, {
    preserveState: true,
    replace: true,
  });
};

function openReleaseDialog(requestId: number) {
  selectedRequestId.value = requestId;
  isReleaseDialogOpen.value = true;
}

function handleRelease() {
  if (!selectedRequestId.value || !exceptionDateFirst.value || !exceptionDateEnd.value) {
    showFlashMessage('error', 'Todas as datas são obrigatórias');
    return;
  }

  if (new Date(exceptionDateEnd.value) < new Date(exceptionDateFirst.value)) {
    showFlashMessage('error', 'A data final não pode ser menor que a data inicial');
    return;
  }
  
  const evaluation = props.pendingRequests.data.find(req => req.id === selectedRequestId.value);
  if (!evaluation) {
    showFlashMessage('error', 'Avaliação não encontrada');
    return;
  }

  router.post(route('evaluations.release'), {
    requestId: selectedRequestId.value,
    exceptionDateFirst: exceptionDateFirst.value,
    exceptionDateEnd: exceptionDateEnd.value,
    evaluationType: evaluation.type
  }, {
    onSuccess: () => {
      // Ações a serem feitas em caso de sucesso da requisição (mesmo com erro de flash)
      isReleaseDialogOpen.value = false;
      selectedRequestId.value = null;
      exceptionDateFirst.value = '';
      exceptionDateEnd.value = '';
      
      // MODIFICAÇÃO: Remova a linha abaixo.
      // A mensagem de sucesso ou erro será exibida pelo watcher do page.props.flash.
      // ANTES: showFlashMessage('success', 'Avaliação liberada com sucesso!');
    },
    onError: (errors) => {
      // Este bloco trata erros de validação do Laravel (status 422)
      const firstError = Object.values(errors)[0];
      if (firstError) {
        showFlashMessage('error', firstError);
      }
    }
  });
}

const activeFiltersCount = computed(() => {
  let count = 0;
  if (search.value) count++;
  if (filterType.value) count++;
  if (filterForm.value) count++;
  return count;
});

watch(search, applyFilters);
watch(filterType, applyFilters);
watch(filterForm, applyFilters);

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourses.dashboard'));
  }
}
</script>

<template>
  <Head title="Sem Resposta" />
  <DashboardLayout pageTitle="Avaliações Sem Resposta (Fora do Prazo)">
    <div v-if="flash.show"
         :class="[
           'mb-6 p-4 border rounded-lg flex justify-between items-center shadow-md transition-all duration-300',
           { 'bg-green-100 border-green-300 text-green-800': flash.type === 'success' },
           { 'bg-red-100 border-red-300 text-red-800': flash.type === 'error' }
         ]">
      <div class="flex items-center">
        <icons.CheckCircle2Icon v-if="flash.type === 'success'" class="size-5 mr-3" />
        <icons.AlertCircleIcon v-if="flash.type === 'error'" class="size-5 mr-3" />
        <span>{{ flash.message }}</span>
      </div>
      <button @click="flash.show = false" :class="[{ 'text-green-800': flash.type === 'success' }, { 'text-red-800': flash.type === 'error' }]">
        <icons.XIcon class="size-5" />
      </button>
    </div>
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">Avaliações Sem Resposta</h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
          Fora do Prazo
        </span>
      </div>
      <button @click="goBack" class="back-btn">
        <icons.ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <!-- Informações sobre o status -->
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
      <div class="flex items-start">
        <icons.AlertTriangleIcon class="size-5 text-amber-600 mt-0.5 mr-3 flex-shrink-0" />
        <div>
          <h3 class="text-sm font-medium text-amber-800 mb-1">Avaliações Expiradas</h3>
          <p class="text-sm text-amber-700">
            Esta página mostra avaliações que não foram respondidas dentro do prazo estabelecido. 
            Estas avaliações ainda podem ser completadas, mas são consideradas em atraso.
          </p>
        </div>
      </div>
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
            <span class="font-medium">{{ props.pendingRequests.data.length }}</span> 
            {{ props.pendingRequests.data.length === 1 ? 'avaliação' : 'avaliações' }}
          </div>
        </div>
      </div>

      <!-- Painel de filtros -->
      <div v-show="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4 transition-all duration-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

    <div v-if="props.pendingRequests.data.length === 0" class="text-center py-12">
      <icons.ClipboardListIcon class="mx-auto h-12 w-12 text-gray-400 mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma avaliação encontrada</h3>
      <p class="text-gray-500 max-w-md mx-auto mb-4">
        {{ activeFiltersCount > 0 ? 'Tente ajustar os filtros para encontrar outras avaliações.' : 'Todas as avaliações foram respondidas dentro do prazo ou ainda não expiraram.' }}
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
                Avaliado
              </div>
            </th>
            <th class="table-header">
              <div class="flex items-center gap-2">
                <icons.UserCheckIcon class="size-4" />
                Avaliador
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
                <icons.SettingsIcon class="size-4" />
                Ações
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in props.pendingRequests.data" :key="req.id" class="border-t hover:bg-gray-50 transition-colors duration-200">
            <td class="table-cell">
              <div class="font-medium text-gray-900">{{ req.avaliado }}</div>
            </td>
            <td class="table-cell">
              <div class="text-gray-700">{{ req.avaliador }}</div>
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
              <!-- Botão de liberar avaliação -->
              <button
                v-if="props.canRelease"
                @click="openReleaseDialog(req.id)"
                class="btn btn-green btn-sm flex items-center gap-1"
              >
                <icons.Redo2Icon class="size-4" />
                Liberar
              </button>
              <span v-else class="text-xs text-gray-400">Sem ações</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Versão Mobile -->
      <div class="sm:hidden space-y-4">
        <div v-for="req in props.pendingRequests.data" :key="req.id" 
             class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
          <div class="flex justify-between items-start mb-3">
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-medium text-gray-900 truncate">{{ req.avaliado }}</h3>
              <p class="text-sm text-gray-500 mt-1">Avaliado</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ml-2"
                  :class="{
                    'bg-blue-100 text-blue-800': req.type === 'servidor',
                    'bg-green-100 text-green-800': req.type === 'gestor',
                    'bg-purple-100 text-purple-800': req.type === 'comissionado',
                    'bg-yellow-100 text-yellow-800': req.type.includes('auto'),
                    'bg-gray-100 text-gray-800': !['servidor', 'gestor', 'comissionado'].includes(req.type) && !req.type.includes('auto')
                  }">
              {{ req.type }}
            </span>
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
          </div>
        </div>
      </div>
    </div>

    <!-- Paginação -->
    <div v-if="props.pendingRequests.links.length > 3" class="mt-8 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
        <template v-for="(link, key) in props.pendingRequests.links" :key="key">
          <div
            v-if="link.url === null"
            class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-gray-400 bg-gray-50 border border-gray-300 cursor-default"
            :class="{
              'rounded-l-md': key === 0,
              'rounded-r-md': key === props.pendingRequests.links.length - 1
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
              'rounded-r-md': key === props.pendingRequests.links.length - 1
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

    <!-- Dialog de liberação -->
    <Dialog :open="isReleaseDialogOpen" @update:open="isReleaseDialogOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900">
            Liberar Avaliação
          </DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Defina o período excepcional para preenchimento desta avaliação.
          </DialogDescription>
        </DialogHeader>

        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Data Inicial</label>
            <input
              v-model="exceptionDateFirst"
              type="date"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Data Final</label>
            <input
              v-model="exceptionDateEnd"
              type="date"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>
        </div>

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
            @click="handleRelease"
            type="button"
            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
          >
            Confirmar
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
    <div v-if="showSuccessMessage"
         class="mb-6 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg flex justify-between items-center shadow-md transition-all duration-300">
      <div class="flex items-center">
        <icons.CheckCircle2Icon class="size-5 mr-3" />
        <span>{{ successMessageContent }}</span>
      </div>
      <button @click="showSuccessMessage = false" class="text-green-800 hover:text-green-900">
        <icons.XIcon class="size-5" />
      </button>
    </div>
    <div class="detail-page-header">
      <div class="flex items-center gap-3">
        <h2 class="text-2xl font-bold text-gray-800">Avaliações Sem Resposta</h2>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
          Fora do Prazo
        </span>
      </div>
      <button @click="goBack" class="back-btn">
        <icons.ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>
  </DashboardLayout>
</template>
