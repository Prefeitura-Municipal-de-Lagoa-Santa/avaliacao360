<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'; // 1. Importar 'watch'
import { Head, usePage, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import axios from 'axios';
import { route } from 'ziggy-js';
import { useFlashModal } from '@/composables/useFlashModal';

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

// --- DEFINIÇÃO DAS PROPS ---
// 2. Corrigir a tipagem da prop 'configs'
const props = defineProps<{
  forms: Record<string, {
    id: number;
    name: string;
    type: string;
    year: string;
    release: boolean;
    release_data: string | null;
    term_first: string | null;
    term_end: string | null;
  }>;
   // Agora `configs` é um objeto onde a chave é o ano (string)
   // e o valor é o objeto de configuração para aquele ano.
   configs: Record<string, {
    gradesPeriod: string;
    awarePeriod: number;
    recoursePeriod: number;
  }>;
  existingYears: Array<string | number>;
}>();

const { showFlashModal } = useFlashModal();

// --- ESTADO DA PÁGINA ---
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });
const selectedYear = ref(String(new Date().getFullYear()));

// Limpar mensagens flash automaticamente
watch(() => flash.value.success, (newMessage) => {
  if (newMessage) {
    setTimeout(() => {
      const flashProps = page.props.flash as { success?: string | null; error?: string | null };
      if (flashProps.success) {
        flashProps.success = null;
      }
    }, 1000); // Remove a mensagem após 1 segundo
  }
}, { immediate: true });

watch(() => flash.value.error, (newMessage) => {
  if (newMessage) {
    setTimeout(() => {
      const flashProps = page.props.flash as { success?: string | null; error?: string | null };
      if (flashProps.error) {
        flashProps.error = null;
      }
    }, 1000); // Remove a mensagem após 1 segundo
  }
}, { immediate: true });

const gradesPeriod = ref('');
const awarePeriod = ref<number | string>('');
const recoursePeriod = ref<number | string>('');

// 3. (NOVO) Propriedade computada para obter a configuração do ano selecionado
const currentYearConfig = computed(() => {
  return props.configs[selectedYear.value] || null;
});

// 4. (NOVO) Observador para atualizar o formulário quando o ano ou as configs mudarem
watch(currentYearConfig, (newConfig) => {
  if (newConfig) {
    // Se existe configuração para o ano, preenche os campos
    gradesPeriod.value = newConfig.gradesPeriod ? newConfig.gradesPeriod.substring(0, 10) : '';
    awarePeriod.value = newConfig.awarePeriod;
    recoursePeriod.value = newConfig.recoursePeriod;
  } else {
    // Se não existe, limpa os campos
    gradesPeriod.value = '';
    awarePeriod.value = '';
    recoursePeriod.value = '';
  }
}, { immediate: true }); // `immediate: true` faz com que seja executado na montagem inicial


// --- Restante do seu código <script setup> permanece o mesmo ---
// ... (isPrazoModalVisible, isPreviewModalVisible, yearOptions, etc.)
// ... (NÃO PRECISA MAIS DO onMounted para setar os valores de config)

// O método saveSettings() já envia o `selectedYear.value`, então ele funcionará corretamente
// com as mudanças do backend sem precisar de alterações.

// --- ESTADO DO MODAL DE PRAZO ---
const isPrazoModalVisible = ref(false);
const prazoGroup = ref<'avaliacao' | 'pdi' | null>(null);
const prazoDateInicio = ref('');
const prazoDateFim = ref('');

// Garante que a data fim nunca fique antes da data início
watch(prazoDateInicio, (inicio) => {
  if (inicio && prazoDateFim.value && prazoDateFim.value < inicio) {
    prazoDateFim.value = inicio;
  }
});

// --- ESTADO PARA UPLOAD DE CSV DE PESSOAS ---
const isPreviewModalVisible = ref(false);
const isLiberarModalVisible = ref(false);
const liberarModalTitle = ref('');
const liberarModalDescription = ref('');
const groupToLiberar = ref<'avaliacao' | 'pdi' | null>(null);
const isConfirmationDialog = ref(false);
const isProcessing = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref('');
const uploadSummary = ref<{ new: number; updated: number; unchanged: number; errors: number; } | null>(null);
const uploadErrors = ref<string[]>([]);
const uploadDetails = ref<any[]>([]);
const tempFilePath = ref<string | null>(null);

// --- PROPRIEDADES COMPUTADAS ---
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const nextYear = currentYear + 1;
  const yearsSet = new Set([currentYear, nextYear, ...(props.existingYears || []).map(year => Number(year))]);
  return Array.from(yearsSet).sort((a, b) => b - a);
});

const formsForSelectedYear = computed(() => {
  return Object.values(props.forms).filter(form => String(form.year) === selectedYear.value);
});

const isAvaliacaoGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['servidor', 'gestor', 'chefia', 'comissionado'].includes(form.type)).some(form => form.release));
const isPdiGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['pactuacao_servidor','pactuacao_comissionado','pactuacao_gestor'].includes(form.type)).some(form => form.release));

const avaliacaoReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['servidor', 'gestor', 'chefia', 'comissionado'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

const canManuallyGenerateEvaluations = computed(() => {

  const avaliacaoForm = formsForSelectedYear.value.find(form =>
    ['servidor', 'gestor', 'chefia', 'comissionado'].includes(form.type)
  );

  if (!avaliacaoForm || !avaliacaoForm.term_first) {
    return false;
  }

  const startDate = new Date(avaliacaoForm.term_first);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return startDate > today;
});

const pdiReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['pactuacao_servidor','pactuacao_comissionado','pactuacao_gestor'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

const canManuallyGeneratePdi = computed(() => {
  const pdiForm = formsForSelectedYear.value.find(form =>
    ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'].includes(form.type)
  );
  if (!pdiForm || !pdiForm.term_first) {
    return false;
  }
  const startDate = new Date(pdiForm.term_first);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  // Só permite gerar manualmente se a data atual for menor que o início do prazo
  return startDate > today;
});

// --- MÉTODOS DE AÇÃO - FORMULÁRIOS ---
const getFormForType = (type: string) => props.forms[`${selectedYear.value}_${type}`] || null;

function openPrazoModal(group: 'avaliacao' | 'pdi') {
  prazoGroup.value = group;
  const formTypes = group === 'avaliacao' ? ['servidor', 'gestor', 'chefia', 'comissionado'] : ['pactuacao_servidor','pactuacao_comissionado','pactuacao_gestor'];
  const existingForm = formsForSelectedYear.value.find(f => formTypes.includes(f.type) && f.term_first && f.term_end);

  if (existingForm) {
    prazoDateInicio.value = existingForm.term_first?.substring(0, 10) ?? '';
    prazoDateFim.value = existingForm.term_end?.substring(0, 10) ?? '';
  } else {
    prazoDateInicio.value = '';
    prazoDateFim.value = '';
  }
  isPrazoModalVisible.value = true;
}

const isSavingPrazo = ref(false);
function handleSetPrazo() {
  if (!prazoGroup.value || !prazoDateInicio.value || !prazoDateFim.value) {
    showFlashModal('error', 'Por favor, preencha as datas de início e encerramento.');
    return;
  }
  isSavingPrazo.value = true;
  router.visit(route('configs.prazo.store'), {
    method: 'post',
    data: {
      year: selectedYear.value,
      group: prazoGroup.value,
      term_first: prazoDateInicio.value,
      term_end: prazoDateFim.value,
    },
    onSuccess: () => {
      isSavingPrazo.value = false;
      isPrazoModalVisible.value = false;
      showFlashModal('success', 'Prazo salvo com sucesso!');
    },
    onError: (errors) => {
      isSavingPrazo.value = false;
      const first = Object.values(errors || {})[0];
      showFlashModal('error', first || 'Erro ao salvar o prazo. Verifique as datas.');
    },
    onFinish: () => { isSavingPrazo.value = false; }
  });
}

function handleLiberar(group: 'avaliacao' | 'pdi') {
  const formTypes = group === 'avaliacao' ? ['servidor', 'gestor', 'chefia', 'comissionado'] : ['pactuacao_servidor','pactuacao_comissionado','pactuacao_gestor'];
  const formComPrazo = formsForSelectedYear.value.find(f => formTypes.includes(f.type) && f.term_first && f.term_end);

  if (!formComPrazo) {
    isConfirmationDialog.value = false;
    liberarModalTitle.value = 'Prazos Não Definidos';
    liberarModalDescription.value = 'É necessário definir os prazos de início e encerramento antes de poder liberar este grupo de formulários.';
  } else {
    isConfirmationDialog.value = true;
    groupToLiberar.value = group;
    liberarModalTitle.value = 'Confirmar Liberação';
    liberarModalDescription.value = `Tem certeza que deseja LIBERAR os formulários de ${group.toUpperCase()} para o ano de ${selectedYear.value}? Esta ação não pode ser desfeita.`;
  }

  isLiberarModalVisible.value = true;
}
function confirmAndLiberar() {
  if (!groupToLiberar.value) return;

  router.visit(route('configs.liberar.store'), {
    method: 'post',
    data: {
      year: selectedYear.value,
      group: groupToLiberar.value
    },
    onSuccess: () => {
      isLiberarModalVisible.value = false;
      generateRelease();
    }
  });
}

function saveSettings() {
  const form = useForm({
    year: selectedYear.value,
    gradesPeriod: gradesPeriod.value,
    awarePeriod: awarePeriod.value,
    recoursePeriod: recoursePeriod.value,
  });
  form.post(route('configs.store'), {
    onSuccess: () => showFlashModal('success', 'Configurações salvas com sucesso!'),
    onError: (errors) => {
      const firstError = Object.values(errors)[0];
      showFlashModal('error', `Erro ao salvar: ${firstError}`);
    }
  });
}

function handleCreate(type: string) { router.get(route('configs.create', { year: selectedYear.value, type: type })); }
function handleEdit(formId: number) { router.get(route('configs.edit', { formulario: formId })); }
function handleView(formId: number) { router.get(route('configs.show', { formulario: formId })); }

// --- MÉTODOS DE AÇÃO - UPLOAD DE CSV ---
function triggerFileInput() {
  fileInput.value?.click();
}

async function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  if (!file.name.toLowerCase().endsWith('.csv')) {
    showFlashModal('error', 'Por favor, selecione um arquivo .csv');
    return;
  }

  fileName.value = file.name;
  isProcessing.value = true;
  isPreviewModalVisible.value = true;

  const formData = new FormData();
  formData.append('file', file);
  
  try {
    const response = await axios.post(route('persons.preview'), formData);
    const data = response.data;

    uploadSummary.value = data.summary;
    uploadErrors.value = data.errors;
    uploadDetails.value = data.detailed_changes;
    tempFilePath.value = data.temp_file_path;

  } catch (error: any) {
    showFlashModal('error', 'Erro ao gerar a pré-visualização: ' + (error.response?.data?.message || error.message));
    closePreviewModal();
  } finally {
    isProcessing.value = false;
    if (target) {
      target.value = '';
    }
  }
}

async function handleConfirmUpload() {
  if (!tempFilePath.value) {
    showFlashModal('error', "Nenhum arquivo temporário encontrado para confirmar.");
    return;
  }
  isProcessing.value = true;
  try {
    const response = await axios.post(route('persons.confirm'), {
      temp_file_path: tempFilePath.value
    });
    showFlashModal('success', response.data.message);
    closePreviewModal();
  router.reload();
  } catch (error: any) {
    showFlashModal('error', 'Erro ao confirmar o upload: ' + (error.response?.data?.message || error.message));
  } finally {
    isProcessing.value = false;
  }
}

function generateRelease() {
  router.visit(route('releases.generate', { year: selectedYear.value }), {
    method: 'post',
    onSuccess: () => {
      showFlashModal('success', 'Avaliações geradas com sucesso!');
      closePreviewModal();
    },
    onError: () => {
      showFlashModal('error', 'Ocorreu um erro ao gerar as avaliações.');
    }
  });
}

function generatePdiRelease() {
  router.visit(route('pdi.generate', { year: selectedYear.value }), {
    method: 'post',
    onSuccess: () => {
      showFlashModal('success', 'A geração dos PDIs foi iniciada com sucesso!');
    },
    onError: () => {
      showFlashModal('error', 'Ocorreu um erro ao gerar os PDIs.');
    }
  });
}

function closePreviewModal() {
  isPreviewModalVisible.value = false;
  fileName.value = '';
  uploadSummary.value = null;
  uploadErrors.value = [];
  uploadDetails.value = [];
  tempFilePath.value = null;
}

// --- CONTROLO DO MODAL ---
const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isPreviewModalVisible.value) {
    closePreviewModal();
  }
};

// Remova o onMounted que definia os valores de configuração. O 'watch' já faz isso.
onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});

</script>

<template>

  <Head title="Configurações" />
  <DashboardLayout pageTitle="Configurações">

    <div v-if="flash && flash.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
      {{ flash.success }}
    </div>
    <div v-if="flash && flash.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
      {{ flash.error }}
    </div>

    <div class="space-y-8 max-w-4xl mx-auto">

      <div class="settings-section">
        <h3>Formulários</h3>
        <div class="setting-item">
          <label for="form-year">Ano:</label>
          <select id="form-year" v-model="selectedYear" class="form-select rounded-md border-gray-300 text-black">
            <option v-for="year in yearOptions" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>

        <div class="form-section">
          <h4>AVALIAÇÃO</h4>
          <div class="setting-item">
            <label>Formulário I - Servidor:</label>
            <div class="button-group">
              <button v-if="getFormForType('servidor')" @click="handleView(getFormForType('servidor').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('servidor') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('servidor').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('servidor')" @click="handleCreate('servidor')" class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário II - Gestor:</label>
            <div class="button-group">
              <button v-if="getFormForType('gestor')" @click="handleView(getFormForType('gestor').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('gestor') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('gestor').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('gestor')" @click="handleCreate('gestor')" class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário III - Equipe do Gestor:</label>
            <div class="button-group">
              <button v-if="getFormForType('chefia')" @click="handleView(getFormForType('chefia').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('chefia') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('chefia').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('chefia')" @click="handleCreate('chefia')" class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário IV - Comissionado:</label>
            <div class="button-group">
              <button v-if="getFormForType('comissionado')" @click="handleView(getFormForType('comissionado').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('comissionado') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('comissionado').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('comissionado')" @click="handleCreate('comissionado')"
                class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item border-t pt-4 mt-4">
            <label>Ações da Avaliação:</label>
            <div v-if="!isAvaliacaoGroupReleased" class="button-group">
              <button class="btn btn-orange" @click="openPrazoModal('avaliacao')">
                <span>Prazo</span>
                <component :is="icons.ClockIcon" class="size-5" />
              </button>
              <button class="btn btn-pine" @click="handleLiberar('avaliacao')">
                <span>Liberar</span>
                <component :is="icons.SendIcon" class="size-5" />
              </button>
            </div>
            <div v-else class="text-green-600 font-semibold flex items-center gap-2">
              <component :is="icons.CheckCircle2Icon" class="size-5" />
              Liberado em: {{ avaliacaoReleaseData }}
              <Dialog v-if="canManuallyGenerateEvaluations">
                <DialogTrigger as-child>
                  <button
                    class="px-2 py-3 rounded-lg font-medium text-base transition-colors flex items-center justify-center gap-3">
                    <component :is="icons.SquarePenIcon" class="size-5" />
                    Gerar Avaliações
                  </button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md bg-white">
                  <DialogHeader>
                    <DialogTitle class="text-lg font-semibold text-gray-900">Gerar avaliações</DialogTitle>
                    <DialogDescription class="mt-2 text-sm text-gray-600">
                      Tem certeza que deseja gerar as avaliações para o ano de {{ selectedYear }}? Isso irá criar os
                      formulários de avaliação para todos os gestores.
                    </DialogDescription>
                  </DialogHeader>
                  <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <DialogClose as-child>
                      <button type="button"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        Cancelar
                      </button>
                    </DialogClose>

                    <DialogClose as-child>
                      <button @click="generateRelease" type="button"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                        Sim, Gerar
                      </button>
                    </DialogClose>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
          <div class="setting-item">
            <label>Formulário de Pactuação - Servidor:</label>
            <div class="button-group">
              <button v-if="getFormForType('pactuacao_servidor')" @click="handleView(getFormForType('pactuacao_servidor').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('pactuacao_servidor') && !isPdiGroupReleased"
                @click="handleEdit(getFormForType('pactuacao_servidor').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('pactuacao_servidor')" @click="handleCreate('pactuacao_servidor')"
                class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Pactuação - Comissionado:</label>
            <div class="button-group">
              <button v-if="getFormForType('pactuacao_comissionado')" @click="handleView(getFormForType('pactuacao_comissionado').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('pactuacao_comissionado') && !isPdiGroupReleased"
                @click="handleEdit(getFormForType('pactuacao_comissionado').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('pactuacao_comissionado')" @click="handleCreate('pactuacao_comissionado')"
                class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Pactuação - Gestor:</label>
            <div class="button-group">
              <button v-if="getFormForType('pactuacao_gestor')" @click="handleView(getFormForType('pactuacao_gestor').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('pactuacao_gestor') && !isPdiGroupReleased"
                @click="handleEdit(getFormForType('pactuacao_gestor').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('pactuacao_gestor')" @click="handleCreate('pactuacao_gestor')"
                class="btn btn-create">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item border-t pt-4 mt-4">
            <label>Ações do PDI:</label>
            <div v-if="!isPdiGroupReleased" class="button-group">
              <button class="btn btn-orange" @click="openPrazoModal('pdi')">
                <span>Prazo</span>
                <component :is="icons.ClockIcon" class="size-5" />
              </button>
              <button class="btn btn-pine" @click="handleLiberar('pdi')">
                <span>Liberar</span>
                <component :is="icons.SendIcon" class="size-5" />
              </button>
            </div>
            <div v-else class="text-green-600 font-semibold flex items-center gap-2">
              <component :is="icons.CheckCircle2Icon" class="size-5" />
              Liberado em: {{ pdiReleaseData }}
              <Dialog v-if="canManuallyGeneratePdi">
    <DialogTrigger as-child>
      <button class="ml-4 btn btn-blue">
        <component :is="icons.SquarePenIcon" class="size-5 mr-2" />
        Gerar PDIs
      </button>
    </DialogTrigger>
    <DialogContent class="sm:max-w-md bg-white">
      <DialogHeader>
        <DialogTitle class="text-lg font-semibold text-gray-900">Gerar PDIs</DialogTitle>
        <DialogDescription class="mt-2 text-sm text-gray-600">
          Tem certeza que deseja gerar os Planos de Desenvolvimento Individual (PDI) para o ano de {{ selectedYear }}?
        </DialogDescription>
      </DialogHeader>
      <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
        <DialogClose as-child>
          <button type="button" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
            Cancelar
          </button>
        </DialogClose>
        <DialogClose as-child>
          <button @click="generatePdiRelease" type="button" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
            Sim, Gerar
          </button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
            </div>
          </div>
        </div>
      </div>
       <!-- Configurações -->
      <div class="settings-section">
        <h3>Configurações</h3>

        <div class="setting-item">
          <label for="release-date">Definir data de divulgação das notas:</label>
          <input type="date" id="release-date" class="input-date" v-model="gradesPeriod" :min="new Date().toISOString().substring(0,10)" />
        </div>

        <div class="setting-item">
          <label>Definir período de ciência das notas (quantidade de dias após divulgação):</label>
          <input type="number" id="aware-period" min="0" class="input-day" placeholder="Nº de dias" v-model="awarePeriod">
        </div>

        <div class="setting-item">
          <label for="appeal-days">Definir período de recurso (quantidade de dias após a ciência das notas):</label>
          <input type="number" id="recourse-period" min="0" class="input-day" placeholder="Nº de dias" v-model="recoursePeriod">
        </div>

        <div class="setting-item">
          <label>Salvar alterações:</label>
          <button @click="saveSettings" class="btn btn-green">
            <span>Salvar</span>
            <component :is="icons.SaveIcon" class="size-5" />
          </button>
        </div>
      </div>
      <div class="settings-section">
        <h3>Gestão de Pessoas</h3>
        <div class="setting-item">
          <label>Upload de Planilha de Pessoas:</label>
          <button @click="triggerFileInput" class="btn btn-yellow">
            <span>Upload</span>
            <component :is="icons.UploadCloudIcon" class="size-5" />
          </button>
          <input type="file" ref="fileInput" @change="handleFileSelect" class="hidden" accept=".csv">
        </div>
        <div class="setting-item">
          <label>Editar Pessoas:</label>
          <button @click="router.get(route('people.index'))" class="btn btn-yellow">
            <span>Editar</span>
            <component :is="icons.UsersIcon" class="size-5" />
          </button>
        </div>
        <div class="setting-item">
          <label>Criar Chefe(Cedidos):</label>
          <button @click="router.get(route('people.manual.create'))" class="btn btn-yellow">
            <span>Criar</span>
            <component :is="icons.UserIcon" class="size-5" />
          </button>
        </div>
        <div class="setting-item">
          <label>Grupo de Funções:</label>
          <button @click="router.get(route('funcoes.index'))" class="btn btn-yellow">
            <span>Editar</span>
            <component :is="icons.BookOpenCheck" class="size-5" />
          </button>
        </div>
        <div class="setting-item">
          <label>Organograma:</label>
          <button @click="router.get(route('organizational-chart.index'))" class="btn btn-yellow">
            <span>Ver</span>
            <component :is="icons.ChartArea" class="size-5" />
          </button>
        </div>
      </div>

      <div class="settings-section">
        <h3>Gestão de Usuários</h3>
        <div class="setting-item">
          <label>Editar Papéis:</label>
          <button @click="router.get(route('users.manage-roles'))" class="btn btn-yellow">
            <span>Editar</span>
            <component :is="icons.UserIcon" class="size-5" />
          </button>
        </div>
      </div>

     
    </div>


    <div v-if="isPrazoModalVisible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md m-4">
        <h3 class="text-lg font-bold text-gray-800">Definir Prazo para {{ prazoGroup?.toUpperCase() }}</h3>
        <p class="text-sm text-gray-600 mt-2">Selecione a data limite para o preenchimento dos formulários de {{
          prazoGroup }} para o ano de {{ selectedYear }}.</p>
        <div class="my-4">
          <label for="prazo-date-inicio" class="block font-medium text-sm text-gray-700 mb-1">Data de início:</label>
          <input type="date" id="prazo-date-inicio" v-model="prazoDateInicio"
            :min="new Date().toISOString().substring(0,10)"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>
        <div class="my-4">
          <label for="prazo-date-fim" class="block font-medium text-sm text-gray-700 mb-1">Data de encerramento:</label>
          <input type="date" id="prazo-date-fim" v-model="prazoDateFim"
            :min="(prazoDateInicio || new Date().toISOString().substring(0,10))"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="isPrazoModalVisible = false" class="btn btn-gray">Cancelar</button>
          <button @click="handleSetPrazo" :disabled="!prazoDateInicio || !prazoDateFim || isSavingPrazo" class="btn btn-blue disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <span v-if="!isSavingPrazo">Salvar Prazo</span>
            <span v-else class="flex items-center gap-2">
              <icons.LoaderCircleIcon class="size-4 animate-spin" /> Salvando...
            </span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="isPreviewModalVisible"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm p-4"
      @click.self="closePreviewModal">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center p-4 border-b">
          <h3 class="text-lg font-bold text-gray-800">Pré-visualização do Upload</h3>
          <button @click="closePreviewModal" class="p-1 rounded-full hover:bg-gray-200">
            <icons.XIcon class="size-5 text-gray-600" />
          </button>
        </div>

        <div class="p-6 overflow-y-auto flex-grow">
          <p class="text-sm text-gray-600 mb-4">Arquivo selecionado: <strong class="font-medium text-gray-900">{{
            fileName }}</strong></p>

          <div v-if="isProcessing" class="flex flex-col items-center justify-center text-center p-8 gap-4">
            <icons.LoaderCircleIcon class="size-8 animate-spin text-indigo-600" />
            <p class="text-gray-600">Processando o arquivo, por favor aguarde...</p>
          </div>

          <div v-else class="flex flex-col gap-6">
            <div v-if="uploadSummary">
              <h4 class="font-semibold text-gray-700 mb-2">Resumo da Análise</h4>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div class="flex flex-col items-center justify-center p-4 bg-blue-50 border border-blue-200 rounded-lg">
                  <span class="text-2xl font-bold text-blue-600">{{ uploadSummary.new }}</span><span
                    class="text-xs text-blue-500">Novos</span>
                </div>
                <div
                  class="flex flex-col items-center justify-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                  <span class="text-2xl font-bold text-yellow-600">{{ uploadSummary.updated }}</span><span
                    class="text-xs text-yellow-500">Atualizados</span>
                </div>
                <div
                  class="flex flex-col items-center justify-center p-4 bg-gray-100 border border-gray-200 rounded-lg">
                  <span class="text-2xl font-bold text-gray-600">{{ uploadSummary.unchanged }}</span><span
                    class="text-xs text-gray-500">Inalterados</span>
                </div>
                <div class="flex flex-col items-center justify-center p-4 bg-red-50 border border-red-200 rounded-lg">
                  <span class="text-2xl font-bold text-red-600">{{ uploadSummary.errors }}</span><span
                    class="text-xs text-red-500">Com Erros</span>
                </div>
              </div>
            </div>

            <div v-if="uploadErrors && uploadErrors.length > 0">
              <h4 class="font-semibold text-gray-700 mb-2">Erros Encontrados</h4>
              <div
                class="max-h-32 overflow-y-auto bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm space-y-1">
                <p v-for="(error, index) in uploadErrors" :key="index">{{ error }}</p>
              </div>
            </div>

            <div v-if="uploadDetails && uploadDetails.length > 0">
              <h4 class="font-semibold text-gray-700 mb-2">Dados a Serem Importados/Atualizados</h4>
              <div class="max-h-64 overflow-y-auto space-y-3 p-3 bg-gray-50 rounded-lg border">
                <div v-for="(detail, index) in uploadDetails" :key="index" class="text-sm">
                  <p class="font-semibold">
                    <span v-if="detail.status === 'new'"
                      class="inline-block align-middle text-xs py-0.5 px-2 rounded-full bg-blue-100 text-blue-700 font-bold">[NOVO]</span>
                    <span v-if="detail.status === 'updated'"
                      class="inline-block align-middle text-xs py-0.5 px-2 rounded-full bg-yellow-100 text-yellow-800 font-bold">[ATUALIZADO]</span>
                    <span class="text-gray-800 ml-2">{{ detail.name }}</span>
                    <span class="text-gray-500 text-xs ml-1">(Matrícula: {{ detail.registration_number }})</span>
                  </p>
                  <ul v-if="detail.status === 'updated' && detail.changes && Object.keys(detail.changes).length > 0"
                    class="text-xs list-disc pl-8 mt-1.5 text-gray-600 space-y-1">
                    <li v-for="(change, field) in detail.changes" :key="field">
                      <strong class="capitalize font-medium">{{ String(field).replace(/_/g, ' ') }}:</strong>
                      de <code class="bg-red-100 text-red-800 px-1.5 py-0.5 rounded">'{{ change.from }}'</code>
                      para <code class="bg-green-100 text-green-800 px-1.5 py-0.5 rounded">'{{ change.to }}'</code>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="flex justify-end gap-3 p-4 border-t bg-gray-50 rounded-b-2xl">
          <button @click="closePreviewModal" class="btn btn-gray">Cancelar</button>
          <button @click="handleConfirmUpload" class="btn btn-green"
            :disabled="isProcessing || (uploadErrors && uploadErrors.length > 0)">
            <span v-if="!isProcessing">Confirmar Upload</span>
            <span v-else>
              <icons.LoaderCircleIcon class="size-5 animate-spin mr-2" />
              Processando...
            </span>
          </button>
        </div>
        <p v-if="uploadErrors && uploadErrors.length > 0 && !isProcessing"
          class="px-6 pb-2 -mt-2 text-xs text-red-600 text-right">
          O upload só será liberado após a correção dos erros no arquivo.
        </p>
      </div>
    </div>
    <Dialog :open="isLiberarModalVisible" @update:open="isLiberarModalVisible = false">
      <DialogContent class="sm:max-w-md bg-white">
        <DialogHeader>
          <DialogTitle class="text-lg font-semibold text-gray-900">{{ liberarModalTitle }}</DialogTitle>
          <DialogDescription class="mt-2 text-sm text-gray-600">
            {{ liberarModalDescription }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
          <DialogClose as-child>
            <button type="button"
              class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-red-400">
              {{ isConfirmationDialog ? 'Cancelar' : 'Fechar' }}
            </button>
          </DialogClose>

          <button v-if="isConfirmationDialog" @click="confirmAndLiberar" type="button"
            class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-green-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-500">
            Sim, Liberar
          </button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </DashboardLayout>
</template>

<style>
.input-date {
  border: 1px solid #ccc;
  padding: 8px;
  border-radius: 5px;
}

.input-day {
  border: 1px solid #ccc;
  padding: 8px;
  border-radius: 5px;
  width: 105px;
}

.date-range-inputs {
  display: flex;
  align-items: center;
  gap: 5px;
}
</style>