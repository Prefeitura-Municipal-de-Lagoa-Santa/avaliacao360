<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { route } from 'ziggy-js';

const props = defineProps<{
  recourse: {
    id: number;
    text: string;
    status: string;
    current_instance?: 'RH' | 'Comissao';
    stage?: string | null;
    response: string | null;
    dgp?: { decision?: string | null; decided_at?: string | null; notes?: string | null };
    second_instance?: { enabled: boolean; requested_at?: string | null; text?: string | null };
    secretary?: { decision?: string | null; decided_at?: string | null; notes?: string | null };
  last_return?: { by: string; to: 'RH' | 'Comissao' | null; at: string | null } | null;
    actions?: {
      canForwardToCommission?: boolean;
      forwardToCommissionDisabledReason?: string | null;
      canForwardToDgp?: boolean;
      canDgpDecide?: boolean;
      canDgpReturnToCommission?: boolean;
      canRhFinalizeFirst?: boolean;
      canForwardToSecretary?: boolean;
      canSecretaryDecide?: boolean;
      canRhFinalizeSecond?: boolean;
    };
    attachments: Array<{ name: string; url: string }>;
    responseAttachments?: Array<{ name: string; url: string }>;
    responsiblePersons: Array<{ id: number; name: string; registration_number: string }>;
    person: { name: string };
    evaluation: {
      id: number;
      year: string;
      type: string;
      form_name: string;
      avaliado: string;
      answers: Array<{ question: string; score: number | null }>;
      is_chef_evaluation: boolean;
      original_evaluation_type: string;
    };
  logs: Array<{ status: string; message: string | null; created_at: string }>;
  };
  availablePersons: Array<{ id: number; name: string; registration_number: string }>;
  canManageAssignees: boolean;
  canDecideNow?: boolean;
  userRole?: 'RH' | 'Comissão' | 'Sem permissão';
  permissions?: { isRH?: boolean; isComissao?: boolean };
}>();

// Mapeia valores internos de etapa para rótulos em português
const stageLabels: Record<string, string> = {
  rh_analysis: 'Análise do RH',
  commission_analysis: 'Análise da Comissão',
  dgp_review: 'Revisão da DGP',
  await_first_ack: 'Aguardando ciência (1ª instância)',
  secretary_review: 'Decisão do Secretário',
  rh_finalize_second: 'Finalização do RH (2ª instância)',
  await_second_ack: 'Aguardando ciência (2ª instância)',
  completed: 'Concluído',
};

function formatStageLabel(stage?: string | null): string {
  if (!stage) return '—';
  return stageLabels[stage] ?? stage.replaceAll('_', ' ').toUpperCase();
}

function formatInstance(instance?: string | null): string {
  if (!instance) return '—';
  if (instance === 'Comissao') return 'Comissão';
  return instance;
}

const response = ref('');
const decision = ref<'respondido' | 'indeferido' | null>(null);
const dgpNotes = ref('');
const responseAttachments = ref<File[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
// DGP decisão: anexos
const dgpDecisionAttachments = ref<File[]>([]);
const dgpDecisionFileInput = ref<HTMLInputElement | null>(null);
// Secretary decision: notes and attachments
const secretaryNotes = ref('');
const secretaryDecisionAttachments = ref<File[]>([]);
const secretaryDecisionFileInput = ref<HTMLInputElement | null>(null);
const isAnalyzing = ref(props.recourse.status === 'em_analise');
const canDecideNow = ref(props.canDecideNow ?? true);
const showReturnModal = ref(false);
const showForwardModal = ref(false);
const forwardMessage = ref('');
const showDgpReturnModal = ref(false);
const dgpReturnMessage = ref('');
const returnMessage = ref('');

// Arquivos para transições de etapas
const forwardAttachments = ref<File[]>([]); // RH -> Comissão (reenvio)
const dgpReturnAttachments = ref<File[]>([]); // DGP -> Comissão (devolução)
const returnAttachments = ref<File[]>([]); // Comissão -> RH (devolução)
// Refs dos inputs de arquivo (ocultos)
const forwardFileInput = ref<HTMLInputElement | null>(null);
const dgpReturnFileInput = ref<HTMLInputElement | null>(null);
const returnFileInput = ref<HTMLInputElement | null>(null);

// Estados para gerenciar responsáveis
const selectedPersonId = ref<number | null>(null);
const showAssignForm = ref(false);

// Estados para modal de confirmação
const showRemoveModal = ref(false);
const responsibleToRemove = ref<{ id: number; name: string; registration_number: string } | null>(null);

function markAsAnalyzing() {
  if (!canDecideNow.value) return;
  router.post(route('recourses.markAnalyzing', props.recourse.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      isAnalyzing.value = true;
    },
  });
}

function openFile(file: { name: string; url: string }) {
  if (!isAnalyzing.value && canDecideNow.value) markAsAnalyzing();

  const link = document.createElement('a');
  link.href = file.url;
  link.download = file.name;
  link.target = '_blank';
  link.click();
}

function triggerFileInput() {
  fileInput.value?.click();
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement;
  const files = target.files;
  if (files) {
    responseAttachments.value.push(...Array.from(files));
  }
}

function removeAttachment(index: number) {
  responseAttachments.value.splice(index, 1);
}

function submitAnalysis() {
  if (!decision.value || !response.value.trim()) {
    alert('Informe o parecer e selecione o tipo de decisão.');
    return;
  }
  if (!canDecideNow.value) {
    alert('A decisão não pode ser tomada nesta instância. Aguarde o processo retornar para sua instância.');
    return;
  }

  const formData = new FormData();
  formData.append('status', decision.value);
  formData.append('response', response.value);
  
  // Adiciona os anexos de resposta
  responseAttachments.value.forEach((file, index) => {
    formData.append(`response_attachments[${index}]`, file);
  });

  router.post(route('recourses.respond', props.recourse.id), formData, {
    forceFormData: true,
    onSuccess: () => {
      // Após salvar o parecer, voltar para a lista de recursos abertos
      router.get(route('recourses.index'));
    },
  });
}

// Removido: definição antiga sem anexos do returnToPreviousInstance

function handleForwardToCommission() {
  if (!props.recourse.actions?.canForwardToCommission) return;
  // Se houve devolução anterior, exigir justificativa via modal
  if (props.recourse.last_return) {
    showForwardModal.value = true;
    return;
  }
  router.post(route('recourses.forwardToCommission', props.recourse.id));
}

function confirmForwardToCommission() {
  if (!forwardMessage.value.trim()) return;
  const formData = new FormData();
  formData.append('message', forwardMessage.value);
  forwardAttachments.value.forEach((file, index) => formData.append(`forward_attachments[${index}]`, file));
  router.post(route('recourses.forwardToCommission', props.recourse.id), formData, {
    forceFormData: true,
    onSuccess: () => {
      showForwardModal.value = false;
      forwardMessage.value = '';
      forwardAttachments.value = [];
    }
  });
}

function assignResponsible() {
  if (!selectedPersonId.value) {
    alert('Selecione uma pessoa para atribuir como responsável.');
    return;
  }

  router.post(route('recourses.assignResponsible', props.recourse.id), {
    person_id: selectedPersonId.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedPersonId.value = null;
      showAssignForm.value = false;
    },
  });
}

function removeResponsible(personId: number) {
  const responsible = props.recourse.responsiblePersons.find(r => r.id === personId);
  if (responsible) {
    responsibleToRemove.value = responsible;
    showRemoveModal.value = true;
  }
}

function confirmRemoveResponsible() {
  if (!responsibleToRemove.value) return;

  router.delete(route('recourses.removeResponsible', props.recourse.id), {
    data: { person_id: responsibleToRemove.value.id },
    preserveScroll: true,
    onFinish: () => {
      showRemoveModal.value = false;
      responsibleToRemove.value = null;
    },
  });
}

function cancelRemoveResponsible() {
  showRemoveModal.value = false;
  responsibleToRemove.value = null;
}

function goBack() {
  // Sempre voltar para a lista de recursos abertos
  router.get(route('recourses.index'));
}

function handleForwardFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    forwardAttachments.value.push(...Array.from(files));
    (event.target as HTMLInputElement).value = '';
  }
}

function handleDgpReturnFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    dgpReturnAttachments.value.push(...Array.from(files));
    (event.target as HTMLInputElement).value = '';
  }
}

function handleReturnFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    returnAttachments.value.push(...Array.from(files));
    (event.target as HTMLInputElement).value = '';
  }
}

function triggerForwardFileInput() { forwardFileInput.value?.click(); }
function triggerDgpReturnFileInput() { dgpReturnFileInput.value?.click(); }
function triggerReturnFileInput() { returnFileInput.value?.click(); }
function triggerDgpDecisionFileInput() { dgpDecisionFileInput.value?.click(); }
function triggerSecretaryDecisionFileInput() { secretaryDecisionFileInput.value?.click(); }

function removeForwardAttachment(i: number) { forwardAttachments.value.splice(i, 1); }
function removeDgpReturnAttachment(i: number) { dgpReturnAttachments.value.splice(i, 1); }
function removeReturnAttachment(i: number) { returnAttachments.value.splice(i, 1); }
function removeDgpDecisionAttachment(i: number) { dgpDecisionAttachments.value.splice(i, 1); }
function removeSecretaryDecisionAttachment(i: number) { secretaryDecisionAttachments.value.splice(i, 1); }

function handleDgpDecisionFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    dgpDecisionAttachments.value.push(...Array.from(files));
    (event.target as HTMLInputElement).value = '';
  }
}
function handleSecretaryDecisionFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    secretaryDecisionAttachments.value.push(...Array.from(files));
    (event.target as HTMLInputElement).value = '';
  }
}

function confirmDgpReturn() {
  if (!dgpReturnMessage.value.trim()) return;
  const formData = new FormData();
  formData.append('message', dgpReturnMessage.value);
  dgpReturnAttachments.value.forEach((file, index) => formData.append(`return_attachments[${index}]`, file));
  router.post(route('recourses.dgpReturnToCommission', props.recourse.id), formData, {
    forceFormData: true,
    onSuccess: () => {
      showDgpReturnModal.value = false;
      dgpReturnMessage.value = '';
      dgpReturnAttachments.value = [];
    }
  });
}

// Enviar devolução da Comissão ao RH com anexos
function returnToPreviousInstance() {
  if (!returnMessage.value.trim()) {
    alert('Descreva uma justificativa para a devolução.');
    return;
  }
  const formData = new FormData();
  formData.append('message', returnMessage.value);
  returnAttachments.value.forEach((file, index) => formData.append(`return_attachments[${index}]`, file));
  router.post(route('recourses.return', props.recourse.id), formData, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => {
      showReturnModal.value = false;
      returnMessage.value = '';
      returnAttachments.value = [];
    }
  });
}

// Envio DGP decisão com anexos via FormData
function submitDgpDecision(decision: 'homologado' | 'nao_homologado') {
  const fd = new FormData();
  fd.append('decision', decision);
  if (dgpNotes.value) fd.append('notes', dgpNotes.value);
  dgpDecisionAttachments.value.forEach((f, idx) => fd.append(`dgp_decision_attachments[${idx}]`, f));
  router.post(route('recourses.dgpDecision', props.recourse.id), fd, { forceFormData: true });
}
function submitSecretaryDecision(decision: 'homologado' | 'nao_homologado') {
  const fd = new FormData();
  fd.append('decision', decision);
  if (secretaryNotes.value) fd.append('notes', secretaryNotes.value);
  secretaryDecisionAttachments.value.forEach((f, idx) => fd.append(`secretary_decision_attachments[${idx}]`, f));
  router.post(route('recourses.secretaryDecision', props.recourse.id), fd, { forceFormData: true });
}

// Compat: botões do template utilizam rótulos deferido/indeferido
function directorDecision(decision: 'deferido' | 'indeferido') {
  const map = { deferido: 'homologado', indeferido: 'nao_homologado' } as const;
  submitDgpDecision(map[decision]);
}
function secretaryDecision(decision: 'deferido' | 'indeferido') {
  const map = { deferido: 'homologado', indeferido: 'nao_homologado' } as const;
  submitSecretaryDecision(map[decision]);
}
function escalateToSecretary() {
  router.post(route('recourses.forwardToSecretary', props.recourse.id));
}
function returnToPrevious() {
  showReturnModal.value = true;
}
</script>

<template>
  <Head title="Revisar Recurso" />
  <DashboardLayout page-title="Revisar Recurso">
    <div class="max-w-6xl mx-auto space-y-6">
      <!-- Cabeçalho Principal -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <!-- Cabeçalho com título à esquerda e Voltar à direita -->
        <div class="flex items-center justify-between mb-3 gap-3">
          <h1 class="text-2xl font-bold text-gray-900">Análise de Recurso de Avaliação</h1>
          <button @click="goBack" class="back-btn inline-flex items-center whitespace-nowrap">
            <icons.ArrowLeftIcon class="size-4 mr-2" /> Voltar
          </button>
        </div>
        <div class="flex items-center gap-4 text-sm text-gray-600">
              <span class="flex items-center gap-1">
                <icons.User class="w-4 h-4" />
                <strong>Avaliado:</strong> {{ recourse.person.name }}
              </span>
              <span class="flex items-center gap-1">
                <icons.Calendar class="w-4 h-4" />
                <strong>Ano:</strong> {{ recourse.evaluation.year }}
              </span>
              <span class="flex items-center gap-1">
                <icons.FileText class="w-4 h-4" />
                <strong>Tipo:</strong> {{ recourse.evaluation.type }}
              </span>
              <span class="flex items-center gap-1">
                <icons.Building2 class="w-4 h-4" />
                <strong>Instância Atual:</strong> {{ formatInstance(recourse.current_instance) }}
              </span>
              <span v-if="recourse.stage" class="flex items-center gap-1">
                <icons.Flag class="w-4 h-4" />
                <strong>Etapa:</strong> {{ formatStageLabel(recourse.stage) }}
              </span>
        </div>
        <!-- Chips de etapa/status -->
        <div class="flex flex-wrap items-center gap-3 mt-2">
          <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700 border whitespace-nowrap">
            Etapa: {{ formatStageLabel(recourse.stage) }}
          </span>
          <span
            class="text-sm font-medium text-white px-4 py-2 rounded-full"
            :class="{
              'bg-gray-500': recourse.status === 'aberto',
              'bg-gray-600': recourse.status === 'em_analise',
              'bg-gray-700': recourse.status === 'respondido',
              'bg-gray-800': recourse.status === 'indeferido',
            }"
          >
            {{ recourse.status.replace('_', ' ').toUpperCase() }}
          </span>
        </div>
        </div>

        <!-- Info de última devolução -->
        <div v-if="recourse.last_return" class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded">
          <div class="flex items-start gap-2 text-amber-800">
            <icons.Reply class="w-4 h-4 mt-0.5" />
            <div class="text-sm">
              <p>
                Recurso devolvido para <strong>{{ recourse.last_return.to }}</strong> por <strong>{{ recourse.last_return.by }}</strong> em {{ recourse.last_return.at }}.
              </p>
            </div>
          </div>
        </div>
      

      <!-- Layout em Grid: Notas vs Recurso -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- CARD 1: Notas da Avaliação (Destaque) -->
        <div class="bg-white rounded-lg shadow-sm border">
          <div class="bg-gray-800 text-white p-4 rounded-t-lg">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.TrendingUp class="w-5 h-5" />
              {{ recourse.evaluation.is_chef_evaluation ? 'Notas do Chefe Imediato - Base do Recurso' : 'Notas da Avaliação Original' }}
            </h2>
            <p class="text-sm text-gray-300 mt-1">
              {{ recourse.evaluation.is_chef_evaluation 
                ? `Avaliação ${recourse.evaluation.type.toUpperCase()} - ${recourse.evaluation.form_name}` 
                : `Avaliação ${recourse.evaluation.original_evaluation_type.toUpperCase()} - ${recourse.evaluation.form_name} (Avaliação do chefe não encontrada)` 
              }}
            </p>
          </div>
          
          <div class="p-4 space-y-4">
            <!-- Resumo das Notas -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <icons.BarChart3 class="w-4 h-4" />
                {{ recourse.evaluation.is_chef_evaluation ? 'Notas Atribuídas pelo Chefe Imediato' : 'Notas da Avaliação Original' }}
              </h3>
              
              <!-- Aviso quando não é avaliação do chefe -->
              <div v-if="!recourse.evaluation.is_chef_evaluation" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start gap-2">
                  <icons.AlertTriangle class="w-4 h-4 text-yellow-600 mt-0.5 flex-shrink-0" />
                  <div class="text-sm text-yellow-800">
                    <p class="font-medium">Avaliação do chefe não encontrada</p>
                    <p>Exibindo a avaliação original ({{ recourse.evaluation.original_evaluation_type }}) como referência.</p>
                  </div>
                </div>
              </div>
              
              <div class="space-y-2">
                <div v-for="(answer, index) in recourse.evaluation.answers" :key="index" 
                     class="flex justify-between items-center py-2 px-3 bg-white rounded border">
                  <span class="text-sm font-medium text-gray-700">{{ answer.question }}</span>
                  <span class="text-lg font-bold" 
                        :class="answer.score !== null ? 'text-gray-800' : 'text-gray-400'">
                    {{ answer.score ?? '—' }}
                  </span>
                </div>
              </div>
              
              <!-- Cálculo da média -->
              <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex justify-between items-center">
                  <span class="font-semibold text-gray-800">Média Geral:</span>
                  <span class="text-xl font-bold text-gray-800">
                    {{ 
                      recourse.evaluation.answers.filter(a => a.score !== null).length > 0 
                        ? (recourse.evaluation.answers.reduce((sum, a) => sum + (a.score || 0), 0) / 
                           recourse.evaluation.answers.filter(a => a.score !== null).length).toFixed(1)
                        : '—'
                    }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Link para ver detalhes completos -->
            <div class="text-center">
              <a
                :href="route('recourses.personEvaluations', recourse.id)"
                class="inline-flex items-center px-4 py-2 bg-gray-700 text-white text-sm rounded-lg hover:bg-gray-800 transition-colors"
              >
                <icons.ExternalLink class="w-4 h-4 mr-2" />
                Análise Completa das Avaliações
              </a>
            </div>
          </div>
        </div>

        <!-- CARD 2: Recurso do Funcionário -->
        <div class="bg-white rounded-lg shadow-sm border">
          <div class="bg-gray-700 text-white p-4 rounded-t-lg">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.MessageSquare class="w-5 h-5" />
              Recurso Apresentado
            </h2>
            <p class="text-sm text-gray-300 mt-1">
              Solicitação de revisão das notas atribuídas
            </p>
          </div>
          
          <div class="p-4 space-y-4">
            <!-- Texto do recurso -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-2 flex items-center gap-2">
                <icons.FileText class="w-4 h-4" />
                Justificativa do Recurso
              </h3>
              <div class="bg-white border rounded p-3 max-h-48 overflow-y-auto">
                <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ recourse.text }}</p>
              </div>
            </div>

            <!-- Anexos do recurso -->
            <div v-if="recourse.attachments.length" class="bg-gray-50 border rounded-lg p-4">
              <h3 class="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                <icons.Paperclip class="w-4 h-4" />
                Documentos Anexados
              </h3>
              <div class="space-y-2">
                <div v-for="(file, index) in recourse.attachments" :key="index"
                     class="flex items-center gap-2 p-2 bg-white border rounded hover:bg-gray-50 cursor-pointer"
                     @click="openFile(file)">
                  <icons.File class="w-4 h-4 text-gray-500" />
                  <span class="text-sm text-gray-700 hover:underline">{{ file.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Gestão de Responsáveis (RH) -->
      <div v-if="canManageAssignees" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-600 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.Users class="w-5 h-5" />
            Comissão Responsável
          </h2>
          <p class="text-sm text-gray-300 mt-1">
            Gerenciar membros responsáveis pela análise do recurso
          </p>
        </div>
        
        <div class="p-4 space-y-4">
          <!-- Lista de responsáveis atuais -->
          <div v-if="recourse.responsiblePersons.length">
            <h3 class="font-medium text-gray-700 mb-3">Membros Atuais da Comissão</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div
                v-for="responsible in recourse.responsiblePersons"
                :key="responsible.id"
                class="flex items-center justify-between bg-gray-50 p-3 rounded-lg border border-gray-200"
              >
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 bg-gray-600 text-white rounded-full flex items-center justify-center text-sm font-medium">
                    {{ responsible.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ responsible.name }}</p>
                    <p class="text-xs text-gray-500">{{ responsible.registration_number }}</p>
                  </div>
                </div>
                <button
                  @click="removeResponsible(responsible.id)"
                  class="text-red-500 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors group"
                  type="button"
                  title="Remover membro da comissão"
                >
                  <icons.UserMinus class="w-4 h-4 group-hover:scale-110 transition-transform" />
                </button>
              </div>
            </div>
          </div>
          
          <div v-else class="text-center py-4">
            <icons.UserPlus class="w-8 h-8 text-gray-400 mx-auto mb-2" />
            <p class="text-sm text-gray-500">Nenhum membro atribuído à comissão ainda.</p>
          </div>

          <!-- Formulário para adicionar responsável -->
          <div class="border-t pt-4">
            <div v-if="!showAssignForm">
              <button
                @click="showAssignForm = true"
                class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors flex items-center justify-center gap-2"
              >
                <icons.UserPlus class="w-4 h-4" />
                Adicionar Membro à Comissão
              </button>
            </div>

            <div v-else class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Selecionar Novo Membro
                </label>
                <select
                  v-model="selectedPersonId"
                  class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                >
                  <option value="" disabled>Escolha uma pessoa...</option>
                  <option
                    v-for="person in availablePersons.filter(p => !recourse.responsiblePersons.some(r => r.id === p.id))"
                    :key="person.id"
                    :value="person.id"
                  >
                    {{ person.registration_number }} - {{ person.name }}
                  </option>
                </select>
              </div>
              <div class="flex gap-2">
                <button
                  @click="assignResponsible"
                  class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                  :disabled="!selectedPersonId"
                >
                  Atribuir
                </button>
                <button
                  @click="showAssignForm = false; selectedPersonId = null"
                  class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm font-medium"
                >
                  Cancelar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ação RH: encaminhar para Comissão -->
      <div v-if="userRole === 'RH' && recourse.current_instance === 'RH'" class="bg-white rounded-lg shadow-sm border p-4">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700 flex items-center gap-2">
            <icons.Send class="w-4 h-4" />
            <span>Encaminhar o recurso para a Comissão iniciar a análise.</span>
          </div>
          <button
            class="px-4 py-2 rounded transition-colors text-sm flex items-center gap-2"
            :class="recourse.actions?.canForwardToCommission ? 'bg-gray-700 text-white hover:bg-gray-800' : 'bg-gray-300 text-gray-600 cursor-not-allowed'"
            type="button"
            :disabled="!recourse.actions?.canForwardToCommission"
            @click="handleForwardToCommission"
          >
            <icons.Send class="w-4 h-4" />
            Encaminhar para Comissão
          </button>
        </div>
        <p class="text-xs mt-2" :class="recourse.actions?.canForwardToCommission ? 'text-gray-500' : 'text-amber-700'">
          {{ recourse.actions?.canForwardToCommission ? 'Dica: atribua ao menos um membro da Comissão como responsável antes de encaminhar.' : (recourse.actions?.forwardToCommissionDisabledReason || 'Ação indisponível no momento.') }}
        </p>
      </div>

      <!-- Ações adicionais de fluxo -->
      <div class="space-y-3">
        <!-- RH: Encaminhar à DGP (removido: agora automático após parecer da Comissão) -->

        <!-- DGP: Registrar decisão -->
        <div v-if="recourse.actions?.canDgpDecide" class="bg-white rounded-lg shadow-sm border p-4">
          <div class="flex items-center gap-2 mb-3 text-sm text-gray-700">
            <icons.BadgeCheck class="w-4 h-4" /> Registrar decisão da DGP
          </div>
          <!-- Contexto para a DGP: parecer final da Comissão -->
          <div class="bg-gray-50 border border-gray-200 rounded p-3 mb-3 space-y-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
              <icons.Scale class="w-4 h-4" /> Parecer da Comissão
            </div>
            <template v-if="recourse.response || (recourse.responseAttachments && recourse.responseAttachments.length) || recourse.status === 'respondido' || recourse.status === 'indeferido'">
              <div v-if="recourse.status === 'respondido' || recourse.status === 'indeferido'" class="text-sm text-gray-700 flex items-center gap-2">
                <span class="font-medium">Decisão:</span>
                <span class="uppercase">{{ recourse.status === 'respondido' ? 'DEFERIDO' : 'INDEFERIDO' }}</span>
              </div>
              <div v-if="recourse.response" class="bg-white border rounded p-2 text-sm text-gray-700 whitespace-pre-wrap">
                {{ recourse.response }}
              </div>
              <div v-if="recourse.responseAttachments && recourse.responseAttachments.length" class="mt-1">
                <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1">
                  <icons.Paperclip class="w-4 h-4" /> Documentos do Parecer
                </h4>
                <ul class="space-y-1">
                  <li v-for="(f,i) in recourse.responseAttachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                    <span class="truncate">{{ f.name }}</span>
                    <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                  </li>
                </ul>
              </div>
            </template>
            <p v-else class="text-xs text-gray-500">A Comissão ainda não registrou o parecer final.</p>
          </div>
          <div class="mt-2">
            <label class="block text-sm text-gray-700 mb-1">Justificativa (obrigatória para indeferir)</label>
            <textarea v-model="dgpNotes" rows="3" class="w-full border rounded p-2" placeholder="Descreva a justificativa caso vá indeferir..."></textarea>
          </div>
          <!-- Anexos da decisão da DGP (opcional) -->
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="dgpDecisionFileInput" type="file" multiple @change="handleDgpDecisionFiles" class="hidden" />
            <button type="button" @click="triggerDgpDecisionFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 10MB por arquivo.</div>
            <ul v-if="dgpDecisionAttachments.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in dgpDecisionAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeDgpDecisionAttachment(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <button class="px-4 py-2 bg-green-600 text-white rounded text-sm" @click="submitDgpDecision('homologado')">Deferir</button>
            <button class="px-4 py-2 bg-red-600 text-white rounded text-sm" :disabled="!dgpNotes.trim()" @click="submitDgpDecision('nao_homologado')">Indeferir</button>
          </div>
        </div>

        <!-- DGP: Devolver para Comissão (com justificativa) -->
        <div v-if="recourse.actions?.canDgpReturnToCommission" class="bg-white rounded-lg shadow-sm border p-4 flex items-center justify-between">
          <div class="text-sm text-gray-700 flex items-center gap-2">
            <icons.RotateCcw class="w-4 h-4" />
            <span>Devolver para a Comissão com justificativa.</span>
          </div>
          <button class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 text-sm" @click="showDgpReturnModal = true">Devolver à Comissão</button>
        </div>

        <!-- RH: Finalizar primeira instância (automático após DGP) -->

        <!-- RH: Encaminhar ao Secretário (2ª instância) -->
        <div v-if="userRole === 'RH' && recourse.actions?.canForwardToSecretary" class="bg-white rounded-lg shadow-sm border p-4 flex items-center justify-between">
          <div class="text-sm text-gray-700 flex items-center gap-2">
            <icons.Send class="w-4 h-4" />
            <span>Encaminhar o processo ao Secretário para análise (2ª instância).</span>
          </div>
          <button class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm" @click="router.post(route('recourses.forwardToSecretary', recourse.id))">Enviar ao Secretário</button>
        </div>

        <!-- Secretário: Registrar decisão -->
        <div v-if="recourse.actions?.canSecretaryDecide" class="bg-white rounded-lg shadow-sm border p-4">
          <div class="flex items-center gap-2 mb-3 text-sm text-gray-700">
            <icons.BadgeCheck class="w-4 h-4" /> Registrar decisão do Secretário (2ª instância)
          </div>
          <!-- Contexto para o Secretário: questionamento do servidor e parecer da DGP -->
          <div class="bg-gray-50 border border-gray-200 rounded p-3 mb-3 space-y-3">
            <div v-if="recourse.second_instance?.text" class="">
              <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-1">
                <icons.MessageSquare class="w-4 h-4" /> Questionamento do Servidor (2ª instância)
              </div>
              <p class="text-sm text-gray-700 whitespace-pre-wrap bg-white border rounded p-2">{{ recourse.second_instance.text }}</p>
              <p v-if="recourse.second_instance?.requested_at" class="text-xs text-gray-500 mt-1">Solicitado em: {{ recourse.second_instance.requested_at }}</p>
            </div>
            <div v-if="recourse.dgp?.decision" class="">
              <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-1">
                <icons.Stamp class="w-4 h-4" /> Parecer da DGP
              </div>
              <div class="text-sm text-gray-700">
                <span class="font-medium">Decisão:</span>
                <span class="uppercase">{{ recourse.dgp.decision }}</span>
                <span v-if="recourse.dgp?.decided_at" class="text-gray-500 ml-2">({{ recourse.dgp.decided_at }})</span>
              </div>
              <div v-if="recourse.dgp?.notes" class="mt-1 bg-white border rounded p-2 text-sm text-gray-700 whitespace-pre-wrap">
                {{ recourse.dgp.notes }}
              </div>
            </div>
          </div>
          <!-- Notas e anexos para decisão do Secretário -->
          <div class="mt-2">
            <label class="block text-sm text-gray-700 mb-1">Justificativa (obrigatória para indeferir)</label>
            <textarea v-model="secretaryNotes" rows="3" class="w-full border rounded p-2" placeholder="Descreva a justificativa caso vá indeferir..."></textarea>
          </div>
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="secretaryDecisionFileInput" type="file" multiple @change="handleSecretaryDecisionFiles" class="hidden" />
            <button type="button" @click="triggerSecretaryDecisionFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 10MB por arquivo.</div>
            <ul v-if="secretaryDecisionAttachments.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in secretaryDecisionAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeSecretaryDecisionAttachment(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <button class="px-4 py-2 bg-green-600 text-white rounded text-sm" @click="submitSecretaryDecision('homologado')">Deferir</button>
            <button class="px-4 py-2 bg-red-600 text-white rounded text-sm" :disabled="!secretaryNotes.trim()" @click="submitSecretaryDecision('nao_homologado')">Indeferir</button>
          </div>
        </div>

        <!-- RH: Finalizar segunda instância -->
        <div v-if="userRole === 'RH' && recourse.actions?.canRhFinalizeSecond" class="bg-white rounded-lg shadow-sm border p-4 flex items-center justify-between">
          <div class="text-sm text-gray-700 flex items-center gap-2">
            <icons.Check class="w-4 h-4" />
            <span>Finalizar trâmites e comunicar o servidor (2ª instância).</span>
          </div>
          <button class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm" @click="router.post(route('recourses.rhFinalizeSecond', recourse.id))">Finalizar RH (2ª)</button>
        </div>
      </div>

      <!-- Modal: Justificativa para reencaminhar à Comissão -->
      <div v-if="showForwardModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900">Justificar reenvio para a Comissão</h3>
          <p class="text-sm text-gray-600 mt-2">Este recurso foi devolvido ao RH. Informe a justificativa para reencaminhar à Comissão.</p>
          <div class="mt-4">
            <textarea v-model="forwardMessage" rows="4" class="w-full border rounded p-2" placeholder="Descreva a justificativa..."></textarea>
          </div>
          <!-- Anexos do reenvio (opcional) -->
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="forwardFileInput" type="file" multiple @change="handleForwardFiles" class="hidden" />
            <button type="button" @click="triggerForwardFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 10MB por arquivo.</div>
            <ul v-if="forwardAttachments.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in forwardAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeForwardAttachment(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="showForwardModal = false">Cancelar</button>
            <button class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800" :disabled="!forwardMessage.trim()" @click="confirmForwardToCommission">Confirmar envio</button>
          </div>
        </div>
      </div>

      <!-- Modal: DGP devolver para Comissão -->
      <div v-if="showDgpReturnModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900">Devolver para a Comissão</h3>
          <p class="text-sm text-gray-600 mt-2">Informe a justificativa para devolver o recurso à Comissão.</p>
          <div class="mt-4">
            <textarea v-model="dgpReturnMessage" rows="4" class="w-full border rounded p-2" placeholder="Descreva a justificativa..."></textarea>
          </div>
          <!-- Anexos da devolução (opcional) -->
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="dgpReturnFileInput" type="file" multiple @change="handleDgpReturnFiles" class="hidden" />
            <button type="button" @click="triggerDgpReturnFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 10MB por arquivo.</div>
            <ul v-if="dgpReturnAttachments.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in dgpReturnAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeDgpReturnAttachment(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="showDgpReturnModal = false">Cancelar</button>
            <button class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700" :disabled="!dgpReturnMessage.trim()" @click="confirmDgpReturn">Confirmar devolução</button>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Análise e Parecer da Comissão -->
      <div class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-800 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.Scale class="w-5 h-5" />
            Análise e Parecer da Comissão
          </h2>
          <p class="text-sm text-gray-300 mt-1">Decisão sobre o recurso baseada na avaliação das notas</p>
        </div>

        <div class="p-4">
          <!-- Ações rápidas por etapa -->
          <div class="mb-4 flex flex-wrap gap-2">
            <!-- Comissão decide -->
            <button v-if="props.permissions?.isComissao && recourse.stage === 'comissao' && recourse.status !== 'respondido' && recourse.status !== 'indeferido'"
              @click="isAnalyzing ? null : markAsAnalyzing()"
              class="px-3 py-2 text-xs bg-gray-200 rounded">Iniciar/continuar análise</button>

            <!-- Diretoria homologa -->
            <template v-if="props.permissions?.isRH && recourse.stage === 'diretoria_rh'">
              <button @click="directorDecision('deferido')" class="px-3 py-2 text-xs bg-green-600 text-white rounded">Diretoria: Deferir</button>
              <button @click="directorDecision('indeferido')" class="px-3 py-2 text-xs bg-red-600 text-white rounded">Diretoria: Indeferir</button>
            </template>

            <!-- RH encaminha à 2ª instância -->
            <button v-if="props.permissions?.isRH && recourse.stage === 'requerente'" @click="escalateToSecretary" class="px-3 py-2 text-xs bg-indigo-600 text-white rounded">Encaminhar ao Secretário</button>

            <!-- Secretário decide -->
            <template v-if="props.permissions?.isRH && recourse.stage === 'secretario'">
              <button @click="secretaryDecision('deferido')" class="px-3 py-2 text-xs bg-green-700 text-white rounded">Secretário: Deferir</button>
              <button @click="secretaryDecision('indeferido')" class="px-3 py-2 text-xs bg-red-700 text-white rounded">Secretário: Indeferir</button>
            </template>

            <!-- Devolver à instância anterior -->
            <button v-if="(props.permissions?.isRH || props.permissions?.isComissao) && ['comissao','diretoria_rh','requerente','secretario'].includes(recourse.stage || '')"
              @click="returnToPrevious" class="px-3 py-2 text-xs bg-yellow-500 text-white rounded">Devolver etapa</button>
          </div>

          <!-- Mostrar parecer final -->
          <template v-if="recourse.status === 'respondido' || recourse.status === 'indeferido'">
            <div class="space-y-4">
              <!-- Status da decisão -->
              <div class="flex items-center justify-center">
                <div class="flex items-center gap-3 px-6 py-3 rounded-full border-2 bg-gray-50 border-gray-400 text-gray-700">
                  <icons.CheckCircle v-if="recourse.status === 'respondido'" class="w-6 h-6" />
                  <icons.XCircle v-else class="w-6 h-6" />
                  <span class="font-semibold text-lg">
                    {{ recourse.status === 'respondido' ? 'RECURSO DEFERIDO' : 'RECURSO INDEFERIDO' }}
                  </span>
                </div>
              </div>

              <!-- Parecer detalhado -->
              <div class="bg-gray-50 border rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                  <icons.FileText class="w-5 h-5" />
                  Parecer Fundamentado
                </h3>
                <div class="bg-white border rounded-lg p-4">
                  <p class="text-gray-700 whitespace-pre-wrap leading-relaxed">{{ recourse.response }}</p>
                </div>
              </div>
              
              <!-- Anexos da resposta -->
              <div v-if="recourse.responseAttachments && recourse.responseAttachments.length" 
                   class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                  <icons.Paperclip class="w-4 h-4" />
                  Documentos de Apoio ao Parecer
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div v-for="(file, index) in recourse.responseAttachments" :key="index"
                       class="flex items-center gap-2 p-2 bg-white border rounded hover:bg-gray-50 cursor-pointer transition-colors"
                       @click="openFile(file)">
                    <icons.File class="w-4 h-4 text-gray-600" />
                    <span class="text-sm text-gray-700 hover:underline">{{ file.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Formulário de análise: somente Comissão responsável -->
          <template v-else-if="isAnalyzing && canDecideNow">
            <div class="space-y-6">
              <!-- Área de texto para parecer -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Parecer Fundamentado da Comissão
                </label>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-3">
                  <p class="text-sm text-gray-700">
                    <icons.Info class="w-4 h-4 inline mr-1" />
                    Analise as notas atribuídas pelo chefe e a justificativa do funcionário para emitir um parecer fundamentado.
                  </p>
                </div>
                <textarea
                  v-model="response"
                  class="w-full border border-gray-300 rounded-lg p-4 text-sm focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                  rows="6"
                  placeholder="Digite o parecer detalhado, considerando as notas do chefe e os argumentos apresentados no recurso..."
                ></textarea>
              </div>

              <!-- Anexos da resposta -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Documentos de Apoio (opcional)
                </label>
                
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  @change="handleFileSelect"
                  class="hidden"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                />
                
                <button
                  @click="triggerFileInput"
                  type="button"
                  class="w-full px-4 py-3 border-2 border-dashed border-gray-300 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center gap-2"
                >
                  <icons.Upload class="w-5 h-5" />
                  Adicionar Documentos de Apoio
                </button>

                <!-- Lista de anexos selecionados -->
                <div v-if="responseAttachments.length" class="mt-4 space-y-2">
                  <p class="text-sm font-medium text-gray-700">Documentos selecionados:</p>
                  <div class="space-y-2">
                    <div
                      v-for="(file, index) in responseAttachments"
                      :key="index"
                      class="flex items-center justify-between bg-gray-50 p-3 rounded-lg border"
                    >
                      <span class="flex items-center gap-2 text-sm">
                        <icons.File class="w-4 h-4 text-gray-500" />
                        {{ file.name }}
                      </span>
                      <button
                        @click="removeAttachment(index)"
                        class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition-colors"
                        type="button"
                      >
                        <icons.X class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Decisão -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">
                  Decisão da Comissão
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                         :class="[decision === 'respondido' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400', !canDecideNow ? 'opacity-60 cursor-not-allowed' : '']">
                    <input type="radio" v-model="decision" value="respondido" class="text-gray-600" :disabled="!canDecideNow" />
                    <icons.CheckCircle class="w-5 h-5" />
                    <span class="font-medium">DEFERIR RECURSO</span>
                  </label>
                  <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                         :class="[decision === 'indeferido' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400', !canDecideNow ? 'opacity-60 cursor-not-allowed' : '']">
                    <input type="radio" v-model="decision" value="indeferido" class="text-gray-600" :disabled="!canDecideNow" />
                    <icons.XCircle class="w-5 h-5" />
                    <span class="font-medium">INDEFERIR RECURSO</span>
                  </label>
                </div>
              </div>

              <!-- Botão para salvar -->
              <div class="text-center pt-4 border-t">
                <button
                  @click="submitAnalysis"
                  class="px-8 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-lg font-medium flex items-center gap-2 mx-auto"
                  :disabled="!decision || !response.trim() || !canDecideNow"
                  :class="{ 'opacity-50 cursor-not-allowed': !decision || !response.trim() || !canDecideNow }"
                >
                  <icons.Save class="w-5 h-5" />
                  Finalizar Análise e Salvar Parecer
                </button>
                <div v-if="!canDecideNow" class="text-xs text-gray-500 mt-2">A decisão final só pode ser tomada pela instância atual.</div>
              </div>
            </div>
          </template>

          <!-- Botão para iniciar análise: somente Comissão responsável -->
          <template v-else-if="canDecideNow">
            <div class="text-center py-8">
              <icons.Play class="w-12 h-12 text-gray-500 mx-auto mb-4" />
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Pronto para Análise</h3>
              <p class="text-gray-600 mb-6">
                Inicie a análise do recurso para avaliar as notas do chefe e a justificativa apresentada.
              </p>
              <button
                @click="markAsAnalyzing"
                class="px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-lg font-medium flex items-center gap-2 mx-auto"
              >
                <icons.Play class="w-5 h-5" />
                Iniciar Análise do Recurso
              </button>
            </div>
          </template>
          <!-- Modo somente leitura para quem não pode decidir -->
          <template v-else>
            <div class="text-center py-8">
              <icons.Lock class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Aguardando Comissão</h3>
              <p class="text-gray-600">Somente a Comissão responsável pode iniciar a análise e registrar o parecer.</p>
            </div>
          </template>
        </div>
      </div>

  <!-- Ação: Devolver para instância anterior (somente Comissão responsável) -->
  <div v-if="recourse.current_instance === 'Comissao' && userRole === 'Comissão' && canDecideNow" class="bg-white rounded-lg shadow-sm border p-4">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700 flex items-center gap-2">
            <icons.Reply class="w-4 h-4" />
            <span>Precisa de complementação? Você pode devolver para a instância anterior.</span>
          </div>
          <button
            class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 transition-colors text-sm flex items-center gap-2"
            type="button"
            @click="showReturnModal = true"
          >
            <icons.Reply class="w-4 h-4" />
            Devolver
          </button>
        </div>
      </div>

      <!-- Modal de Devolução com Justificativa -->
      <div v-if="showReturnModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
          <div class="p-5 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Devolver Recurso</h3>
            <p class="text-sm text-gray-500">Informe a justificativa para a devolução. Esta informação ficará registrada no histórico.</p>
          </div>
          <div class="p-5 space-y-3">
            <label class="block text-sm font-medium text-gray-700">Justificativa</label>
            <textarea v-model="returnMessage" rows="4" class="w-full border rounded p-2" placeholder="Descreva o que precisa ser complementado ou esclarecido..."></textarea>
            <!-- Anexos da devolução (opcional) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
              <input ref="returnFileInput" type="file" multiple @change="handleReturnFiles" class="hidden" />
              <button type="button" @click="triggerReturnFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
                <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
              </button>
              <div class="mt-2 text-xs text-gray-500">Até 10MB por arquivo.</div>
              <ul v-if="returnAttachments.length" class="mt-2 space-y-1">
                <li v-for="(f,i) in returnAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeReturnAttachment(i)">remover</button>
                </li>
              </ul>
            </div>
          </div>
          <div class="p-5 border-t flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="showReturnModal = false">Cancelar</button>
            <button class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700" @click="returnToPreviousInstance" :disabled="!returnMessage.trim()">Confirmar Devolução</button>
          </div>
        </div>
      </div>

      <!-- SEÇÃO: Histórico do Recurso -->
      <div v-if="recourse.logs.length" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-600 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.Clock class="w-5 h-5" />
            Histórico do Recurso
          </h2>
          <p class="text-sm text-gray-300 mt-1">
            Linha do tempo com todas as etapas do processo
          </p>
        </div>
        
        <div class="p-4">
          <div class="relative">
            <!-- Linha vertical -->
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-300"></div>
            
            <!-- Eventos -->
            <div class="space-y-6">
              <div v-for="(log, index) in recourse.logs" :key="index" class="relative flex items-start gap-4">
                <!-- Ponto na linha do tempo -->
                <div class="relative z-10 flex-shrink-0">
                  <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center text-white text-sm font-medium">
                    <icons.Circle class="w-3 h-3 fill-current" />
                  </div>
                </div>
                
                <!-- Conteúdo do evento -->
                <div class="flex-1 pb-6">
                  <div class="bg-gray-50 rounded-lg p-4 border">
                    <div class="flex items-center justify-between mb-2">
                      <h3 class="font-semibold text-gray-800">
                        {{ log.status.replace('_', ' ').toUpperCase() }}
                      </h3>
                      <span class="text-xs text-gray-500 bg-white px-2 py-1 rounded">
                        {{ log.created_at }}
                      </span>
                    </div>
                    <p v-if="log.message" class="text-sm text-gray-600">
                      {{ log.message }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Confirmação para Remover Responsável -->
    <div v-if="showRemoveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <!-- Header do Modal -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
              <icons.AlertTriangle class="w-6 h-6 text-red-600" />
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Confirmar Remoção</h3>
              <p class="text-sm text-gray-500">Esta ação não pode ser desfeita</p>
            </div>
          </div>
        </div>

        <!-- Conteúdo do Modal -->
        <div class="p-6">
          <div class="space-y-4">
            <p class="text-gray-700">
              Você está prestes a remover o seguinte membro da comissão responsável pela análise deste recurso:
            </p>
            
            <div class="bg-gray-50 rounded-lg p-4 border">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-600 text-white rounded-full flex items-center justify-center text-sm font-medium">
                  {{ responsibleToRemove?.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <p class="font-medium text-gray-900">{{ responsibleToRemove?.name }}</p>
                  <p class="text-sm text-gray-600">Matrícula: {{ responsibleToRemove?.registration_number }}</p>
                </div>
              </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div class="flex gap-2">
                <icons.Info class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                <div class="text-sm text-blue-800">
                  <p class="font-medium mb-1">Importante:</p>
                  <p>O histórico de ações já realizadas por este membro será preservado. Apenas o acesso futuro para análise do recurso será removido.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer do Modal -->
        <div class="p-6 border-t border-gray-200 flex gap-3 justify-end">
          <button
            @click="cancelRemoveResponsible"
            class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors font-medium"
          >
            Cancelar
          </button>
          <button
            @click="confirmRemoveResponsible"
            class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors font-medium flex items-center gap-2"
          >
            <icons.UserMinus class="w-4 h-4" />
            Remover da Comissão
          </button>
        </div>
      </div>
    </div>

  </DashboardLayout>
</template>
