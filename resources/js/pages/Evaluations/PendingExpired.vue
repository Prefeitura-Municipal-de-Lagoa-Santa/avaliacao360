<script setup lang="ts">
import { watch, computed, ref } from 'vue';
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
      is_released: boolean,
      exception_date_first: string | null,
      exception_date_end: string | null,
      released_by_name: string | null,
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

// Esta função agora está obsoleta pois estamos usando o useFlashModal, mas a manteremos caso seja usada em outro lugar.
function showFlashMessage(type: 'success' | 'error', message: string) {
  showFlashModal(type, message);
}

// Observa mensagens de sucesso e usa o NOVO modal
watch(() => page.props.flash.success, (newMessage) => {
  if (newMessage) {
    showFlashModal('success', newMessage as string);
    // Limpa a prop flash para evitar que o modal seja exibido novamente em navegações futuras
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

// State para o diálogo de liberação
const isReleaseDialogOpen = ref(false);
const selectedRequestId = ref<number | null>(null);
const exceptionDateFirst = ref('');
const exceptionDateEnd = ref('');

// State para o novo diálogo de informações da liberação
const isReleasedInfoDialogOpen = ref(false);
const selectedRequestForInfo = ref<{
  id: number,
  type: string,
  form_name: string,
  avaliado: string,
  avaliador: string,
  created_at: string,
  is_released: boolean,
  exception_date_first: string | null,
  exception_date_end: string | null,
  released_by_name: string | null,
} | null>(null);

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
  exceptionDateFirst.value = '';
  exceptionDateEnd.value = '';
  isReleaseDialogOpen.value = true;
}

function openReleasedInfoDialog(request: any) {
  selectedRequestForInfo.value = request;
  isReleasedInfoDialogOpen.value = true;
}

function handleReleaseAgain() {
  if (!selectedRequestForInfo.value) return;
  
  // Fecha o diálogo de informações
  isReleasedInfoDialogOpen.value = false;
  
  // Abre o diálogo de liberação com as datas atuais
  selectedRequestId.value = selectedRequestForInfo.value.id;
  exceptionDateFirst.value = selectedRequestForInfo.value.exception_date_first || '';
  exceptionDateEnd.value = selectedRequestForInfo.value.exception_date_end || '';
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
      isReleaseDialogOpen.value = false;
      // As mensagens de sucesso/erro agora são tratadas pelo watcher global
    },
    onError: (errors) => {
      const firstError = Object.values(errors)[0];
      if (firstError) {
        showFlashMessage('error', firstError);
      } else {
        showFlashMessage('error', 'Ocorreu um erro desconhecido.');
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

    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
      <div class="flex items-start">
        <icons.AlertTriangleIcon class="size-5 text-amber-600 mt-0.5 mr-3 flex-shrink-0" />
        <div>
          <h3 class="text-sm font-medium text-amber-800 mb-1">Avaliações Expiradas</h3>
          <p class="text-sm text-amber-700">
            Esta página mostra avaliações que não foram respondidas dentro do prazo estabelecido. Você pode conceder um
            novo prazo clicando em "Liberar".
          </p>
        </div>
      </div>
    </div>

    <div class="mb-6 space-y-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="relative w-full sm:w-96">
          <input v-model="search" type="text" placeholder="Pesquisar por avaliado, avaliador..."
            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-colors duration-200" />
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <icons.SearchIcon class="size-4 text-gray-400" />
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button @click="showFilters = !showFilters"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors duration-200"
            :class="{ 'bg-indigo-50 border-indigo-300 text-indigo-700': showFilters }">
            <icons.FilterIcon class="size-4 mr-2" />
            Filtros
            <span v-if="activeFiltersCount > 0"
              class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full">
              {{ activeFiltersCount }}
            </span>
          </button>
        </div>
      </div>

      <div v-show="showFilters" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          <div>
            <label for="filter-type" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por Tipo</label>
            <select id="filter-type" v-model="filterType" class="form-select w-full">
              <option value="">Todos os Tipos</option>
              <option v-for="type in props.availableTypes" :key="type" :value="type" class="capitalize">
                {{ type }}
              </option>
            </select>
          </div>

          <div>
            <label for="filter-form" class="block text-sm font-medium text-gray-700 mb-1">Filtrar por
              Formulário</label>
            <select id="filter-form" v-model="filterForm" class="form-select w-full">
              <option value="">Todos os Formulários</option>
              <option v-for="form in props.availableForms" :key="form" :value="form">
                {{ form }}
              </option>
            </select>
          </div>

          <div class="flex items-end">
            <button @click="clearAllFilters" class="btn btn-gray w-full sm:w-auto">
              <icons.XCircleIcon class="size-4 mr-2" />
              Limpar Filtros
            </button>
          </div>
        </div>
      </div>
      </div>

    <div v-if="props.pendingRequests.data.length === 0" class="text-center py-12">
      <p class="text-gray-500">Nenhuma avaliação pendente encontrada.</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="hidden sm:table min-w-full bg-white shadow rounded-lg">
        <thead>
          <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <th class="table-header">Avaliado</th>
            <th class="table-header">Avaliador</th>
            <th class="table-header">Tipo</th>
            <th class="table-header">Formulário</th>
            <th class="table-header">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in props.pendingRequests.data" :key="req.id" class="border-t hover:bg-gray-50">
            <td class="table-cell">{{ req.avaliado }}</td>
            <td class="table-cell">{{ req.avaliador }}</td>
            <td class="table-cell"><span class="capitalize">{{ req.type }}</span></td>
            <td class="table-cell">{{ req.form_name }}</td>
            <td class="table-cell">
              <button v-if="props.canRelease && !req.is_released" @click="openReleaseDialog(req.id)"
                class="btn btn-green btn-sm flex items-center gap-1">
                <icons.Redo2Icon class="size-4" />
                Liberar
              </button>
              <button v-else-if="req.is_released" @click="openReleasedInfoDialog(req)"
                class="btn btn-blue btn-sm flex items-center gap-1">
                <icons.InfoIcon class="size-4" />
                Liberado
              </button>
              <span v-else class="text-xs text-gray-400">Sem ações</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="sm:hidden space-y-4">
        <div v-for="req in props.pendingRequests.data" :key="req.id"
          class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-start mb-3">
              <div class="flex-1 min-w-0">
                <h3 class="text-sm font-medium text-gray-900 truncate">{{ req.avaliado }}</h3>
                <p class="text-sm text-gray-500 mt-1">Avaliado</p>
              </div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ml-2">{{
                req.type }}</span>
            </div>
            <div class="space-y-2 text-sm">
              <div><strong class="text-gray-600">Avaliador:</strong> {{ req.avaliador }}</div>
              <div><strong class="text-gray-600">Formulário:</strong> {{ req.form_name }}</div>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t flex justify-end">
            <button v-if="props.canRelease && !req.is_released" @click="openReleaseDialog(req.id)"
              class="btn btn-green btn-sm flex items-center gap-1">
              <icons.Redo2Icon class="size-4" />
              Liberar
            </button>
            <button v-else-if="req.is_released" @click="openReleasedInfoDialog(req)"
              class="btn btn-blue btn-sm flex items-center gap-1">
              <icons.InfoIcon class="size-4" />
              Liberado
            </button>
            <span v-else class="text-xs text-gray-400 self-center">Sem ações</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="props.pendingRequests.links.length > 3" class="mt-8 flex justify-center">
    </div>

    <Dialog :open="isReleaseDialogOpen" @update:open="isReleaseDialogOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Liberar Avaliação</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Defina o período excepcional para preenchimento.
          </DialogDescription>
        </DialogHeader>
        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium">Data Inicial</label>
            <input v-model="exceptionDateFirst" type="date" class="form-input" />
          </div>
          <div>
            <label class="block text-sm font-medium">Data Final</label>
            <input v-model="exceptionDateEnd" type="date" class="form-input" />
          </div>
        </div>
        <DialogFooter class="mt-6 flex justify-end gap-3">
          <DialogClose as-child><button type="button" class="btn btn-gray">Cancelar</button></DialogClose>
          <button @click="handleRelease" type="button" class="btn btn-green">Confirmar</button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="isReleasedInfoDialogOpen" @update:open="isReleasedInfoDialogOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Detalhes da Liberação</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            Esta avaliação foi liberada para preenchimento fora do prazo padrão.
          </DialogDescription>
        </DialogHeader>
        <div class="mt-4 space-y-4" v-if="selectedRequestForInfo">
          <div>
            <label class="block text-sm font-medium text-gray-500">
              <icons.CalendarDaysIcon class="size-4 inline mr-1.5" />Novo Prazo
            </label>
            <p class="mt-1 text-sm text-gray-900 ml-5.5">De <strong>{{ selectedRequestForInfo.exception_date_first
            }}</strong> até <strong>{{ selectedRequestForInfo.exception_date_end }}</strong></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">
              <icons.UserCheckIcon class="size-4 inline mr-1.5" />Autorizado Por
            </label>
            <p class="mt-1 text-sm text-gray-900 ml-5.5">{{ selectedRequestForInfo.released_by_name || 'Não informado' }}</p>
          </div>
        </div>
        <DialogFooter class="mt-6 flex justify-between items-center">
          <button 
            v-if="props.canRelease" 
            @click="handleReleaseAgain" 
            type="button"
            class="inline-flex items-center rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
            <icons.Redo2Icon class="size-4 mr-2" />
            Liberar Novamente
          </button>
          <DialogClose as-child>
            <button type="button"
              class="inline-flex items-center rounded-lg bg-gray-500 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
              Fechar
            </button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </DashboardLayout>
</template>