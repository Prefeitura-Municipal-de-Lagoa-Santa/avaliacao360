<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import SignaturePad from 'signature_pad';
import { route } from 'ziggy-js';

const props = defineProps<{
  evaluations: Array<{
    year: string;
    user: string;
    final_score: number;
    calc_final: string;
    calc_auto?: string;
    calc_chefia?: string;
    calc_equipe?: string;
    id: number | null;
    is_in_aware_period?: boolean;
    is_in_recourse_period?: boolean;
    has_recourse?: boolean;
    recourse_id?: number;
    recourse_status?: string;
    is_recourse_approved?: boolean;
    final_score_after_recourse?: number;
    calc_final_after_recourse?: string;
  }>;
  acknowledgments?: Array<{
    year: string;
    signature_base64: string;
    signed_at: string;
  }>;
}>();

const visibleEvaluations = computed(() =>
  (props.evaluations ?? []).filter(eva => eva && eva.is_in_aware_period)
);

const showModal = ref(false);
const selectedEvaluationYear = ref<string | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
let signaturePad: SignaturePad | null = null;

function openSignatureModal(evaluation: any) {
  selectedEvaluationYear.value = evaluation.year;
  showModal.value = true;
  nextTick(() => initSignature());
}

function closeModal() {
  showModal.value = false;
  signaturePad?.clear();
  selectedEvaluationYear.value = null;
}

function initSignature() {
  if (canvas.value) {
    signaturePad = new SignaturePad(canvas.value, {
      backgroundColor: '#fff',
    });
    resizeCanvas();
  }
}

function resizeCanvas() {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  const c = canvas.value!;
  c.width = c.offsetWidth * ratio;
  c.height = c.offsetHeight * ratio;
  c.getContext('2d')!.scale(ratio, ratio);
}

function clearSignature() {
  signaturePad?.clear();
}

function confirmSignature() {
  if (!signaturePad || signaturePad.isEmpty()) {
    alert('Por favor, assine antes de confirmar.');
    return;
  }

  const assinatura_base64 = signaturePad.toDataURL();
  const year = selectedEvaluationYear.value;

  if (!year) {
    alert('Erro: ano da avaliação não encontrado.');
    return;
  }

  router.post(route('evaluations.acknowledge', year), {
    signature_base64: assinatura_base64,
  }, {
    onSuccess: () => {
      closeModal();
      router.reload({ only: ['evaluations', 'acknowledgments'] });
    },
  });
}

function getAcknowledgment(year: string | number) {
  const result = (props.acknowledgments ?? []).find(a => String(a.year) === String(year));
  return result;
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourses.dashboard'));
  }
}
</script>

<template>
  <Head title="Minhas Avaliações Anuais" />
  <DashboardLayout page-title="Minhas Avaliações Anuais">
      <div class="detail-page-header">
        <h2 class="text-2xl font-bold text-gray-800">Minhas Avaliações</h2>
        <button @click="goBack" class="back-btn">
          <icons.ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
      <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
          <tr>
            <th class="px-6 py-3">Ano</th>
            <th class="px-6 py-3">Nota Final</th>
            <th class="px-6 py-3">Notas Parciais</th>
            <th class="px-6 py-3">Assinatura</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="visibleEvaluations.length === 0">
            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
              Nenhuma avaliação anual disponível para visualização neste momento.
            </td>
          </tr>
          <tr v-for="eva in visibleEvaluations" :key="eva.year" class="bg-white border-b hover:bg-gray-50">
            <td class="px-6 py-4">{{ eva.year }}</td>
            <td class="px-6 py-4">
              <div class="space-y-2">
                <!-- Nota original -->
                <div class="flex items-center" :class="{ 'opacity-50': eva.is_recourse_approved }">
                  <span class="text-2xl font-bold mr-2" :class="eva.is_recourse_approved ? 'text-gray-400 line-through' : 'text-blue-600'">
                    {{ eva.final_score }}
                  </span>
                  <span class="text-sm text-gray-500">pts</span>
                  <span v-if="eva.is_recourse_approved" class="ml-2 text-xs text-gray-500">(original)</span>
                </div>
                
                <!-- Nota após recurso (se aprovado) -->
                <div v-if="eva.is_recourse_approved && eva.final_score_after_recourse !== null" class="flex items-center">
                  <span class="text-2xl font-bold text-green-600 mr-2">
                    {{ eva.final_score_after_recourse }}
                  </span>
                  <span class="text-sm text-gray-500">pts</span>
                  <div class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                    Após Recurso
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <div class="space-y-1">
                <div v-if="eva.calc_auto" class="flex justify-between">
                  <span class="text-blue-600 font-medium">Autoavaliação:</span>
                  <span class="font-semibold">{{ eva.calc_auto }}</span>
                </div>
                <div v-if="eva.calc_chefia" class="flex justify-between" :class="{ 'opacity-50 line-through text-gray-400': eva.is_recourse_approved }">
                  <span class="font-medium" :class="eva.is_recourse_approved ? 'text-gray-400' : 'text-purple-600'">Chefe:</span>
                  <span class="font-semibold">{{ eva.calc_chefia }}</span>
                  <span v-if="eva.is_recourse_approved" class="text-xs text-red-500 ml-1">(anulada)</span>
                </div>
                <div v-if="eva.calc_equipe" class="flex justify-between">
                  <span class="text-indigo-600 font-medium">Equipe:</span>
                  <span class="font-semibold">{{ eva.calc_equipe }}</span>
                </div>
                
                <!-- Cálculo após recurso -->
                <div v-if="eva.is_recourse_approved && eva.calc_final_after_recourse" class="mt-3 pt-2 border-t border-green-200">
                  <div class="text-xs text-green-700 font-medium mb-1">Cálculo Após Recurso:</div>
                  <div class="text-sm text-green-800">{{ eva.calc_final_after_recourse }}</div>
                </div>
                
                <div v-if="!eva.calc_auto && !eva.calc_chefia && !eva.calc_equipe" class="text-gray-500 italic">
                  Notas parciais não disponíveis
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <template v-if="getAcknowledgment(eva.year)">
                <img
                  class="w-48 border rounded mt-2"
                  :src="getAcknowledgment(eva.year)!.signature_base64"
                  alt="Assinatura"
                />
                <p class="text-xs text-gray-500 mt-1">
                  Assinado em:
                  {{ new Date(new Date(getAcknowledgment(eva.year)!.signed_at).setDate(
                      new Date(getAcknowledgment(eva.year)!.signed_at).getDate() + 1
                  )).toLocaleDateString('pt-BR') }}
                </p>
              </template>
              <template v-else>
                <button
                  @click="openSignatureModal(eva)"
                  class="inline-flex items-center px-3 py-1 text-sm font-medium text-green-700 bg-green-50 rounded hover:bg-green-100"
                >
                  <icons.PenLineIcon class="size-4 mr-1" />
                  Assinar Ciência
                </button>
              </template>
            </td>
            <td class="px-6 py-4 text-right">
              <template v-if="getAcknowledgment(eva.year)">
                <Link
                  v-if="eva.is_in_recourse_period && !eva.has_recourse && eva.id"
                  :href="route('recourses.create', eva.id)"
                  class="inline-flex items-center px-3 py-1 text-sm font-medium text-rose-600 bg-rose-50 rounded hover:bg-rose-100 mr-2"
                >
                  <icons.FileTextIcon class="size-4 mr-1" />
                  Abrir Recurso
                </Link>
                <Link
                  v-else-if="eva.has_recourse && eva.recourse_id"
                  :href="route('recourses.show', eva.recourse_id)"
                  class="inline-flex items-center px-3 py-1 text-sm font-medium text-yellow-600 bg-yellow-50 rounded hover:bg-yellow-100 mr-2"
                >
                  <icons.MessageSquareIcon class="size-4 mr-1" />
                  Acompanhar Recurso
                </Link>
              </template>
              <Link
                v-if="eva.id"
                :href="route('evaluations.details', eva.id)"
                class="inline-flex items-center px-3 py-1 text-sm font-medium text-indigo-600 bg-indigo-50 rounded hover:bg-indigo-100"
              >
                <icons.FileTextIcon class="size-4 mr-1" />
                Ver mais
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal de assinatura -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white w-full max-w-lg rounded-lg shadow-lg p-6 relative">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Assinatura de Ciência</h2>
        <p class="text-sm text-gray-600 text-right mb-4">{{ new Date().toLocaleDateString('pt-BR') }}</p>

        <canvas ref="canvas" class="border border-gray-400 rounded-md w-full h-48 bg-white"></canvas>
        <p class="text-center text-gray-700 mt-2">Assinatura do Servidor</p>

        <div class="flex justify-between mt-4">
          <button @click="clearSignature" class="text-sm text-red-600 hover:underline">Limpar Assinatura</button>
          <div class="flex gap-2">
            <button @click="closeModal" class="px-4 py-2 text-sm bg-gray-100 rounded hover:bg-gray-200">Cancelar</button>
            <button @click="confirmSignature" class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">
              Confirmar Assinatura
            </button>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
