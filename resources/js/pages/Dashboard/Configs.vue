<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import axios from 'axios';
import { route } from 'ziggy-js';
// 1. Importe o useFlashModal
import { useFlashModal } from '@/composables/useFlashModal';

// --- DEFINIÇÃO DAS PROPS ---
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
  existingYears: Array<string | number>;
}>();

// 2. Inicialize o composable para ter acesso à função que exibe o modal
const { showFlashModal } = useFlashModal();

// --- ESTADO DA PÁGINA ---
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });
const selectedYear = ref(String(new Date().getFullYear()));

// --- ESTADO DO MODAL DE PRAZO ---
const isPrazoModalVisible = ref(false);
const prazoGroup = ref<'avaliacao' | 'pdi' | null>(null);
const prazoDateInicio = ref('');
const prazoDateFim = ref('');

// --- ESTADO PARA UPLOAD DE CSV DE PESSOAS ---
const isPreviewModalVisible = ref(false);
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

const isAvaliacaoGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type)).some(form => form.release));
const isPdiGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['pactuacao'].includes(form.type)).some(form => form.release));

const avaliacaoReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

const pdiReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['pactuacao'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

const avaliacaoPrazoInfo = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type) && form.term_first && form.term_end);
  if (!f) return null;
  return {
    inicio: new Date(f.term_first!).toLocaleDateString('pt-BR'),
    fim: new Date(f.term_end!).toLocaleDateString('pt-BR'),
  };
});

const pdiPrazoInfo = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['pactuacao'].includes(form.type) && form.term_first && form.term_end);
  if (!f) return null;
  return {
    inicio: new Date(f.term_first!).toLocaleDateString('pt-BR'),
    fim: new Date(f.term_end!).toLocaleDateString('pt-BR'),
  };
});

// --- MÉTODOS DE AÇÃO - FORMULÁRIOS ---
const getFormForType = (type: string) => props.forms[`${selectedYear.value}_${type}`] || null;

function openPrazoModal(group: 'avaliacao' | 'pdi') {
  prazoGroup.value = group;
  const formTypes = group === 'avaliacao' ? ['autoavaliacao', 'servidor', 'chefia'] : ['pactuacao'];
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

function handleSetPrazo() {
  if (!prazoGroup.value || !prazoDateInicio.value || !prazoDateFim.value) {
    // Substituído
    showFlashModal('error', 'Por favor, preencha as datas de início e encerramento.');
    return;
  }
  router.post(route('configs.prazo.store'), {
    year: selectedYear.value,
    group: prazoGroup.value,
    term_first: prazoDateInicio.value,
    term_end: prazoDateFim.value,
  }, {
    onSuccess: () => { isPrazoModalVisible.value = false; },
    preserveScroll: true,
  });
}

function handleLiberar(group: 'avaliacao' | 'pdi') {
  if (confirm(`Tem certeza que deseja LIBERAR os formulários de ${group.toUpperCase()} para ${selectedYear.value}?`)) {
    router.post(route('configs.liberar.store'), { year: selectedYear.value, group: group }, { preserveScroll: true });
  }
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
    // Substituído
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
    // Substituído
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
        alert("Nenhum arquivo temporário encontrado para confirmar.");
        return;
    }
    isProcessing.value = true;
    try {
        const response = await axios.post(route('persons.confirm'), {
            temp_file_path: tempFilePath.value
        });
        alert(response.data.message);
        closePreviewModal();
        router.reload({ preserveScroll: true });
    } catch (error: any) {
         alert('Erro ao confirmar o upload: ' + (error.response?.data?.message || error.message));
    } finally {
        isProcessing.value = false;
    }
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
      
      <!-- Seção de Formulários -->
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
            <label>Formulário de Autoavaliação:</label>
            <div class="button-group">
              <button v-if="getFormForType('autoavaliacao')" @click="handleView(getFormForType('autoavaliacao').id)"
                class="btn btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('autoavaliacao') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('autoavaliacao').id)" class="btn btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('autoavaliacao')" @click="handleCreate('autoavaliacao')" class="btn btn-create">
                <span>Criar</span> <component :is="icons.PlusIcon" class="size-5" />
              </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Avaliação do Servidor:</label>
            <div class="button-group">
                <button v-if="getFormForType('servidor')" @click="handleView(getFormForType('servidor').id)" class="btn btn-blue">
                    <span>Visualizar</span> <component :is="icons.EyeIcon" class="size-5" />
                </button>
                <button v-if="getFormForType('servidor') && !isAvaliacaoGroupReleased" @click="handleEdit(getFormForType('servidor').id)" class="btn btn-yellow">
                    <span>Editar</span> <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('servidor')" @click="handleCreate('servidor')" class="btn btn-create">
                    <span>Criar</span> <component :is="icons.PlusIcon" class="size-5" />
                </button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Avaliação da Chefia:</label>
            <div class="button-group">
                 <button v-if="getFormForType('chefia')" @click="handleView(getFormForType('chefia').id)" class="btn btn-blue">
                    <span>Visualizar</span> <component :is="icons.EyeIcon" class="size-5" />
                </button>
                <button v-if="getFormForType('chefia') && !isAvaliacaoGroupReleased" @click="handleEdit(getFormForType('chefia').id)" class="btn btn-yellow">
                    <span>Editar</span> <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('chefia')" @click="handleCreate('chefia')" class="btn btn-create">
                    <span>Criar</span> <component :is="icons.PlusIcon" class="size-5" />
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
            <div v-else class="flex flex-wrap items-center gap-x-6 gap-y-2">
              <div v-if="avaliacaoPrazoInfo" class="text-orange-600 font-semibold flex items-center gap-2">
                <component :is="icons.ClockIcon" class="size-5" />
                Período: {{ avaliacaoPrazoInfo.inicio }} a {{ avaliacaoPrazoInfo.fim }}
              </div>
              <div class=" flex flex-wrap text-green-600 font-semibold flex items-center gap-2">
                <component :is="icons.CheckCircle2Icon" class="size-5" />
                Liberado em: {{ avaliacaoReleaseData }}
              </div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
          <div class="setting-item">
            <label>Formulário de Pactuação PDI:</label>
            <div class="button-group">
                <button v-if="getFormForType('pactuacao')" @click="handleView(getFormForType('pactuacao').id)" class="btn btn-blue">
                    <span>Visualizar</span> <component :is="icons.EyeIcon" class="size-5" />
                </button>
                <button v-if="getFormForType('pactuacao') && !isPdiGroupReleased" @click="handleEdit(getFormForType('pactuacao').id)" class="btn btn-yellow">
                    <span>Editar</span> <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('pactuacao')" @click="handleCreate('pactuacao')" class="btn btn-create">
                    <span>Criar</span> <component :is="icons.PlusIcon" class="size-5" />
                </button>
            </div>
          </div>
         <div class="setting-item border-t pt-4 mt-4">
            <label>Ações do PDI:</label>
            <div v-if="!isPdiGroupReleased" class="button-group">
              <button class="btn btn-orange" @click="openPrazoModal('pdi')">
                <span>Prazo</span> <component :is="icons.ClockIcon" class="size-5" />
              </button>
              <button class="btn btn-pine" @click="handleLiberar('pdi')">
                <span>Liberar</span> <component :is="icons.SendIcon" class="size-5" />
              </button>
            </div>
            <div v-else class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div v-if="pdiPrazoInfo" class="text-orange-600 font-semibold flex items-center gap-2">
                    <component :is="icons.ClockIcon" class="size-5" />
                    <span>Prazo: {{ pdiPrazoInfo.inicio }} a {{ pdiPrazoInfo.fim }}</span>
                </div>
                <div class="text-green-600 font-semibold flex items-center gap-2">
                    <component :is="icons.CheckCircle2Icon" class="size-5" />
                    <span>Liberado em: {{ pdiReleaseData }}</span>
                </div>
            </div>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <h3>Gestão de Pessoas</h3>
        <div class="setting-item">
          <label>Upload de Planilha de Pessoas:</label>
          <button @click="triggerFileInput" class="btn btn-blue">
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
          <label>Organograma:</label>
          <button @click="router.get(route('organizational-chart.index'))" class="btn btn-yellow">
            <span>Ver</span>
            <component :is="icons.ChartArea" class="size-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Prazo -->
    <div v-if="isPrazoModalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md m-4">
        <h3 class="text-lg font-bold text-gray-800">Definir Prazo para {{ prazoGroup?.toUpperCase() }}</h3>
        <p class="text-sm text-gray-600 mt-2">Selecione a data limite para o preenchimento dos formulários de {{
          prazoGroup }} para o ano de {{ selectedYear }}.</p>
        <div class="my-4">
          <label for="prazo-date-inicio" class="block font-medium text-sm text-gray-700 mb-1">Data de início:</label>
          <input type="date" id="prazo-date-inicio" v-model="prazoDateInicio"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>
        <div class="my-4">
          <label for="prazo-date-fim" class="block font-medium text-sm text-gray-700 mb-1">Data de encerramento:</label>
          <input type="date" id="prazo-date-fim" v-model="prazoDateFim"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="isPrazoModalVisible = false" class="btn btn-gray">Cancelar</button>
          <button @click="handleSetPrazo" class="btn btn-blue">Salvar Prazo</button>
        </div>
      </div>
    </div>
    
    <!-- MODAL DE PREVIEW DO UPLOAD -->
    <div v-if="isPreviewModalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm p-4" @click.self="closePreviewModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="text-lg font-bold text-gray-800">Pré-visualização do Upload</h3>
                <button @click="closePreviewModal" class="p-1 rounded-full hover:bg-gray-200">
                    <icons.XIcon class="size-5 text-gray-600"/>
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
                    <!-- Resumo -->
                    <div v-if="uploadSummary">
                        <h4 class="font-semibold text-gray-700 mb-2">Resumo da Análise</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div class="flex flex-col items-center justify-center p-4 bg-blue-50 border border-blue-200 rounded-lg"><span class="text-2xl font-bold text-blue-600">{{ uploadSummary.new }}</span><span class="text-xs text-blue-500">Novos</span></div>
                            <div class="flex flex-col items-center justify-center p-4 bg-yellow-50 border border-yellow-200 rounded-lg"><span class="text-2xl font-bold text-yellow-600">{{ uploadSummary.updated }}</span><span class="text-xs text-yellow-500">Atualizados</span></div>
                            <div class="flex flex-col items-center justify-center p-4 bg-gray-100 border border-gray-200 rounded-lg"><span class="text-2xl font-bold text-gray-600">{{ uploadSummary.unchanged }}</span><span class="text-xs text-gray-500">Inalterados</span></div>
                            <div class="flex flex-col items-center justify-center p-4 bg-red-50 border border-red-200 rounded-lg"><span class="text-2xl font-bold text-red-600">{{ uploadSummary.errors }}</span><span class="text-xs text-red-500">Com Erros</span></div>
                        </div>
                    </div>

                    <!-- Erros -->
                    <div v-if="uploadErrors && uploadErrors.length > 0">
                         <h4 class="font-semibold text-gray-700 mb-2">Erros Encontrados</h4>
                        <div class="max-h-32 overflow-y-auto bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm space-y-1">
                            <p v-for="(error, index) in uploadErrors" :key="index">{{ error }}</p>
                        </div>
                    </div>

                    <!-- Detalhes dos Dados a serem Importados/Atualizados -->
                    <div v-if="uploadDetails && uploadDetails.length > 0">
                        <h4 class="font-semibold text-gray-700 mb-2">Dados a Serem Importados/Atualizados</h4>
                        <div class="max-h-64 overflow-y-auto space-y-3 p-3 bg-gray-50 rounded-lg border">
                            <div v-for="(detail, index) in uploadDetails" :key="index" class="text-sm">
                                <p class="font-semibold">
                                    <span v-if="detail.status === 'new'" class="inline-block align-middle text-xs py-0.5 px-2 rounded-full bg-blue-100 text-blue-700 font-bold">[NOVO]</span>
                                    <span v-if="detail.status === 'updated'" class="inline-block align-middle text-xs py-0.5 px-2 rounded-full bg-yellow-100 text-yellow-800 font-bold">[ATUALIZADO]</span>
                                    <span class="text-gray-800 ml-2">{{ detail.name }}</span>
                                    <span class="text-gray-500 text-xs ml-1">(Matrícula: {{ detail.registration_number }})</span>
                                </p>
                                <ul v-if="detail.status === 'updated' && detail.changes && Object.keys(detail.changes).length > 0" class="text-xs list-disc pl-8 mt-1.5 text-gray-600 space-y-1">
                                    <li v-for="(change, field) in detail.changes" :key="field">
                                        <strong class="capitalize font-medium">{{ field.replace(/_/g, ' ') }}:</strong> 
                                        de <code class="bg-red-100 text-red-800 px-1.5 py-0.5 rounded">'{{ change.from }}'</code>
                                        para <code class="bg-green-100 text-green-800 px-1.5 py-0.5 rounded">'{{ change.to }}'</code>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

          </div>
        </div>

            <!-- Footer do Modal -->
            <div class="flex justify-end gap-3 p-4 border-t bg-gray-50 rounded-b-2xl">
                <button @click="closePreviewModal" class="btn btn-gray">Cancelar</button>
                <button @click="handleConfirmUpload" class="btn btn-create" :disabled="isProcessing || (uploadErrors && uploadErrors.length > 0)">
                    <span v-if="!isProcessing">Confirmar Upload</span>
                    <span v-else>
                        <icons.LoaderCircleIcon class="size-5 animate-spin mr-2"/>
                        Processando...
                    </span>
                </button>
            </div>
             <p v-if="uploadErrors && uploadErrors.length > 0 && !isProcessing" class="px-6 pb-2 -mt-2 text-xs text-red-600 text-right">
                O upload só será liberado após a correção dos erros no arquivo.
            </p>
        </div>
    </div>

  </DashboardLayout>
</template>