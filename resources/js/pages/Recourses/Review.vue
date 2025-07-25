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
    attachments: Array<{ name: string; url: string }>;
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
}>();

const response = ref('');
const decision = ref<'respondido' | 'indeferido' | null>(null);

function submitAnalysis() {
  if (!decision.value || !response.value.trim()) {
    alert('Informe o parecer e selecione o tipo de decisão.');
    return;
  }

  router.post(route('recourses.respond', props.recourse.id), {
    status: decision.value,
    response: response.value,
  });
}
</script>

<template>
  <Head title="Revisar Recurso" />
  <DashboardLayout page-title="Revisar Recurso">
    <div class="max-w-4xl mx-auto bg-white p-6 rounded shadow space-y-6">

      <div class="flex justify-between items-center">
        <h2 class="text-xl font-semibold text-gray-800">
          Análise do Recurso – {{ recourse.person.name }} ({{ recourse.evaluation.year }})
        </h2>
        <span class="text-sm text-gray-600 capitalize bg-gray-100 px-3 py-1 rounded">
          {{ recourse.status }}
        </span>
      </div>

      <!-- Dados da avaliação -->
      <div class="bg-gray-50 p-4 rounded border">
        <p><strong>Avaliado:</strong> {{ recourse.evaluation.avaliado }}</p>
        <p><strong>Tipo:</strong> {{ recourse.evaluation.type }}</p>
        <p><strong>Formulário:</strong> {{ recourse.evaluation.form_name }}</p>
        <p>
          <strong>Notas atribuídas:</strong>
        </p>
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

      <!-- Texto do recurso -->
      <div>
        <h3 class="font-semibold text-sm text-gray-700">Texto enviado:</h3>
        <p class="whitespace-pre-wrap mt-1 bg-gray-100 p-3 rounded text-gray-800">
          {{ recourse.text }}
        </p>
      </div>

      <!-- Anexos -->
      <div v-if="recourse.attachments.length">
        <h3 class="font-semibold text-sm text-gray-700">Anexos:</h3>
        <ul class="list-disc list-inside">
          <li v-for="(file, index) in recourse.attachments" :key="index">
            <a
              :href="file.url"
              :download="file.name"
              target="_blank"
              class="text-blue-600 hover:underline flex items-center gap-1"
            >
              <icons.PaperclipIcon class="w-4 h-4" /> {{ file.name }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Formulário de decisão -->
      <div class="space-y-3 border-t pt-6 mt-6">
        <h3 class="font-semibold text-sm text-gray-700">Parecer da Comissão</h3>

        <textarea
          v-model="response"
          class="w-full border rounded p-2 text-sm"
          rows="5"
          placeholder="Digite o parecer detalhado aqui..."
        ></textarea>

        <div class="flex gap-4 items-center">
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
          class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
        >
          Salvar Parecer
        </button>
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
