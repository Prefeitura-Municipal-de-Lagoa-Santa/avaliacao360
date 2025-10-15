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
    second_instance?: { enabled: boolean; requested_at?: string | null; text?: string | null };
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
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow space-y-6">
      <!-- Cabeçalho -->
      <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">
          Recurso da Avaliação de {{ recourse.person.name }} - {{ recourse.evaluation.year }}
        </h2>
        <button @click="goBack" class="back-btn">
          <icons.ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <!-- Status/Etapa -->
      <div class="space-y-2">
        <div class="flex gap-2 items-center">
          <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700 border">
            Etapa: {{ recourse.stage?.toUpperCase() || '—' }}
          </span>
          <span class="text-xs px-3 py-1 rounded-full bg-gray-100 text-gray-700 border">
            Status: {{ recourse.status?.toUpperCase() || '—' }}
          </span>
        </div>
        <p class="text-sm text-gray-700 font-medium">Status do Recurso:</p>

        <!-- Prioridade: decisão da DGP (1ª instância) -->
        <div v-if="recourse.dgp && recourse.dgp.decision && !(recourse.secretary && recourse.secretary.decision)" class="text-indigo-700 bg-indigo-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.Stamp class="w-5 h-5" />
          <span>
            Decisão da DGP registrada
            <span v-if="recourse.dgp && recourse.dgp.decision">: <strong>{{ recourse.dgp.decision.toUpperCase() }}</strong></span>
            <span v-if="recourse.actions && recourse.actions.canAcknowledgeFirst"> — Aguardando sua ciência (1ª instância).</span>
            <span v-else> — Aguardando trâmites do RH.</span>
          </span>
        </div>

        <div v-else-if="recourse.status === 'aberto'" class="text-yellow-700 bg-yellow-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.AlertCircleIcon class="w-5 h-5" />
          <span>Aguardando análise da comissão.</span>
        </div>

        <div v-else-if="recourse.status === 'em_analise'" class="text-blue-700 bg-blue-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.LoaderIcon class="w-5 h-5 animate-spin" />
          <span>Recurso em análise. Em breve você receberá uma resposta oficial.</span>
        </div>

        <div v-else-if="recourse.secretary && recourse.secretary.decision" class="text-purple-700 bg-purple-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.Stamp class="w-5 h-5" />
          <span>
            Decisão do Secretário registrada
            <span v-if="recourse.secretary && recourse.secretary.decision">: <strong>{{ recourse.secretary.decision.toUpperCase() }}</strong></span>
            <span v-if="recourse.actions && recourse.actions.canAcknowledgeSecond"> — Aguardando sua ciência (2ª instância).</span>
          </span>
        </div>

        <div v-else-if="recourse.status === 'respondido'" class="text-green-700 bg-green-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.CheckCircleIcon class="w-5 h-5" />
          <span>Recurso respondido. Veja abaixo o parecer da comissão.</span>
        </div>

        <div v-else-if="recourse.status === 'indeferido'" class="text-red-700 bg-red-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.XCircleIcon class="w-5 h-5" />
          <span>Recurso indeferido. Veja a justificativa abaixo.</span>
        </div>
      </div>

      <!-- Nota final após decisão (Secretário tem precedência) -->
      <div v-if="recourse.final_score !== null && recourse.final_score !== undefined" class="mt-4">
        <div class="flex items-center gap-2 text-sm text-gray-700 font-medium mb-1">
          <icons.BadgeCheck class="w-4 h-4 text-gray-700" />
          <span>
            {{ recourse.secretary?.decision ? 'Nota Final após decisão do Secretário' : (recourse.dgp?.decision ? 'Nota Final após decisão da DGP' : 'Nota Final') }}
          </span>
        </div>
        <div class="flex items-center gap-2 bg-gray-50 border rounded px-4 py-3 inline-flex">
          <span class="text-2xl font-bold text-blue-700">{{ recourse.final_score?.toFixed(1) }}</span>
          <span class="text-sm text-gray-500">pts</span>
        </div>
      </div>

      <!-- Detalhes da decisão final -->
      <div v-if="recourse.secretary?.decision" class="mt-3">
        <div class="text-sm text-gray-700 font-medium mb-1 flex items-center gap-2">
          <icons.Stamp class="w-4 h-4" /> Decisão do Secretário
        </div>
        <div class="bg-gray-50 border rounded p-3">
          <div class="text-sm text-gray-800">
            <span class="font-semibold">{{ recourse.secretary.decision.toUpperCase() }}</span>
            <span v-if="recourse.secretary.decided_at" class="text-gray-500 ml-2">({{ recourse.secretary.decided_at }})</span>
          </div>
          <div v-if="recourse.secretary?.notes" class="text-sm text-gray-700 whitespace-pre-wrap mt-2 bg-white border rounded p-2">{{ recourse.secretary.notes }}</div>
        </div>
      </div>

      <!-- Exibe questionamento enviado para 2ª instância -->
      <div v-if="recourse.second_instance?.enabled && recourse.second_instance?.text" class="mt-6">
        <h3 class="font-semibold text-sm text-gray-700 flex items-center gap-2">
          <icons.MessageSquareIcon class="w-4 h-4" /> Questionamento enviado ao Secretário (2ª instância)
        </h3>
        <p class="text-gray-800 whitespace-pre-wrap mt-1 bg-indigo-50 border border-indigo-200 p-3 rounded">
          {{ recourse.second_instance.text }}
        </p>
        <p v-if="recourse.second_instance?.requested_at" class="text-xs text-gray-500 mt-1">
          Enviado em: {{ recourse.second_instance.requested_at }}
        </p>
      </div>

      <!-- Texto enviado -->
      <div class="mt-6">
        <h3 class="font-semibold text-sm text-gray-700">Texto enviado por você:</h3>
        <p class="text-gray-800 whitespace-pre-wrap mt-1 bg-gray-50 border p-3 rounded">
          {{ recourse.text }}
        </p>
      </div>

      <!-- Anexos -->
      <div v-if="recourse.attachments.length" class="mt-6">
        <h3 class="font-semibold text-sm text-gray-700 mb-1">Anexos enviados:</h3>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(file, index) in recourse.attachments" :key="index">
            <a
              href="#"
              @click.prevent="openFile(file)"
              class="text-blue-600 hover:underline flex items-center gap-1"
            >
              <icons.PaperclipIcon class="w-4 h-4" />
              {{ file.name }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Resposta -->
      <div v-if="recourse.response" class="mt-6">
        <h3 class="font-semibold text-sm text-gray-700">Resposta da Comissão:</h3>
        <p class="text-gray-800 whitespace-pre-wrap mt-1 bg-green-50 border border-green-200 p-3 rounded">
          {{ recourse.response }}
        </p>
        <p class="text-xs text-gray-500 mt-1">
          Respondido em: {{ recourse.responded_at }}
        </p>
        
        <!-- Anexos da resposta -->
        <div v-if="recourse.responseAttachments && recourse.responseAttachments.length" class="mt-4">
          <h4 class="font-semibold text-sm text-gray-700 mb-1">Anexos da Resposta:</h4>
          <ul class="list-disc list-inside space-y-1">
            <li v-for="(file, index) in recourse.responseAttachments" :key="index">
              <a
                href="#"
                @click.prevent="openFile(file)"
                class="text-blue-600 hover:underline flex items-center gap-1"
              >
                <icons.PaperclipIcon class="w-4 h-4" />
                {{ file.name }}
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- Ações do Servidor -->
      <div class="mt-6 space-y-3">
        <div v-if="recourse.actions?.canAcknowledgeFirst" class="flex justify-end">
          <button
            class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm flex items-center gap-2"
            @click="openAckModal('first')"
          >
            <icons.CheckCircleIcon class="w-4 h-4" /> Registrar ciência (1ª instância)
          </button>
        </div>
        <div v-if="recourse.actions?.canRequestSecondInstance" class="flex justify-end">
          <button
            class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 text-sm flex items-center gap-2"
            @click="showSecondModal = true"
          >
            <icons.MessageSquareIcon class="w-4 h-4" /> Questionar ao Secretário (2ª instância)
          </button>
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
      <div class="mt-4 space-y-4">
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
      <div v-if="recourse.logs?.length" class="mt-10">
        <h3 class="font-semibold text-sm text-gray-700 mb-4">Linha do Tempo do Recurso:</h3>
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
