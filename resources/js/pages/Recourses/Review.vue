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
    response: string | null;
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
    };
    logs: Array<{ status: string; message: string | null; created_at: string }>;
  };
  availablePersons: Array<{ id: number; name: string; registration_number: string }>;
  canManageAssignees: boolean;
}>();

const response = ref('');
const decision = ref<'respondido' | 'indeferido' | null>(null);
const responseAttachments = ref<File[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);
const isAnalyzing = ref(props.recourse.status === 'em_analise');

// Estados para gerenciar responsáveis
const selectedPersonId = ref<number | null>(null);
const showAssignForm = ref(false);

function markAsAnalyzing() {
  router.post(route('recourses.markAnalyzing', props.recourse.id), {}, {
    preserveScroll: true,
    onSuccess: () => {
      isAnalyzing.value = true;
    },
  });
}

function openFile(file: { name: string; url: string }) {
  if (!isAnalyzing.value) markAsAnalyzing();

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

  const formData = new FormData();
  formData.append('status', decision.value);
  formData.append('response', response.value);
  
  // Adiciona os anexos de resposta
  responseAttachments.value.forEach((file, index) => {
    formData.append(`response_attachments[${index}]`, file);
  });

  router.post(route('recourses.respond', props.recourse.id), formData, {
    forceFormData: true,
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
  if (!confirm('Tem certeza que deseja remover este responsável?')) {
    return;
  }

  router.delete(route('recourses.removeResponsible', props.recourse.id), {
    data: { person_id: personId },
    preserveScroll: true,
  });
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourse')); // Dashboard de recursos
  }
}
</script>

<template>
  <Head title="Revisar Recurso" />
  <DashboardLayout page-title="Revisar Recurso">
    <div class="max-w-4xl mx-auto bg-white p-6 rounded shadow space-y-6">
      <!-- Cabeçalho -->
      <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">
          Análise do Recurso – {{ recourse.person.name }} ({{ recourse.evaluation.year }})
        </h2>
        <button @click="goBack" class="back-btn inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
          <icons.ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <!-- Status -->
      <div class="flex items-center">
        <span
          class="text-sm text-white px-3 py-1 rounded"
          :class="{
            'bg-gray-500': recourse.status === 'aberto',
            'bg-yellow-500': recourse.status === 'em_analise',
            'bg-green-600': recourse.status === 'respondido',
            'bg-red-600': recourse.status === 'indeferido',
          }"
        >
          {{ recourse.status.replace('_', ' ').toUpperCase() }}
        </span>
      </div>

      <!-- ETAPA 1: Informações da Avaliação -->
      <div>
        <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
          <icons.FileSearch class="w-5 h-5" /> Etapa 1: Informações da Avaliação
        </h2>
        <div class="bg-gray-50 p-4 rounded border space-y-1">
          <p><strong>Avaliado:</strong> {{ recourse.evaluation.avaliado }}</p>
          <p><strong>Tipo:</strong> {{ recourse.evaluation.type }}</p>
          <p><strong>Formulário:</strong> {{ recourse.evaluation.form_name }}</p>
          <p><strong>Notas atribuídas:</strong></p>
          <ul class="list-disc list-inside text-sm text-gray-700">
            <li v-for="(answer, index) in recourse.evaluation.answers" :key="index">
              {{ answer.question }} — <strong>{{ answer.score ?? '—' }}</strong>
            </li>
          </ul>
          <a
            :href="route('evaluations.details', recourse.evaluation.id)"
            class="inline-flex items-center text-indigo-600 hover:underline text-sm mt-2"
          >
            <icons.FileSearch class="w-4 h-4 mr-1" /> Ver respostas completas
          </a>
        </div>
      </div>

      <!-- SEÇÃO: Responsáveis (apenas para RH) -->
            <!-- Seção de Responsáveis -->
      <div v-if="canManageAssignees" class="mt-6 border-t pt-4">
        <h3 class="font-semibold text-sm text-gray-700 mb-3 flex items-center gap-2">
          <icons.Users class="w-4 h-4" />
          Responsáveis pelo Recurso
        </h3>
        
        <!-- Lista de responsáveis atuais -->
        <div v-if="recourse.responsiblePersons.length" class="mb-4">
          <div class="space-y-2">
            <div
              v-for="responsible in recourse.responsiblePersons"
              :key="responsible.id"
              class="flex items-center justify-between bg-blue-50 p-3 rounded border"
            >
              <div class="flex items-center gap-2">
                <icons.User class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-medium">{{ responsible.name }}</span>
                <span class="text-xs text-gray-500">({{ responsible.registration_number }})</span>
              </div>
              <button
                @click="removeResponsible(responsible.id)"
                class="text-red-600 hover:text-red-800 p-1"
                type="button"
                title="Remover responsável"
              >
                <icons.X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="mb-4 text-sm text-gray-500 italic">
          Nenhum responsável atribuído ainda.
        </div>

        <!-- Formulário para adicionar responsável -->
        <div class="bg-gray-50 p-4 rounded border">
          <div v-if="!showAssignForm" class="text-center">
            <button
              @click="showAssignForm = true"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm inline-flex items-center gap-2"
            >
              <icons.UserPlus class="w-4 h-4" />
              Adicionar Responsável
            </button>
          </div>

          <div v-else class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Selecionar Responsável (Comissão)
              </label>
              <select
                v-model="selectedPersonId"
                class="w-full border rounded p-2 text-sm"
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
                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
                :disabled="!selectedPersonId"
              >
                Atribuir
              </button>
              <button
                @click="showAssignForm = false; selectedPersonId = null"
                class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm"
              >
                Cancelar
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ETAPA 2: Leitura do Recurso -->
      <div>
        <h2 class="text-lg font-semibold text-gray-800 mt-6 mb-2 flex items-center gap-2">
          <icons.BookOpen class="w-5 h-5" /> Etapa 2: Leitura do Recurso
        </h2>

        <div class="bg-gray-100 p-4 rounded text-gray-800">
          <h3 class="font-semibold text-sm text-gray-700 mb-1">Texto enviado:</h3>
          <p class="whitespace-pre-wrap">{{ recourse.text }}</p>
        </div>

        <div v-if="recourse.attachments.length" class="mt-4">
          <h3 class="font-semibold text-sm text-gray-700 mb-1">Anexos:</h3>
          <ul class="list-disc list-inside">
            <li v-for="(file, index) in recourse.attachments" :key="index">
              <a
                @click.prevent="openFile(file)"
                href="#"
                class="text-blue-600 hover:underline flex items-center gap-1"
              >
                <icons.PaperclipIcon class="w-4 h-4" /> {{ file.name }}
              </a>
            </li>
          </ul>
        </div>
      </div>

      <!-- ETAPA 3: Parecer -->
      <div class="border-t pt-6 mt-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
          <icons.Edit class="w-5 h-5" /> Etapa 3: Parecer da Comissão
        </h2>

        <!-- Mostrar parecer final -->
        <template v-if="recourse.status === 'respondido' || recourse.status === 'indeferido'">
          <div class="bg-gray-100 p-4 rounded text-gray-800">
            <p class="text-sm text-gray-700 font-semibold mb-2">Parecer Final:</p>
            <p class="whitespace-pre-wrap text-sm">{{ recourse.response }}</p>
          </div>
          
          <!-- Anexos da resposta -->
          <div v-if="recourse.responseAttachments && recourse.responseAttachments.length" class="mt-4">
            <h3 class="font-semibold text-sm text-gray-700 mb-1">Anexos da Resposta:</h3>
            <ul class="list-disc list-inside">
              <li v-for="(file, index) in recourse.responseAttachments" :key="index">
                <a
                  @click.prevent="openFile(file)"
                  href="#"
                  class="text-blue-600 hover:underline flex items-center gap-1"
                >
                  <icons.PaperclipIcon class="w-4 h-4" /> {{ file.name }}
                </a>
              </li>
            </ul>
          </div>
        </template>

        <!-- Formulário de análise -->
        <template v-else-if="isAnalyzing">
          <textarea
            v-model="response"
            class="w-full border rounded p-2 text-sm"
            rows="5"
            placeholder="Digite o parecer detalhado aqui..."
          ></textarea>

          <!-- Campo para anexos da resposta -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Anexos da Resposta (opcional)
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
              class="px-3 py-2 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 flex items-center gap-2"
            >
              <icons.PaperclipIcon class="w-4 h-4" />
              Adicionar Anexos
            </button>

            <!-- Lista de anexos selecionados -->
            <div v-if="responseAttachments.length" class="mt-3">
              <p class="text-sm text-gray-600 mb-2">Anexos selecionados:</p>
              <ul class="space-y-2">
                <li
                  v-for="(file, index) in responseAttachments"
                  :key="index"
                  class="flex items-center justify-between bg-gray-50 p-2 rounded text-sm"
                >
                  <span class="flex items-center gap-2">
                    <icons.PaperclipIcon class="w-4 h-4" />
                    {{ file.name }}
                  </span>
                  <button
                    @click="removeAttachment(index)"
                    class="text-red-600 hover:text-red-800"
                    type="button"
                  >
                    <icons.X class="w-4 h-4" />
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <div class="flex gap-4 items-center mt-3">
            <label class="flex items-center gap-2 text-sm">
              <input type="radio" v-model="decision" value="respondido" />
              Deferir
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input type="radio" v-model="decision" value="indeferido" />
              Indeferir
            </label>
          </div>

          <button
            @click="submitAnalysis"
            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm mt-3"
          >
            Salvar Parecer
          </button>
        </template>

        <!-- Botão para iniciar análise -->
        <template v-else>
          <button
            @click="markAsAnalyzing"
            class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm"
          >
            Iniciar Análise
          </button>
          <p class="text-sm text-gray-600 mt-2">
            Clique acima para iniciar a análise e habilitar o parecer.
          </p>
        </template>
      </div>

      <!-- Histórico -->
      <div v-if="recourse.logs.length" class="mt-8">
        <h3 class="font-semibold text-sm text-gray-700 mb-2">Histórico do Recurso:</h3>
        <ul class="border-l-2 border-gray-300 pl-4 space-y-3">
          <li v-for="(log, index) in recourse.logs" :key="index" class="relative">
            <div class="absolute -left-2 top-1 w-3 h-3 bg-gray-400 rounded-full"></div>
            <div class="text-sm text-gray-800">
              <strong>{{ log.status.replace('_', ' ').toUpperCase() }}</strong>
              <span v-if="log.message">— {{ log.message }}</span>
              <div class="text-xs text-gray-500">{{ log.created_at }}</div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </DashboardLayout>
</template>
