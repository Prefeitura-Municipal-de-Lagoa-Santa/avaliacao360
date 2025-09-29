<script setup lang="ts">
import { watch, computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
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
      person_name: string,
      manager_name: string,
      status: string,
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
  },
}>();

const { showFlashModal } = useFlashModal();
const page = usePage();

watch(() => page.props.flash.success, (newMessage) => {
  if (newMessage) {
    showFlashModal('success', newMessage as string);
    page.props.flash.success = null;
  }
}, { immediate: true });

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

const isReleasedInfoDialogOpen = ref(false);
const selectedRequestForInfo = ref(null);

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

function handleRelease() {
  if (!selectedRequestId.value || !exceptionDateFirst.value || !exceptionDateEnd.value) {
    showFlashModal('error', 'Todas as datas são obrigatórias');
    return;
  }
  if (new Date(exceptionDateEnd.value) < new Date(exceptionDateFirst.value)) {
    showFlashModal('error', 'A data final não pode ser menor que a data inicial');
    return;
  }

  router.post(route('pdi.release'), {
    requestId: selectedRequestId.value,
    exceptionDateFirst: exceptionDateFirst.value,
    exceptionDateEnd: exceptionDateEnd.value,
  }, {
    onSuccess: () => {
      isReleaseDialogOpen.value = false;
    },
    onError: (errors) => {
      const firstError = Object.values(errors)[0];
      showFlashModal('error', firstError || 'Ocorreu um erro desconhecido.');
    }
  });
}
</script>

<template>
  <Head title="PDIs Sem Resposta" />
  <DashboardLayout pageTitle="PDIs Sem Resposta (Fora do Prazo)">
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
      <div class="flex items-start">
        <icons.AlertTriangleIcon class="size-5 text-amber-600 mt-0.5 mr-3 flex-shrink-0" />
        <div>
          <h3 class="text-sm font-medium text-amber-800 mb-1">PDIs Expirados</h3>
          <p class="text-sm text-amber-700">
            Esta página mostra os Planos de Desenvolvimento Individuais (PDIs) que não foram concluídos dentro do prazo. Você pode conceder um novo prazo clicando em "Liberar".
          </p>
        </div>
      </div>
    </div>

    <div v-if="props.pendingRequests.data.length === 0" class="text-center py-12">
      <p class="text-gray-500">Nenhum PDI pendente encontrado.</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full bg-white shadow rounded-lg">
        <thead>
          <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <th class="table-header">Servidor</th>
            <th class="table-header">Gestor</th>
            <th class="table-header">Status Atual</th>
            <th class="table-header">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in props.pendingRequests.data" :key="req.id" class="border-t hover:bg-gray-50">
            <td class="table-cell">{{ req.person_name }}</td>
            <td class="table-cell">{{ req.manager_name }}</td>
            <td class="table-cell"><span class="capitalize">{{ req.status.replace(/_/g, ' ') }}</span></td>
            <td class="table-cell">
              <button v-if="!req.is_released" @click="openReleaseDialog(req.id)" class="btn btn-green btn-sm flex items-center gap-1">
                <icons.Redo2Icon class="size-4" />
                Liberar
              </button>
              <button v-else @click="openReleasedInfoDialog(req)" class="btn btn-blue btn-sm flex items-center gap-1">
                <icons.InfoIcon class="size-4" />
                Liberado
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Dialog :open="isReleaseDialogOpen" @update:open="isReleaseDialogOpen = $event">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold">Liberar PDI</DialogTitle>
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
        </DialogHeader>
        <div class="mt-4 space-y-4" v-if="selectedRequestForInfo">
          <div>
            <label class="block text-sm font-medium text-gray-500">Novo Prazo</label>
            <p class="mt-1 text-sm text-gray-900">De <strong>{{ selectedRequestForInfo.exception_date_first }}</strong> até <strong>{{ selectedRequestForInfo.exception_date_end }}</strong></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Autorizado Por</label>
            <p class="mt-1 text-sm text-gray-900">{{ selectedRequestForInfo.released_by_name }}</p>
          </div>
        </div>
        <DialogFooter class="mt-6">
          <DialogClose as-child><button type="button" class="btn btn-gray">Fechar</button></DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </DashboardLayout>
</template>