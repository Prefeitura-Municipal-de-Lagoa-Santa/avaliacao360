<script setup lang="ts">
import { ref, computed } from 'vue';
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
    commission?: {
      decision?: string | null;
      response?: string | null;
      decided_at?: string | null;
      clarification?: {
        response?: string | null;
        responded_at?: string | null;
        attachments?: Array<{ name: string; url: string }>;
      };
    };
    dgp?: {
      decision?: string | null;
      decided_at?: string | null;
      notes?: string | null;
      attachments?: Array<{ name: string; url: string }>;
    };
    second_instance?: { 
      enabled: boolean; 
      requested_at?: string | null; 
      text?: string | null;
      deadline_at?: string | null;
      deadline_days?: number;
      is_deadline_expired?: boolean;
    };
    secretary?: {
      decision?: string | null;
      decided_at?: string | null;
      notes?: string | null;
      attachments?: Array<{ name: string; url: string }>;
    };
    last_return?: {
      by: string;
      to: 'RH' | 'Comissao' | null;
      at: string | null;
      message?: string | null;
      attachments?: Array<{ name: string; url: string }>;
    } | null;
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
    forward?: { at?: string | null; message?: string | null; attachments?: Array<{ name: string; url: string }> } | null;
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
  commission_clarification: 'Esclarecimento da Comissão',
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
const dgpDecision = ref<'homologado' | 'nao_homologado' | null>(null);
const secretaryDecisionChoice = ref<'homologado' | 'nao_homologado' | null>(null);
const responseAttachments = ref<File[]>([]);
// Clarification state
const clarificationResponse = ref('');
const clarificationAttachments = ref<File[]>([]);
const clarificationFileInput = ref<HTMLInputElement | null>(null);
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
// Após encaminhamento para a Comissão o perfil RH deve apenas visualizar histórico
const isRhViewerPostForward = computed(() => props.userRole === 'RH' && props.recourse.current_instance !== 'RH');
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

// Contador de devoluções anteriores com base nos logs
const returnsCount = computed(() => {
  try {
    return (props.recourse.logs || []).filter((log) => {
      const s = (log.status || '').toLowerCase();
      const m = (log.message || '').toLowerCase();
      return s.includes('devolv') || s.includes('return') || m.includes('devolv') || m.includes('return');
    }).length;
  } catch {
    return 0;
  }
});

// Mostrar aviso de última devolução apenas quando ainda pertinente
const showLastReturnBanner = computed(() => {
  const lr = props.recourse.last_return;
  if (!lr) return false;
  // Se o recurso já mudou de instância desde a devolução, não exibir
  if (props.recourse.current_instance !== lr.to) return false;
  // Se já houve decisão final, não exibir
  if (props.recourse.status === 'respondido' || props.recourse.status === 'indeferido') return false;
  // Caso haja parecer registrado (texto), considerar tratado
  if (props.recourse.response && props.recourse.response.trim().length > 0) return false;
  return true;
});

// Mostrar informações sobre o prazo da segunda instância
const showSecondInstanceDeadlineInfo = computed(() => {
  const si = props.recourse.second_instance;
  // Só mostrar se houver prazo definido e ainda não solicitou segunda instância
  return si?.deadline_at && !si.enabled;
});

// Formatação do prazo da segunda instância
const formatSecondInstanceDeadline = computed(() => {
  if (!props.recourse.second_instance?.deadline_at) return '';
  try {
    const date = new Date(props.recourse.second_instance.deadline_at);
    return date.toLocaleDateString('pt-BR', { 
      day: '2-digit', 
      month: '2-digit', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch {
    return props.recourse.second_instance.deadline_at;
  }
});

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
function triggerClarificationFileInput(){ clarificationFileInput.value?.click(); }
function handleClarificationFiles(e: Event){
  const files = (e.target as HTMLInputElement).files; if(files){ clarificationAttachments.value.push(...Array.from(files)); (e.target as HTMLInputElement).value=''; }
}
function removeClarificationAttachment(i:number){ clarificationAttachments.value.splice(i,1); }
function submitClarification(){
  if(!clarificationResponse.value.trim()) { alert('Informe a resposta de esclarecimento.'); return; }
  const fd = new FormData();
  fd.append('clarification_response', clarificationResponse.value.trim());
  clarificationAttachments.value.forEach((f,i)=> fd.append(`clarification_attachments[${i}]`, f));
  router.post(route('recourses.respondClarification', props.recourse.id), fd, { forceFormData: true, onSuccess:()=>{ clarificationResponse.value=''; clarificationAttachments.value=[]; }});
}

function submitAnalysis() {
  if (!decision.value || !response.value.trim()) {
    alert('Informe o parecer e selecione o tipo de decisão.');
    return;
  }
  if (responseAttachments.value.length === 0) {
    alert('Envie pelo menos um documento de apoio antes de salvar o parecer.');
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
  // Sempre abrir modal para permitir anexos e mensagem
  showForwardModal.value = true;
}

function confirmForwardToCommission() {
  // Mensagem obrigatória apenas se houve devolução anterior
  if (props.recourse.last_return && !forwardMessage.value.trim()) {
    alert('Informe a justificativa do reenvio.');
    return;
  }
  const formData = new FormData();
  if (forwardMessage.value.trim()) {
    formData.append('message', forwardMessage.value.trim());
  }
  forwardAttachments.value.forEach((file, index) => formData.append(`forward_attachments[${index}]`, file));
  router.post(route('recourses.forwardToCommission', props.recourse.id), formData, {
    forceFormData: true,
    onSuccess: () => {
      showForwardModal.value = false;
      forwardMessage.value = '';
      forwardAttachments.value = [];
  // Removido alerta de confirmação após encaminhar para a Comissão
    },
    onError: () => {
      // Mantém modal aberto para correção
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
      try { alert('Recurso devolvido para a Comissão.'); } catch {}
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
      try { alert('Recurso devolvido para a instância anterior.'); } catch {}
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
function saveDgpDecision() {
  if (!dgpDecision.value) {
    alert('Selecione uma decisão (Deferir ou Indeferir).');
    return;
  }
  if (dgpDecision.value === 'nao_homologado' && !dgpNotes.value.trim()) {
    alert('Justificativa obrigatória para indeferir.');
    return;
  }
  submitDgpDecision(dgpDecision.value);
}
function submitSecretaryDecision(decision: 'homologado' | 'nao_homologado') {
  const fd = new FormData();
  fd.append('decision', decision);
  if (secretaryNotes.value) fd.append('notes', secretaryNotes.value);
  secretaryDecisionAttachments.value.forEach((f, idx) => fd.append(`secretary_decision_attachments[${idx}]`, f));
  router.post(route('recourses.secretaryDecision', props.recourse.id), fd, { forceFormData: true });
}

function saveSecretaryDecision() {
  if (!secretaryDecisionChoice.value) {
    alert('Selecione uma decisão (Deferir ou Indeferir).');
    return;
  }
  if (secretaryDecisionChoice.value === 'nao_homologado' && !secretaryNotes.value.trim()) {
    alert('Justificativa obrigatória para indeferir.');
    return;
  }
  submitSecretaryDecision(secretaryDecisionChoice.value);
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
                <strong>Instância Atual:</strong>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full border"
                  :class="recourse.second_instance?.enabled ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-green-50 text-green-700 border-green-200'">
                  {{ recourse.second_instance?.enabled ? '2ª instância' : '1ª instância' }}
                </span>
              </span>
              <span v-if="recourse.stage" class="flex items-center gap-1">
                <icons.Flag class="w-4 h-4" />
                <strong>Etapa:</strong> {{ formatStageLabel(recourse.stage) }}
              </span>
        </div>
        <!-- Chips de etapa/status -->
        <div class="flex flex-wrap items-center gap-3 mt-2">
          <span v-if="returnsCount > 0" class="text-xs px-3 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-300 whitespace-nowrap">
            Devolvido {{ returnsCount }} vez{{ returnsCount > 1 ? 'es' : '' }}
          </span>
          <span
            class="text-sm font-medium text-white px-4 py-2 rounded-full"
            :class="[
              (userRole === 'RH' && (recourse.stage === 'await_first_ack' || recourse.stage === 'await_second_ack' || recourse.stage === 'completed'))
                ? 'bg-green-600'
                : (recourse.status === 'aberto' ? 'bg-gray-500'
                  : recourse.status === 'em_analise' ? 'bg-gray-600'
                  : recourse.status === 'respondido' ? 'bg-gray-700'
                  : 'bg-gray-800')
            ]"
          >
            {{ (userRole === 'RH' && (recourse.stage === 'await_first_ack' || recourse.stage === 'await_second_ack' || recourse.stage === 'completed'))
              ? 'CONCLUÍDO'
              : recourse.status.replace('_', ' ').toUpperCase() }}
          </span>
        </div>
        </div>

        <!-- Aviso: RH somente leitura fora da Análise do RH -->
        <div
          v-if="userRole === 'RH' && (recourse.current_instance !== 'RH' || recourse.stage !== 'rh_analysis')"
          class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded"
        >
          <div class="flex items-start gap-2 text-blue-800">
            <icons.Info class="w-4 h-4 mt-0.5" />
            <div class="text-sm">
              <p>
                Este recurso está em <strong>{{ formatStageLabel(recourse.stage) }}</strong>. As ações do RH estão bloqueadas nesta fase.
              </p>
              <p v-if="recourse.stage === 'dgp_review'" class="mt-1">
                Aguardando decisão da DGP. Após a decisão, o servidor registrará ciência.
              </p>
              <p v-else-if="recourse.stage === 'secretary_review'" class="mt-1">
                Aguardando decisão do Secretário.
              </p>
            </div>
          </div>
        </div>

        <!-- Info de última devolução -->
        <div v-if="showLastReturnBanner" class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded">
          <div class="flex items-start gap-2 text-amber-800">
            <icons.Reply class="w-4 h-4 mt-0.5" />
            <div class="text-sm w-full">
              <p>
                Recurso devolvido para <strong>{{ recourse.last_return?.to }}</strong> por <strong>{{ recourse.last_return?.by }}</strong> em {{ recourse.last_return?.at }}.
              </p>
              <p v-if="recourse.last_return?.message" class="mt-1 whitespace-pre-wrap">{{ recourse.last_return.message }}</p>
              <!-- Anexos da última devolução (se houver) -->
              <div v-if="recourse.last_return?.attachments?.length" class="mt-2 bg-white/60 border border-amber-200 rounded p-2">
                <h4 class="text-xs font-semibold text-amber-900 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da devolução</h4>
                <ul class="space-y-1 max-h-40 overflow-y-auto">
                  <li v-for="(f,i) in recourse.last_return.attachments" :key="i" class="flex items-center justify-between text-xs bg-amber-50 border rounded px-2 py-1">
                    <span class="truncate">{{ f.name }}</span>
                    <a :href="f.url" target="_blank" class="text-amber-700 hover:underline">abrir</a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Info sobre prazo da segunda instância -->
        <div v-if="showSecondInstanceDeadlineInfo" class="mt-2 p-3 rounded" 
             :class="recourse.second_instance?.is_deadline_expired ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200'">
          <div class="flex items-start gap-2" 
               :class="recourse.second_instance?.is_deadline_expired ? 'text-red-800' : 'text-blue-800'">
            <icons.Clock class="w-4 h-4 mt-0.5" />
            <div class="text-sm w-full">
              <p v-if="recourse.second_instance?.is_deadline_expired" class="font-medium">
                <strong>Prazo expirado:</strong> O prazo para solicitar a 2ª instância expirou em {{ formatSecondInstanceDeadline }}.
              </p>
              <p v-else class="font-medium">
                <strong>Prazo para 2ª instância:</strong> {{ recourse.second_instance?.deadline_days }} dias após a ciência (até {{ formatSecondInstanceDeadline }}).
              </p>
              <p v-if="!recourse.second_instance?.is_deadline_expired" class="mt-1 text-xs opacity-75">
                Caso discorde da decisão da DGP, você pode solicitar recurso em segunda instância ao Secretário dentro do prazo.
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

      

    <!-- SEÇÃO: Presidente da Comissão (RH) -->
  <div v-if="canManageAssignees && recourse.current_instance === 'RH' && recourse.stage === 'rh_analysis'" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-600 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.UserCircle2 class="w-5 h-5" />
            Presidente da Comissão
          </h2>
          <p class="text-sm text-gray-300 mt-1">
            Defina o Presidente da Comissão responsável pela análise deste recurso.
          </p>
        </div>

        <div class="p-4 space-y-6">
          <!-- Presidente atual -->
            <div>
              <h3 class="font-medium text-gray-700 mb-3">Presidente Atual</h3>
              <div v-if="recourse.responsiblePersons.length" class="flex items-center justify-between bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-gray-600 text-white rounded-full flex items-center justify-center text-sm font-medium">
                    {{ recourse.responsiblePersons[0].name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-gray-900">{{ recourse.responsiblePersons[0].name }}</p>
                    <p class="text-xs text-gray-500">{{ recourse.responsiblePersons[0].registration_number }}</p>
                  </div>
                </div>
                <button
                  @click="removeResponsible(recourse.responsiblePersons[0].id)"
                  class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors flex items-center gap-1 text-xs font-medium"
                  type="button"
                  title="Remover Presidente"
                >
                  <icons.Trash2 class="w-4 h-4" /> Remover
                </button>
              </div>
              <div v-else class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                <p class="text-sm text-gray-500 flex items-center justify-center gap-2">
                  <icons.AlertCircle class="w-4 h-4" /> Nenhum Presidente definido.
                </p>
              </div>
            </div>

            <!-- Formulário para definir/alterar Presidente -->
            <div class="border-t pt-4">
              <div v-if="!showAssignForm">
                <button
                  @click="showAssignForm = true"
                  class="w-full px-4 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors flex items-center justify-center gap-2"
                >
                  <icons.UserPlus class="w-4 h-4" />
                  {{ recourse.responsiblePersons.length ? 'Alterar Presidente' : 'Definir Presidente' }}
                </button>
              </div>

              <div v-else class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Selecionar Presidente</label>
                  <select
                    v-model="selectedPersonId"
                    class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                  >
                    <option value="" disabled>Escolha uma pessoa...</option>
                    <option
                      v-for="person in availablePersons"
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
                    Salvar
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
  <div v-if="userRole === 'RH' && recourse.current_instance === 'RH' && recourse.stage === 'rh_analysis'" class="bg-white rounded-lg shadow-sm border p-4">
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

      <!-- SEÇÃO: Análise e Parecer da Comissão (movida para antes da Decisão da DGP) -->
  <div v-if="(userRole !== 'RH' || recourse.current_instance !== 'RH')" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-800 text-white p-4 rounded-t-lg">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.Scale class="w-5 h-5" />
              Análise e Parecer da Comissão
            </h2>
            <!-- Chip com a decisão da DGP dentro do bloco da Comissão -->
            <div v-if="recourse.dgp && recourse.dgp.decision" class="flex items-center gap-2">
              <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border"
                    :class="recourse.dgp.decision === 'homologado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-700 border-red-300'">
                <icons.Stamp class="w-3.5 h-3.5 mr-1" />
                DGP: {{ recourse.dgp.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
              </span>
              <span v-if="recourse.dgp.decided_at" class="text-[10px] text-gray-200">{{ recourse.dgp.decided_at }}</span>
            </div>
          </div>
          <p class="text-sm text-gray-300 mt-1">Decisão sobre o recurso baseada na avaliação das notas</p>
        </div>

        <div class="p-4">
          <!-- Parecer da Comissão (resumo) -->
          <div v-if="(recourse.response && recourse.response.trim()) || (recourse.responseAttachments && recourse.responseAttachments.length) || (recourse.commission?.clarification?.response)" class="mb-6">
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-3">
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="text-xs font-semibold text-gray-700 flex items-center gap-1">
                  <icons.FileText class="w-4 h-4" /> Texto do Parecer da Comissão
                </h3>
                <span v-if="recourse.commission?.decision"
                  class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border"
                  :class="recourse.commission.decision === 'deferido' ? 'bg-green-100 text-green-700 border-green-300' : 'bg-red-100 text-red-700 border-red-300'">
                  {{ recourse.commission.decision === 'deferido' ? 'DEFERIDO' : 'INDEFERIDO' }}
                </span>
                <span v-else-if="recourse.response && recourse.response.trim()"
                  class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border bg-gray-100 text-gray-700 border-gray-300">
                  ANÁLISE CONCLUÍDA
                </span>
              </div>
              <div v-if="recourse.commission?.decision" class="mt-1 text-[11px] text-gray-600 flex items-center gap-2">
                <icons.Clock class="w-3 h-3" />
                <span>
                  <span class="font-semibold">Decisão da Comissão:</span>
                  <strong :class="recourse.commission.decision === 'deferido' ? 'text-green-700' : 'text-red-700'">
                    {{ recourse.commission.decision === 'deferido' ? 'DEFERIDO' : 'INDEFERIDO' }}
                  </strong>
                  <span v-if="recourse.commission.decided_at"> em {{ recourse.commission.decided_at }}</span>
                </span>
              </div>
              <div v-if="recourse.response" class="bg-white border rounded p-3 text-sm text-gray-700 whitespace-pre-wrap max-h-48 overflow-y-auto">
                {{ recourse.response }}
              </div>
              <div v-if="recourse.responseAttachments && recourse.responseAttachments.length" class="pt-2 border-t border-gray-200">
                <h4 class="text-xs font-medium text-gray-600 mb-1 flex items-center gap-1"><icons.Paperclip class="w-4 h-4" /> Anexos do Parecer</h4>
                <ul class="space-y-1 max-h-40 overflow-y-auto">
                  <li v-for="(f,i) in recourse.responseAttachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                    <span class="truncate">{{ f.name }}</span>
                    <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                  </li>
                </ul>
              </div>
              <!-- Complemento de esclarecimento da Comissão (não substitui parecer) -->
              <div v-if="recourse.commission?.clarification?.response" class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                  <h4 class="text-xs font-semibold text-gray-700 flex items-center gap-1">
                    <icons.MessageSquare class="w-4 h-4" /> Esclarecimento Complementar da Comissão
                  </h4>
                  <span v-if="recourse.commission.clarification.responded_at" class="text-[10px] text-gray-500 flex items-center gap-1">
                    <icons.Clock class="w-3 h-3" /> {{ recourse.commission.clarification.responded_at }}
                  </span>
                </div>
                <div class="bg-white border rounded p-3 text-xs text-gray-700 whitespace-pre-wrap max-h-40 overflow-y-auto">
                  {{ recourse.commission.clarification.response }}
                </div>
                <div v-if="recourse.commission.clarification.attachments && recourse.commission.clarification.attachments.length" class="mt-3">
                  <h5 class="text-[11px] font-medium text-gray-600 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos do Esclarecimento</h5>
                  <ul class="space-y-1 max-h-32 overflow-y-auto">
                    <li v-for="(f,i) in recourse.commission.clarification.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-[11px]">
                      <span class="truncate">{{ f.name }}</span>
                      <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- BLOCO ESCLARECIMENTO SOLICITADO -->
          <div v-if="recourse.stage === 'commission_clarification'" class="mb-6 space-y-4">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
              <div class="flex items-start gap-2">
                <icons.HelpCircle class="w-5 h-5 text-amber-600 mt-0.5" />
                <div class="flex-1">
                  <h3 class="text-sm font-semibold text-amber-800 mb-1">Esclarecimentos solicitados pela DGP</h3>
                  <p class="text-sm text-amber-800 whitespace-pre-wrap">{{ recourse.last_return?.message || 'A DGP solicitou esclarecimentos adicionais antes da homologação.' }}</p>
                  <div v-if="recourse.last_return && recourse.last_return.attachments && recourse.last_return.attachments.length" class="mt-3 bg-white border rounded p-3">
                    <h4 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da solicitação</h4>
                    <ul class="space-y-1 max-h-40 overflow-y-auto">
                      <li v-for="(f,i) in recourse.last_return.attachments" :key="i" class="flex items-center justify-between text-xs bg-gray-50 border rounded px-2 py-1">
                        <span class="truncate">{{ f.name }}</span>
                        <a :href="f.url" target="_blank" class="text-amber-700 hover:underline">abrir</a>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <!-- Resposta já enviada -->
            <div v-if="recourse.commission?.clarification?.response" class="bg-green-50 border border-green-200 rounded-lg p-4">
              <div class="flex items-start gap-2">
                <icons.CheckCircle class="w-5 h-5 text-green-600 mt-0.5" />
                <div class="flex-1">
                  <h3 class="text-sm font-semibold text-green-700 mb-1">Esclarecimento Respondido</h3>
                  <p class="text-sm text-green-800 whitespace-pre-wrap">{{ recourse.commission.clarification.response }}</p>
                  <p v-if="recourse.commission.clarification.responded_at" class="text-xs text-green-700 mt-2">Enviado em {{ recourse.commission.clarification.responded_at }}</p>
                  <div v-if="recourse.commission.clarification.attachments && recourse.commission.clarification.attachments.length" class="mt-3 bg-white border rounded p-3">
                    <h4 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos do esclarecimento</h4>
                    <ul class="space-y-1 max-h-40 overflow-y-auto">
                      <li v-for="(f,i) in recourse.commission.clarification.attachments" :key="i" class="flex items-center justify-between text-xs bg-gray-50 border rounded px-2 py-1">
                        <span class="truncate">{{ f.name }}</span>
                        <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <!-- Form de resposta (somente se ainda não respondeu e é responsável) -->
            <div v-else-if="canDecideNow" class="bg-white border border-gray-200 rounded-lg p-4 space-y-4">
              <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2"><icons.Reply class="w-4 h-4" /> Responder Esclarecimento</h3>
              <textarea v-model="clarificationResponse" rows="4" class="w-full border rounded p-3 text-sm" placeholder="Digite a resposta de esclarecimento..." />
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Anexos (opcional)</label>
                <input ref="clarificationFileInput" type="file" multiple class="hidden" @change="handleClarificationFiles" />
                <button type="button" @click="triggerClarificationFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-gray-50 hover:bg-gray-100 text-xs">
                  <icons.Paperclip class="w-4 h-4" /> Adicionar arquivos
                </button>
                <p class="text-[10px] text-gray-500 mt-1">Até 100MB por arquivo.</p>
                <ul v-if="clarificationAttachments.length" class="mt-2 space-y-1">
                  <li v-for="(f,i) in clarificationAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                    <span class="truncate">{{ f.name }}</span>
                    <button type="button" class="text-red-600 hover:underline" @click="removeClarificationAttachment(i)">remover</button>
                  </li>
                </ul>
              </div>
              <div class="pt-2 border-t">
                <button @click="submitClarification" :disabled="!clarificationResponse.trim()" class="px-6 py-2 bg-gray-700 text-white rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                  <icons.Send class="w-4 h-4" /> Enviar Esclarecimento
                </button>
              </div>
            </div>
            <!-- Caso não seja responsável -->
            <div v-else class="bg-gray-50 border rounded p-4 text-sm text-gray-600 flex items-center gap-2">
              <icons.Info class="w-4 h-4" /> Aguardando resposta da Comissão responsável.
            </div>
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
          <template v-else-if="isAnalyzing && canDecideNow && recourse.stage !== 'commission_clarification'">
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
                  Documentos de Apoio (obrigatório)
                </label>
                <div v-if="responseAttachments.length === 0" class="text-xs text-red-600 mb-2">
                  Anexe pelo menos um documento antes de salvar o parecer.
                </div>
                
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
                  :disabled="!decision || !response.trim() || !canDecideNow || responseAttachments.length === 0"
                  :class="{ 'opacity-50 cursor-not-allowed': !decision || !response.trim() || !canDecideNow || responseAttachments.length === 0 }"
                >
                  <icons.Save class="w-5 h-5" />
                  Finalizar Análise e Salvar Parecer
                </button>
                <div class="text-xs text-gray-500 mt-2 space-y-1">
                  <div v-if="!canDecideNow">A decisão final só pode ser tomada pela instância atual.</div>
                  <div v-if="responseAttachments.length === 0">É obrigatório anexar pelo menos um documento.</div>
                </div>
              </div>
            </div>
          </template>

          <!-- Botão para iniciar análise: somente Comissão responsável -->
          <template v-else-if="canDecideNow && recourse.stage !== 'commission_clarification'">
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
          <!-- Modo somente leitura para quem não pode decidir (somente durante a etapa da Comissão) -->
          <template v-else-if="recourse.stage === 'commission_analysis'">
            <div class="text-center py-8">
              <icons.Lock class="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 class="text-lg font-semibold text-gray-800 mb-2">Aguardando Comissão</h3>
              <p class="text-gray-600">Somente a Comissão responsável pode iniciar a análise e registrar o parecer.</p>
            </div>
          </template>
          <!-- Nas demais etapas (ex.: dgp_review), nenhum aviso é exibido aqui -->
          <!-- (fim) Comissão -->
        </div>
      </div>

      <!-- Bloco: Decisão da DGP (somente leitura para todos quando houver) -->
  <div v-if="userRole !== 'RH' && recourse.dgp?.decision" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-800 text-white p-4 rounded-t-lg">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.BadgeCheck class="w-5 h-5" />
              Decisão da DGP
            </h2>
            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border"
                  :class="recourse.dgp.decision === 'homologado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-700 border-red-300'">
              <icons.Stamp class="w-3.5 h-3.5 mr-1" />
              {{ recourse.dgp.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
            </span>
          </div>
          <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-xs text-gray-600" v-if="recourse.dgp?.decided_at">
            <icons.Clock class="w-3 h-3 inline mr-1" /> Decidido em {{ recourse.dgp.decided_at }}
          </div>
          <div class="text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.dgp?.notes">
            {{ recourse.dgp.notes }}
          </div>
          <div v-if="recourse.dgp?.attachments?.length" class="pt-2 border-t border-gray-200">
            <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da Decisão</h4>
            <ul class="space-y-1 max-h-40 overflow-y-auto">
              <li v-for="(f,i) in recourse.dgp.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Bloco: Decisão do Secretário (somente leitura) -->
  <div v-if="userRole !== 'RH' && recourse.secretary?.decision" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-800 text-white p-4 rounded-t-lg">
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.BadgeCheck class="w-5 h-5" />
              Decisão do Secretário
            </h2>
            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border"
                  :class="recourse.secretary.decision === 'homologado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-700 border-red-300'">
              <icons.Stamp class="w-3.5 h-3.5 mr-1" />
              {{ recourse.secretary.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
            </span>
          </div>
          <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
        </div>
        <div class="p-4 space-y-3">
          <div class="text-xs text-gray-600" v-if="recourse.secretary?.decided_at">
            <icons.Clock class="w-3 h-3 inline mr-1" /> Decidido em {{ recourse.secretary.decided_at }}
          </div>
          <div class="text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.secretary?.notes">
            {{ recourse.secretary.notes }}
          </div>
          <div v-if="recourse.secretary?.attachments?.length" class="pt-2 border-t border-gray-200">
            <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da Decisão</h4>
            <ul class="space-y-1 max-h-40 overflow-y-auto">
              <li v-for="(f,i) in recourse.secretary.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Resumo das Decisões (RH após decisão da DGP) -->
      <div v-if="userRole === 'RH' && recourse.dgp?.decision" class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-700 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.ClipboardList class="w-5 h-5" /> Resumo das Decisões
          </h2>
          <p class="text-sm text-gray-300 mt-1">Visão geral das decisões registradas nas instâncias</p>
        </div>
        <div class="p-4 space-y-4">
          <!-- Comissão -->
          <div class="bg-gray-50 border rounded-lg p-4" v-if="recourse.commission?.decision || recourse.response">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 text-gray-800 font-semibold">
                <icons.Scale class="w-4 h-4" /> Comissão
              </div>
              <span v-if="recourse.commission?.decision"
                    class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border"
                    :class="recourse.commission.decision === 'deferido' ? 'bg-green-100 text-green-700 border-green-300' : 'bg-red-100 text-red-700 border-red-300'">
                {{ recourse.commission.decision === 'deferido' ? 'DEFERIDO' : 'INDEFERIDO' }}
              </span>
            </div>
            <div class="mt-2 text-xs text-gray-600" v-if="recourse.commission?.decided_at">
              Decidido em {{ recourse.commission.decided_at }}
            </div>
            <div class="mt-2 text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.response">
              {{ recourse.response }}
            </div>
            <div v-if="recourse.responseAttachments?.length" class="mt-2">
              <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos do Parecer</h4>
              <ul class="space-y-1 max-h-40 overflow-y-auto">
                <li v-for="(f,i) in recourse.responseAttachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                </li>
              </ul>
            </div>
          </div>

          <!-- DGP -->
          <div class="bg-gray-50 border rounded-lg p-4">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 text-gray-800 font-semibold">
                <icons.Stamp class="w-4 h-4" /> DGP
              </div>
              <span class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border"
                    :class="recourse.dgp?.decision === 'homologado' ? 'bg-green-100 text-green-700 border-green-300' : 'bg-red-100 text-red-700 border-red-300'">
                {{ recourse.dgp?.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
              </span>
            </div>
            <div class="mt-2 text-xs text-gray-600" v-if="recourse.dgp?.decided_at">
              Decidido em {{ recourse.dgp.decided_at }}
            </div>
            <div class="mt-2 text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.dgp?.notes">
              {{ recourse.dgp.notes }}
            </div>
            <div v-if="recourse.dgp?.attachments?.length" class="mt-2">
              <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da Decisão da DGP</h4>
              <ul class="space-y-1 max-h-40 overflow-y-auto">
                <li v-for="(f,i) in recourse.dgp.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                </li>
              </ul>
            </div>
          </div>

          <!-- Secretário (2ª instância) -->
          <div class="bg-gray-50 border rounded-lg p-4" v-if="recourse.secretary?.decision">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2 text-gray-800 font-semibold">
                <icons.BadgeCheck class="w-4 h-4" /> Secretário (2ª instância)
              </div>
              <span class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border"
                    :class="recourse.secretary?.decision === 'homologado' ? 'bg-green-100 text-green-700 border-green-300' : 'bg-red-100 text-red-700 border-red-300'">
                {{ recourse.secretary?.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
              </span>
            </div>
            <div class="mt-2 text-xs text-gray-600" v-if="recourse.secretary?.decided_at">
              Decidido em {{ recourse.secretary.decided_at }}
            </div>
            <div class="mt-2 text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.secretary?.notes">
              {{ recourse.secretary.notes }}
            </div>
            <div v-if="recourse.secretary?.attachments?.length" class="mt-2">
              <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da Decisão do Secretário</h4>
              <ul class="space-y-1 max-h-40 overflow-y-auto">
                <li v-for="(f,i) in recourse.secretary.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Ações adicionais de fluxo -->
      <div class="space-y-3">
        <!-- RH: Encaminhar à DGP (removido: agora automático após parecer da Comissão) -->

        <!-- DGP: Registrar decisão (bloco separado) -->
        <div v-if="recourse.actions?.canDgpDecide" class="bg-white rounded-lg shadow-sm border">
          <div class="bg-gray-800 text-white p-4 rounded-t-lg">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.BadgeCheck class="w-5 h-5" /> Decisão da DGP
            </h2>
            <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
          </div>
          <div class="p-5 space-y-6">

            <!-- Decisão (radio cards) -->
            <div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                       :class="[dgpDecision === 'homologado' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400']">
                  <input type="radio" v-model="dgpDecision" value="homologado" class="text-gray-600" />
                  <icons.CheckCircle class="w-5 h-5" />
                  <span class="font-medium">DEFERIR</span>
                </label>
                <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                       :class="[dgpDecision === 'nao_homologado' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400']">
                  <input type="radio" v-model="dgpDecision" value="nao_homologado" class="text-gray-600" />
                  <icons.XCircle class="w-5 h-5" />
                  <span class="font-medium">INDEFERIR</span>
                </label>
              </div>
            </div>

            <!-- Justificativa -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Justificativa <span class="text-red-600" v-if="dgpDecision === 'nao_homologado'">(obrigatória)</span></label>
              <textarea v-model="dgpNotes" rows="4" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-gray-500 focus:border-transparent" placeholder="Descreva a justificativa para a decisão..."></textarea>
              <p v-if="dgpDecision === 'nao_homologado' && !dgpNotes.trim()" class="text-xs text-red-600 mt-1">Informe a justificativa para indeferir.</p>
            </div>

            <!-- Anexos -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Anexos (opcional)</label>
              <input ref="dgpDecisionFileInput" type="file" multiple @change="handleDgpDecisionFiles" class="hidden" />
              <button
                type="button"
                @click="triggerDgpDecisionFileInput"
                class="w-full px-4 py-3 border-2 border-dashed border-gray-300 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <icons.Paperclip class="w-5 h-5" /> Adicionar Anexos
              </button>
              <ul v-if="dgpDecisionAttachments.length" class="mt-3 space-y-2">
                <li v-for="(f,i) in dgpDecisionAttachments" :key="i" class="flex items-center justify-between bg-gray-50 p-2 rounded border text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <button type="button" class="text-red-600 hover:underline" @click="removeDgpDecisionAttachment(i)">remover</button>
                </li>
              </ul>
              <p class="text-xs text-gray-500 mt-2">Até 100MB por arquivo.</p>
              <!-- Anexos já vinculados à decisão da DGP (após salvar) -->
              <div v-if="recourse.dgp?.attachments?.length" class="mt-3 pt-3 border-t">
                <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da decisão já registrados</h4>
                <ul class="space-y-1 max-h-40 overflow-y-auto">
                  <li v-for="(f,i) in recourse.dgp.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                    <span class="truncate">{{ f.name }}</span>
                    <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Botão Salvar -->
            <div class="pt-4 border-t">
              <button
                @click="saveDgpDecision"
                class="px-8 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium flex items-center gap-2"
                :disabled="!dgpDecision || (dgpDecision === 'nao_homologado' && !dgpNotes.trim())"
                :class="{'opacity-50 cursor-not-allowed': !dgpDecision || (dgpDecision === 'nao_homologado' && !dgpNotes.trim())}"
              >
                <icons.Save class="w-4 h-4" /> Registrar Decisão da DGP
              </button>
            </div>
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

        <!-- Secretário: Registrar decisão (mesmo layout da DGP) -->
        <div v-if="recourse.actions?.canSecretaryDecide" class="bg-white rounded-lg shadow-sm border">
          <div class="bg-gray-800 text-white p-4 rounded-t-lg">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <icons.BadgeCheck class="w-5 h-5" /> Decisão do Secretário
            </h2>
            <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
          </div>
          <div class="p-5 space-y-6">

            <!-- Contexto: questionamento do servidor e parecer da DGP -->
            <div class="bg-gray-50 border border-gray-200 rounded p-3 space-y-3">
            <div v-if="recourse.second_instance?.text" class="">
              <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-1">
                <icons.MessageSquare class="w-4 h-4" /> Questionamento do Servidor (2ª instância)
              </div>
              <p class="text-sm text-gray-700 whitespace-pre-wrap bg-white border rounded p-2">{{ recourse.second_instance.text }}</p>
              <p v-if="recourse.second_instance?.requested_at" class="text-xs text-gray-500 mt-1">Solicitado em: {{ recourse.second_instance.requested_at }}</p>
            </div>

            </div>

            <!-- Decisão (radio cards) -->
            <div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                       :class="[secretaryDecisionChoice === 'homologado' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400']">
                  <input type="radio" v-model="secretaryDecisionChoice" value="homologado" class="text-gray-600" />
                  <icons.CheckCircle class="w-5 h-5" />
                  <span class="font-medium">DEFERIR</span>
                </label>
                <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                       :class="[secretaryDecisionChoice === 'nao_homologado' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400']">
                  <input type="radio" v-model="secretaryDecisionChoice" value="nao_homologado" class="text-gray-600" />
                  <icons.XCircle class="w-5 h-5" />
                  <span class="font-medium">INDEFERIR</span>
                </label>
              </div>
            </div>

            <!-- Justificativa -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Justificativa <span class="text-red-600" v-if="secretaryDecisionChoice === 'nao_homologado'">(obrigatória)</span></label>
              <textarea v-model="secretaryNotes" rows="4" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-gray-500 focus:border-transparent" placeholder="Descreva a justificativa para a decisão..."></textarea>
              <p v-if="secretaryDecisionChoice === 'nao_homologado' && !secretaryNotes.trim()" class="text-xs text-red-600 mt-1">Informe a justificativa para indeferir.</p>
            </div>

            <!-- Anexos -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Anexos (opcional)</label>
              <input ref="secretaryDecisionFileInput" type="file" multiple @change="handleSecretaryDecisionFiles" class="hidden" />
              <button
                type="button"
                @click="triggerSecretaryDecisionFileInput"
                class="w-full px-4 py-3 border-2 border-dashed border-gray-300 bg-gray-50 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors flex items-center justify-center gap-2 text-sm"
              >
                <icons.Paperclip class="w-5 h-5" /> Adicionar Anexos
              </button>
              <ul v-if="secretaryDecisionAttachments.length" class="mt-3 space-y-2">
                <li v-for="(f,i) in secretaryDecisionAttachments" :key="i" class="flex items-center justify-between bg-gray-50 p-2 rounded border text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <button type="button" class="text-red-600 hover:underline" @click="removeSecretaryDecisionAttachment(i)">remover</button>
                </li>
              </ul>
              <p class="text-xs text-gray-500 mt-2">Até 100MB por arquivo.</p>
              <!-- Anexos já vinculados à decisão do Secretário (após salvar) -->
              <div v-if="recourse.secretary?.attachments?.length" class="mt-3 pt-3 border-t">
                <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos da decisão já registrados</h4>
                <ul class="space-y-1 max-h-40 overflow-y-auto">
                  <li v-for="(f,i) in recourse.secretary.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                    <span class="truncate">{{ f.name }}</span>
                    <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Botão Salvar -->
            <div class="pt-4 border-t">
              <button
                @click="saveSecretaryDecision"
                class="px-8 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium flex items-center gap-2"
                :disabled="!secretaryDecisionChoice || (secretaryDecisionChoice === 'nao_homologado' && !secretaryNotes.trim())"
                :class="{'opacity-50 cursor-not-allowed': !secretaryDecisionChoice || (secretaryDecisionChoice === 'nao_homologado' && !secretaryNotes.trim())}"
              >
                <icons.Save class="w-4 h-4" /> Registrar Decisão do Secretário
              </button>
            </div>
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
                <h3 class="text-lg font-semibold text-gray-900">Encaminhar para a Comissão</h3>
                <p class="text-sm text-gray-600 mt-2">
                  {{ recourse.last_return ? 'Este recurso foi devolvido. Justifique o reenvio e anexe documentos de suporte se necessário.' : 'Você pode (opcionalmente) adicionar uma mensagem e anexos para contextualizar o envio à Comissão.' }}
                </p>
          <div class="mt-4">
                  <textarea v-model="forwardMessage" rows="4" class="w-full border rounded p-2" :placeholder="recourse.last_return ? 'Justificativa (obrigatória após devolução)...' : 'Mensagem (opcional)...'"></textarea>
          </div>
          <!-- Anexos do reenvio (opcional) -->
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="forwardFileInput" type="file" multiple @change="handleForwardFiles" class="hidden" />
            <button type="button" @click="triggerForwardFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.Paperclip class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 100MB por arquivo.</div>
            <ul v-if="forwardAttachments.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in forwardAttachments" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeForwardAttachment(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="showForwardModal = false">Cancelar</button>
            <button class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800" :disabled="!!recourse.last_return && !forwardMessage.trim()" @click="confirmForwardToCommission">Confirmar envio</button>
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
            <div class="mt-2 text-xs text-gray-500">Até 100MB por arquivo.</div>
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

  <!-- Bloco de devolução removido para visão da Comissão -->

      <!-- Modal de Devolução com Justificativa -->
  <div v-if="false && showReturnModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
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
              <div class="mt-2 text-xs text-gray-500">Até 100MB por arquivo.</div>
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

      <!-- Mensagem somente leitura para RH após encaminhamento -->
      <div v-else-if="isRhViewerPostForward" class="bg-white rounded-lg shadow-sm border p-6">
        <div class="text-center">
          <icons.Info class="w-10 h-10 text-gray-500 mx-auto mb-3" />
          <h3 class="text-lg font-semibold text-gray-800 mb-2">Recurso em Análise pela Comissão</h3>
          <p class="text-sm text-gray-600 max-w-xl mx-auto">Este recurso foi encaminhado para a Comissão. O RH pode acompanhar o histórico abaixo, mas não pode mais alterar o Presidente ou registrar parecer nesta etapa.</p>
        </div>
        <div class="mt-6">
          <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
            <icons.UserCircle2 class="w-4 h-4" /> Presidente Responsável
          </h4>
          <div v-if="recourse.responsiblePersons.length" class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg p-4">
            <div class="w-12 h-12 bg-gray-600 text-white rounded-full flex items-center justify-center text-lg font-medium">
              {{ recourse.responsiblePersons[0].name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-gray-900 truncate">{{ recourse.responsiblePersons[0].name }}</p>
              <p class="text-xs text-gray-500">Matrícula: {{ recourse.responsiblePersons[0].registration_number }}</p>
            </div>
            <span class="text-xs px-2 py-1 rounded bg-gray-200 text-gray-700 font-medium">Presidente</span>
          </div>
          <div v-else class="text-sm text-gray-500 bg-gray-50 border border-dashed border-gray-300 rounded p-4 flex items-center gap-2">
            <icons.AlertCircle class="w-4 h-4 text-gray-500" /> Nenhum Presidente definido (inconsistência). Defina antes de encaminhar próximos recursos.
          </div>
          <!-- Contexto do encaminhamento do RH (se houver) -->
          <div v-if="recourse.forward?.message || recourse.forward?.attachments?.length" class="mt-6 bg-gray-50 border border-gray-200 rounded p-4">
            <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 mb-1">
              <icons.Send class="w-4 h-4" /> Encaminhamento à Comissão (RH)
            </div>
            <p v-if="recourse.forward?.message" class="text-sm text-gray-700 whitespace-pre-wrap">{{ recourse.forward.message }}</p>
            <p v-if="recourse.forward?.at" class="text-xs text-gray-500 mt-1">Enviado em: {{ recourse.forward.at }}</p>
            <div v-if="recourse.forward?.attachments?.length" class="mt-2">
              <h5 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos do Encaminhamento</h5>
              <ul class="space-y-1 max-h-40 overflow-y-auto">
                <li v-for="(f,i) in recourse.forward.attachments" :key="i" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                  <span class="truncate">{{ f.name }}</span>
                  <a :href="f.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
                </li>
              </ul>
            </div>
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

    <!-- Modal de Confirmação para Remover Presidente -->
    <div v-if="showRemoveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <!-- Header do Modal -->
        <div class="p-6 border-b border-gray-200">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
              <icons.AlertTriangle class="w-6 h-6 text-red-600" />
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Remover Presidente</h3>
              <p class="text-sm text-gray-500">Esta ação não pode ser desfeita</p>
            </div>
          </div>
        </div>

        <!-- Conteúdo do Modal -->
        <div class="p-6">
          <div class="space-y-4">
            <p class="text-gray-700">
              Você está prestes a remover o Presidente da Comissão responsável por este recurso:
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
                  <p>O histórico de ações já realizadas será preservado. Apenas o acesso futuro para análise do recurso será removido enquanto não houver novo Presidente.</p>
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
            <icons.Trash2 class="w-4 h-4" />
            Remover Presidente
          </button>
        </div>
      </div>
    </div>

  </DashboardLayout>
</template>
