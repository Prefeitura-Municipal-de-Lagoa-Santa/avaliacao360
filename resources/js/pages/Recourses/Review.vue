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
    <div class="max-w-6xl mx-auto space-y-6">
      <!-- Cabeçalho Principal -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
              Análise de Recurso de Avaliação
            </h1>
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
            </div>
          </div>
          <div class="flex items-center gap-3">
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
            <button @click="goBack" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
              <icons.ArrowLeftIcon class="w-4 h-4 mr-2" />
              Voltar
            </button>
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
              Notas da Avaliação - Base do Recurso
            </h2>
            <p class="text-sm text-gray-300 mt-1">
              Avaliação {{ recourse.evaluation.type }} - {{ recourse.evaluation.form_name }}
            </p>
          </div>
          
          <div class="p-4 space-y-4">
            <!-- Resumo das Notas -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <icons.BarChart3 class="w-4 h-4" />
                Notas Atribuídas pelo Chefe Imediato
              </h3>
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
                :href="route('evaluations.details', recourse.evaluation.id)"
                class="inline-flex items-center px-4 py-2 bg-gray-700 text-white text-sm rounded-lg hover:bg-gray-800 transition-colors"
              >
                <icons.ExternalLink class="w-4 h-4 mr-2" />
                Ver Avaliação Completa
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
                  class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition-colors"
                  type="button"
                  title="Remover responsável"
                >
                  <icons.X class="w-4 h-4" />
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

      <!-- SEÇÃO: Análise e Parecer da Comissão -->
      <div class="bg-white rounded-lg shadow-sm border">
        <div class="bg-gray-800 text-white p-4 rounded-t-lg">
          <h2 class="text-lg font-semibold flex items-center gap-2">
            <icons.Scale class="w-5 h-5" />
            Análise e Parecer da Comissão
          </h2>
          <p class="text-sm text-gray-300 mt-1">
            Decisão sobre o recurso baseada na avaliação das notas
          </p>
        </div>

        <div class="p-4">
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

          <!-- Formulário de análise -->
          <template v-else-if="isAnalyzing">
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
                         :class="decision === 'respondido' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400'">
                    <input type="radio" v-model="decision" value="respondido" class="text-gray-600" />
                    <icons.CheckCircle class="w-5 h-5" />
                    <span class="font-medium">DEFERIR RECURSO</span>
                  </label>
                  <label class="flex items-center justify-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-colors"
                         :class="decision === 'indeferido' ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-300 hover:border-gray-400'">
                    <input type="radio" v-model="decision" value="indeferido" class="text-gray-600" />
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
                  :disabled="!decision || !response.trim()"
                  :class="{ 'opacity-50 cursor-not-allowed': !decision || !response.trim() }"
                >
                  <icons.Save class="w-5 h-5" />
                  Finalizar Análise e Salvar Parecer
                </button>
              </div>
            </div>
          </template>

          <!-- Botão para iniciar análise -->
          <template v-else>
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
  </DashboardLayout>
</template>
