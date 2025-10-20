<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, nextTick } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import SignaturePad from 'signature_pad';

const props = defineProps<{
  recourse: {
    id: number;
    text: string;
    status: string;
    stage?: string;
    response?: string;
    responded_at?: string;
    final_score?: number | null;
    second_instance?: { 
      enabled: boolean; 
      requested_at?: string | null; 
      text?: string | null;
      deadline_at?: string | null;
      deadline_days?: number;
      is_deadline_expired?: boolean;
    };
    dgp?: { decision?: string | null; decided_at?: string | null; notes?: string | null };
    secretary?: { decision?: string | null; decided_at?: string | null; notes?: string | null };
    attachments: Array<{ name: string; url: string }>;
    responseAttachments?: Array<{ name: string; url: string }>;
    first_ack_at?: string | null;
    first_ack_signature_base64?: string | null;
    evaluation: {
      id: number;
      year: string;
    };
    person: { name: string };
    second_ack_at?: string | null;
    second_ack_signature_base64?: string | null;
    logs: Array<{ status: string; message: string; created_at: string }>;
    actions?: {
      canAcknowledgeFirst: boolean;
      canRequestSecondInstance: boolean;
      canAcknowledgeSecond: boolean;
    };
  };
  permissions?: { isRH: boolean; isComissao: boolean; isRequerente: boolean };
}>();

// Labels amigáveis para etapas (mesmo padrão da tela de revisão)
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

// Formatação do prazo da segunda instância
const formatSecondInstanceDeadline = (deadline?: string | null) => {
  if (!deadline) return '';
  try {
    const date = new Date(deadline);
    return date.toLocaleDateString('pt-BR', { 
      day: '2-digit', 
      month: '2-digit', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch {
    return deadline;
  }
};

const secondInstanceText = ref('');
const showSecondModal = ref(false);
const secondFiles = ref<File[]>([]);
const secondFileInput = ref<HTMLInputElement | null>(null);

// Assinatura para ciência (1ª e 2ª instância)
const showAckModal = ref(false);
const ackType = ref<'first' | 'second' | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
let signaturePad: SignaturePad | null = null;

function openAckModal(type: 'first' | 'second') {
  ackType.value = type;
  showAckModal.value = true;
  nextTick(() => initSignature());
}
function closeAckModal() {
  showAckModal.value = false;
  signaturePad?.clear();
  ackType.value = null;
}
function initSignature() {
  if (!canvas.value) return;
  // Ajustar resolução para telas de alta densidade
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  const c = canvas.value;
  c.width = c.offsetWidth * ratio;
  c.height = c.offsetHeight * ratio;
  const ctx = c.getContext('2d');
  if (ctx) ctx.scale(ratio, ratio);
  signaturePad = new SignaturePad(c, { backgroundColor: '#fff' });
}
function clearSignature() {
  signaturePad?.clear();
}
function confirmAckSignature() {
  if (!signaturePad || signaturePad.isEmpty() || !ackType.value) {
    alert('Por favor, assine antes de confirmar.');
    return;
  }
  const assinatura_base64 = signaturePad.toDataURL();
  const routeName = ackType.value === 'first' ? 'recourses.acknowledgeFirst' : 'recourses.acknowledgeSecond';
  router.post(route(routeName, props.recourse.id), {
    signature_base64: assinatura_base64,
  }, {
    onSuccess: () => {
      closeAckModal();
      // Recarrega apenas os dados do recurso para refletir assinatura e datas
      router.reload({ only: ['recourse'] });
    },
  });
}

function triggerSecondFileInput() {
  secondFileInput.value?.click();
}
function handleSecondFiles(e: Event) {
  const files = (e.target as HTMLInputElement).files;
  if (files) {
    secondFiles.value.push(...Array.from(files));
    // reset input so the same file can be re-selected if removed
    (e.target as HTMLInputElement).value = '';
  }
}
function removeSecondFile(i: number) {
  secondFiles.value.splice(i, 1);
}
function submitSecondInstance() {
  // Defesa extra no cliente: se DGP homologou, não permitir abrir 2ª instância
  if (props.recourse?.dgp?.decision === 'homologado') {
    alert('Não é possível abrir a 2ª instância após deferimento/homologação da DGP.');
    return;
  }
  
  // Verificar se o prazo ainda está válido
  if (props.recourse?.second_instance?.is_deadline_expired) {
    alert('O prazo para solicitar a 2ª instância já expirou.');
    return;
  }
  
  if (!secondInstanceText.value.trim()) return;
  const fd = new FormData();
  fd.append('text', secondInstanceText.value);
  secondFiles.value.forEach((f) => fd.append('second_instance_attachments[]', f));
  router.post(route('recourses.requestSecondInstance', props.recourse.id), fd, {
    forceFormData: true,
    onSuccess: () => {
      showSecondModal.value = false;
      secondInstanceText.value = '';
      secondFiles.value = [];
    },
  });
}

function openFile(file: { name: string; url: string }) {
  const link = document.createElement('a');
  link.href = file.url;
  link.setAttribute('download', file.name);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourse'));
  }
}
</script>

<template>
  <Head :title="`Recurso de ${recourse.person.name}`" />
  <DashboardLayout :page-title="`Recurso de ${recourse.person.name}`">
    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Cabeçalho -->
      <div class="bg-white p-6 rounded shadow border">
        <div class="flex justify-between items-center">
          <h2 class="text-2xl font-bold text-gray-900">Recurso de {{ recourse.person.name }} - {{ recourse.evaluation.year }}</h2>
          <button @click="goBack" class="back-btn inline-flex items-center whitespace-nowrap">
            <icons.ArrowLeftIcon class="size-4 mr-2" /> Voltar
          </button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
          <span class="px-2.5 py-1 rounded-full border bg-gray-50 text-gray-700">Etapa: {{ formatStageLabel(recourse.stage) }}</span>
          <span class="px-2.5 py-1 rounded-full border bg-gray-50 text-gray-700">Status: {{ recourse.status?.toUpperCase() || '—' }}</span>
          <span class="px-2.5 py-1 rounded-full border" :class="recourse.second_instance?.enabled ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-green-50 text-green-700 border-green-200'">
            {{ recourse.second_instance?.enabled ? '2ª instância' : '1ª instância' }}
          </span>
        </div>
      </div>

      <!-- Situação Atual -->
      <div class="bg-white rounded shadow border">
        <div class="bg-gray-800 text-white p-4 rounded-t">
          <h3 class="text-lg font-semibold flex items-center gap-2"><icons.Info class="w-5 h-5" /> Situação Atual</h3>
          <p class="text-sm text-gray-300 mt-1">Acompanhamento do andamento e próximas ações</p>
        </div>
        <div class="p-4 space-y-2 text-sm">
          <div v-if="recourse.dgp?.decision && !recourse.secretary?.decision" class="text-indigo-800 bg-indigo-50 border border-indigo-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.Stamp class="w-4 h-4" />
            <span>
              Decisão da DGP registrada: <strong>{{ recourse.dgp.decision.toUpperCase() }}</strong>
              <span v-if="recourse.actions?.canAcknowledgeFirst"> — Aguardando sua ciência (1ª instância).</span>
              <span v-else> — Aguardando trâmites do RH.</span>
            </span>
          </div>
          <div v-else-if="recourse.status === 'aberto'" class="text-amber-800 bg-amber-50 border border-amber-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.AlertCircle class="w-4 h-4" /> Aguardando análise da Comissão.
          </div>
          <div v-else-if="recourse.status === 'em_analise'" class="text-blue-800 bg-blue-50 border border-blue-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.Loader2 class="w-4 h-4 animate-spin" /> Recurso em análise. Em breve você receberá uma resposta oficial.
          </div>
          <div v-else-if="recourse.secretary?.decision" class="text-purple-800 bg-purple-50 border border-purple-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.Stamp class="w-4 h-4" /> Decisão do Secretário registrada: <strong>{{ recourse.secretary.decision.toUpperCase() }}</strong>
            <span v-if="recourse.actions?.canAcknowledgeSecond"> — Aguardando sua ciência (2ª instância).</span>
          </div>
          <div v-else-if="recourse.status === 'respondido'" class="text-green-800 bg-green-50 border border-green-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.CheckCircle class="w-4 h-4" /> Recurso deferido pela Comissão. Veja o parecer abaixo.
          </div>
          <div v-else-if="recourse.status === 'indeferido'" class="text-red-800 bg-red-50 border border-red-200 px-3 py-2 rounded flex items-center gap-2">
            <icons.XCircle class="w-4 h-4" /> Recurso indeferido pela Comissão. Veja a justificativa abaixo.
          </div>
        </div>
      </div>

      <!-- Nota final após decisão (Secretário tem precedência) -->
      <div v-if="recourse.final_score !== null && recourse.final_score !== undefined" class="bg-white rounded shadow border">
        <div class="bg-gray-700 text-white p-4 rounded-t">
          <h3 class="text-lg font-semibold flex items-center gap-2"><icons.BadgeCheck class="w-5 h-5" /> Nota Final</h3>
          <p class="text-sm text-gray-300 mt-1">{{ recourse.secretary?.decision ? 'Após decisão do Secretário' : (recourse.dgp?.decision ? 'Após decisão da DGP' : 'Calculada') }}</p>
        </div>
        <div class="p-4">
          <div class="inline-flex items-center gap-2 bg-gray-50 border rounded px-4 py-3">
            <span class="text-2xl font-bold text-blue-700">{{ recourse.final_score?.toFixed(1) }}</span>
            <span class="text-sm text-gray-500">pts</span>
          </div>
        </div>
      </div>

      <!-- Decisão da DGP (1ª instância) -->
      <div v-if="recourse.dgp?.decision" class="bg-white rounded shadow border">
        <div class="bg-gray-800 text-white p-4 rounded-t">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold flex items-center gap-2"><icons.Stamp class="w-5 h-5" /> Decisão da DGP</h3>
            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border"
                  :class="recourse.dgp.decision === 'homologado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-700 border-red-300'">
              {{ recourse.dgp.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
            </span>
          </div>
          <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
        </div>
        <div class="p-4 space-y-2">
          <div class="text-xs text-gray-600" v-if="recourse.dgp?.decided_at">
            <icons.Clock class="w-3 h-3 inline mr-1" /> Decidido em {{ recourse.dgp.decided_at }}
          </div>
          <div class="text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.dgp?.notes">
            {{ recourse.dgp.notes }}
          </div>
        </div>
      </div>

      <!-- Decisão do Secretário (2ª instância) -->
      <div v-if="recourse.secretary?.decision" class="bg-white rounded shadow border">
        <div class="bg-gray-800 text-white p-4 rounded-t">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold flex items-center gap-2"><icons.BadgeCheck class="w-5 h-5" /> Decisão do Secretário</h3>
            <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border"
                  :class="recourse.secretary.decision === 'homologado' ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-red-100 text-red-700 border-red-300'">
              {{ recourse.secretary.decision === 'homologado' ? 'HOMOLOGADO' : 'NÃO HOMOLOGADO' }}
            </span>
          </div>
          <p class="text-sm text-gray-300 mt-1">Homologação ou não do parecer da Comissão</p>
        </div>
        <div class="p-4 space-y-2">
          <div class="text-xs text-gray-600" v-if="recourse.secretary?.decided_at">
            <icons.Clock class="w-3 h-3 inline mr-1" /> Decidido em {{ recourse.secretary.decided_at }}
          </div>
          <div class="text-sm text-gray-700 whitespace-pre-wrap" v-if="recourse.secretary?.notes">
            {{ recourse.secretary.notes }}
          </div>
        </div>
      </div>

      <!-- Exibe questionamento enviado para 2ª instância -->
      <div v-if="recourse.second_instance?.enabled && recourse.second_instance?.text" class="bg-white rounded shadow border">
        <div class="bg-gray-700 text-white p-4 rounded-t">
          <h3 class="text-lg font-semibold flex items-center gap-2"><icons.MessageSquare class="w-5 h-5" /> Questionamento ao Secretário (2ª instância)</h3>
          <p class="text-sm text-gray-300 mt-1">Texto enviado por você</p>
        </div>
        <div class="p-4">
          <p class="text-gray-800 whitespace-pre-wrap bg-indigo-50 border border-indigo-200 p-3 rounded">{{ recourse.second_instance.text }}</p>
          <p v-if="recourse.second_instance?.requested_at" class="text-xs text-gray-500 mt-2">Enviado em: {{ recourse.second_instance.requested_at }}</p>
        </div>
      </div>

      <!-- Recurso Apresentado -->
      <div class="bg-white rounded shadow border">
        <div class="bg-gray-700 text-white p-4 rounded-t">
          <h3 class="text-lg font-semibold flex items-center gap-2"><icons.MessageSquare class="w-5 h-5" /> Recurso Apresentado</h3>
          <p class="text-sm text-gray-300 mt-1">Texto e anexos enviados por você</p>
        </div>
        <div class="p-4 space-y-4">
          <div>
            <h4 class="text-sm font-semibold text-gray-800 mb-1 flex items-center gap-2"><icons.FileText class="w-4 h-4" /> Justificativa do Recurso</h4>
            <p class="text-gray-800 whitespace-pre-wrap bg-gray-50 border p-3 rounded">{{ recourse.text }}</p>
          </div>
          <div v-if="recourse.attachments.length">
            <h4 class="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2"><icons.Paperclip class="w-4 h-4" /> Anexos enviados</h4>
            <ul class="space-y-1">
              <li v-for="(file, index) in recourse.attachments" :key="index" class="flex items-center gap-2 p-2 bg-white border rounded hover:bg-gray-50 cursor-pointer" @click="openFile(file)">
                <icons.File class="w-4 h-4 text-gray-500" />
                <span class="text-sm text-gray-700 hover:underline">{{ file.name }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Parecer da Comissão -->
      <div v-if="recourse.response" class="bg-white rounded shadow border">
        <div class="bg-gray-800 text-white p-4 rounded-t">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold flex items-center gap-2"><icons.Scale class="w-5 h-5" /> Parecer da Comissão</h3>
            <span class="text-[10px] tracking-wide font-semibold px-2 py-1 rounded-full border"
                  :class="recourse.status === 'respondido' ? 'bg-green-100 text-green-700 border-green-300' : (recourse.status === 'indeferido' ? 'bg-red-100 text-red-700 border-red-300' : 'bg-gray-100 text-gray-700 border-gray-300')">
              {{ recourse.status === 'respondido' ? 'DEFERIDO' : (recourse.status === 'indeferido' ? 'INDEFERIDO' : 'ANÁLISE CONCLUÍDA') }}
            </span>
          </div>
          <p class="text-sm text-gray-300 mt-1">Texto fundamentado registrado pela Comissão</p>
        </div>
        <div class="p-4 space-y-3">
          <div class="bg-white border rounded p-3 text-sm text-gray-800 whitespace-pre-wrap">{{ recourse.response }}</div>
          <div class="text-xs text-gray-600" v-if="recourse.responded_at">
            <icons.Clock class="w-3 h-3 inline mr-1" /> Respondido em {{ recourse.responded_at }}
          </div>
          <div v-if="recourse.responseAttachments?.length" class="pt-2 border-t">
            <h4 class="text-xs font-medium text-gray-700 mb-1 flex items-center gap-1"><icons.Paperclip class="w-3 h-3" /> Anexos do Parecer</h4>
            <ul class="space-y-1 max-h-40 overflow-y-auto">
              <li v-for="(file, index) in recourse.responseAttachments" :key="index" class="flex items-center justify-between bg-white border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ file.name }}</span>
                <a :href="file.url" target="_blank" class="text-gray-700 hover:underline">abrir</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Ações do Servidor -->
      <div class="bg-white rounded shadow border p-4 space-y-3">
        <div v-if="recourse.actions?.canAcknowledgeFirst" class="flex justify-end">
          <button
            class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm flex items-center gap-2"
            @click="openAckModal('first')"
          >
            <icons.CheckCircleIcon class="w-4 h-4" /> Registrar ciência (1ª instância)
          </button>
        </div>
        <div v-if="recourse.actions?.canRequestSecondInstance && recourse.dgp?.decision !== 'homologado'" class="space-y-2">
          <!-- Informações sobre o prazo -->
          <div v-if="recourse.second_instance?.deadline_at" class="p-3 rounded-lg" 
               :class="recourse.second_instance?.is_deadline_expired ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200'">
            <div class="flex items-start gap-2" 
                 :class="recourse.second_instance?.is_deadline_expired ? 'text-red-800' : 'text-blue-800'">
              <icons.Clock class="w-4 h-4 mt-0.5" />
              <div class="text-sm">
                <p v-if="recourse.second_instance?.is_deadline_expired" class="font-medium">
                  <strong>Prazo expirado:</strong> O prazo para solicitar a 2ª instância expirou em {{ formatSecondInstanceDeadline(recourse.second_instance?.deadline_at) }}.
                </p>
                <p v-else class="font-medium">
                  <strong>Prazo para 2ª instância:</strong> Você tem {{ recourse.second_instance?.deadline_days }} dias após a ciência para questionar ao Secretário.
                </p>
                <p v-if="!recourse.second_instance?.is_deadline_expired" class="mt-1 text-xs opacity-75">
                  Prazo válido até: {{ formatSecondInstanceDeadline(recourse.second_instance?.deadline_at) }}
                </p>
              </div>
            </div>
          </div>
          
          <!-- Botão para solicitar segunda instância -->
          <div class="flex justify-end">
            <button
              class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 text-sm flex items-center gap-2"
              :disabled="recourse.second_instance?.is_deadline_expired"
              :class="recourse.second_instance?.is_deadline_expired ? 'opacity-50 cursor-not-allowed' : ''"
              @click="recourse.second_instance?.is_deadline_expired ? null : (showSecondModal = true)"
            >
              <icons.MessageSquareIcon class="w-4 h-4" /> Questionar ao Secretário (2ª instância)
            </button>
          </div>
        </div>
        <div v-if="recourse.actions?.canAcknowledgeSecond" class="flex justify-end">
          <button
            class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm flex items-center gap-2"
            @click="openAckModal('second')"
          >
            <icons.CheckCircleIcon class="w-4 h-4" /> Registrar ciência (2ª instância)
          </button>
        </div>
      </div>

      <!-- Assinaturas registradas -->
      <div class="bg-white rounded shadow border p-4 space-y-4">
        <div v-if="recourse.first_ack_signature_base64" class="bg-gray-50 border rounded p-3">
          <div class="text-sm text-gray-700 font-medium mb-2 flex items-center gap-2">
            <icons.PenLineIcon class="w-4 h-4" /> Ciência registrada (1ª instância)
          </div>
          <img :src="recourse.first_ack_signature_base64" alt="Assinatura 1ª instância" class="w-48 border rounded" />
          <p v-if="recourse.first_ack_at" class="text-xs text-gray-500 mt-1">Assinado em: {{ recourse.first_ack_at }}</p>
        </div>
        <div v-if="recourse.second_ack_signature_base64" class="bg-gray-50 border rounded p-3">
          <div class="text-sm text-gray-700 font-medium mb-2 flex items-center gap-2">
            <icons.PenLineIcon class="w-4 h-4" /> Ciência registrada (2ª instância)
          </div>
          <img :src="recourse.second_ack_signature_base64" alt="Assinatura 2ª instância" class="w-48 border rounded" />
          <p v-if="recourse.second_ack_at" class="text-xs text-gray-500 mt-1">Assinado em: {{ recourse.second_ack_at }}</p>
        </div>
      </div>

      <!-- Modal: Solicitar 2ª instância -->
      <div v-if="showSecondModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
          <h3 class="text-lg font-semibold text-gray-900">Questionar ao Secretário (2ª instância)</h3>
          <p class="text-sm text-gray-600 mt-2">Descreva seu questionamento e, se necessário, anexe documentos de apoio.</p>
          <div class="mt-4">
            <textarea v-model="secondInstanceText" rows="4" class="w-full border rounded p-2" placeholder="Escreva aqui seu questionamento ao Secretário..."></textarea>
          </div>
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (opcional)</label>
            <input ref="secondFileInput" type="file" multiple @change="handleSecondFiles" class="hidden" />
            <button type="button" @click="triggerSecondFileInput" class="inline-flex items-center gap-2 px-3 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm">
              <icons.PaperclipIcon class="w-4 h-4" /> Selecionar arquivos
            </button>
            <div class="mt-2 text-xs text-gray-500">Até 100MB por arquivo.</div>
            <ul v-if="secondFiles.length" class="mt-2 space-y-1">
              <li v-for="(f,i) in secondFiles" :key="i" class="flex items-center justify-between bg-gray-50 border rounded px-2 py-1 text-xs">
                <span class="truncate">{{ f.name }}</span>
                <button type="button" class="ml-2 text-red-600 hover:underline" @click="removeSecondFile(i)">remover</button>
              </li>
            </ul>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="showSecondModal = false">Cancelar</button>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700" :disabled="!secondInstanceText.trim()" @click="submitSecondInstance">Enviar</button>
          </div>
        </div>
      </div>

      <!-- Linha do tempo -->
      <div v-if="recourse.logs?.length" class="bg-white rounded shadow border p-4">
        <h3 class="font-semibold text-sm text-gray-800 mb-4 flex items-center gap-2"><icons.Clock class="w-4 h-4" /> Linha do Tempo do Recurso</h3>
        <ol class="relative border-l border-gray-300 ml-2">
          <li
            v-for="(log, index) in recourse.logs"
            :key="index"
            class="mb-6 ml-4"
          >
            <div
              class="absolute w-3 h-3 rounded-full -left-1.5 border border-white"
              :class="{
                'bg-yellow-500': log.status === 'aberto',
                'bg-blue-500': log.status === 'em_analise',
                'bg-green-600': log.status === 'respondido',
                'bg-red-600': log.status === 'indeferido',
                'bg-gray-400': !['aberto', 'em_analise', 'respondido', 'indeferido'].includes(log.status),
              }"
            ></div>
            <h4 class="text-sm font-semibold text-gray-800">
              {{ log.status.replace('_', ' ').toUpperCase() }}
            </h4>
            <p class="text-sm text-gray-600 whitespace-pre-line">
              {{ log.message || 'Atualização de status' }}
            </p>
            <span class="text-xs text-gray-500 block mt-1">
              {{ log.created_at }}
            </span>
          </li>
        </ol>
      </div>

      <!-- Aviso se ainda não respondeu -->
      <div v-if="!recourse.response" class="mt-8 text-sm text-gray-500">
        Você será notificado nesta tela e por e-mail assim que a comissão responder seu recurso.
      </div>
    </div>

    <!-- Modal: Assinar Ciência (1ª ou 2ª instância) -->
    <div v-if="showAckModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <icons.PenLineIcon class="w-5 h-5" /> Assinar Ciência
          <span class="text-sm text-gray-500 ml-2" v-if="ackType === 'first'">1ª instância</span>
          <span class="text-sm text-gray-500 ml-2" v-else-if="ackType === 'second'">2ª instância</span>
        </h3>
        <p class="text-sm text-gray-600 mt-2">Assine abaixo para registrar sua ciência.</p>
        <div class="mt-4 border rounded bg-white">
          <canvas ref="canvas" class="w-full h-40"></canvas>
        </div>
        <div class="mt-2 text-xs text-gray-500">Use o mouse (ou toque) para assinar. Limpe se necessário.</div>
        <div class="mt-4 flex justify-between">
          <button class="px-3 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-sm" @click="clearSignature">Limpar</button>
          <div class="flex gap-2">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200" @click="closeAckModal">Cancelar</button>
            <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700" @click="confirmAckSignature">Confirmar</button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
