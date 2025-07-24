<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourse: {
    id: number;
    text: string;
    status: string;
    response: string | null;
    responded_at: string | null;
    evaluation: {
      year: string;
      id: number;
    };
    attachments: Array<{
      name: string;
      url: string;
    }>;
  };
}>();
</script>

<template>
  <Head title="Acompanhamento do Recurso" />
  <DashboardLayout page-title="Acompanhamento do Recurso">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow space-y-6">
      <div class="flex justify-between items-center">
        <h2 class="text-lg font-semibold">Recurso da Avaliação {{ recourse.evaluation.year }}</h2>
        <Link :href="route('dashboard')" class="text-sm text-gray-500 hover:underline">Voltar</Link>
      </div>

      <div>
        <h3 class="text-sm font-medium text-gray-700 mb-1">Texto enviado:</h3>
        <p class="text-gray-800 whitespace-pre-wrap">{{ recourse.text }}</p>
      </div>

      <div>
        <h3 class="text-sm font-medium text-gray-700 mb-1">Anexos:</h3>
        <ul class="list-disc list-inside space-y-1">
          <li v-for="(file, index) in recourse.attachments" :key="index">
            <a :href="file.url" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
              <icons.PaperclipIcon class="w-4 h-4" />
              {{ file.name }}
            </a>
          </li>
        </ul>
      </div>

      <div>
        <h3 class="text-sm font-medium text-gray-700 mb-1">Status:</h3>
        <p class="text-sm font-semibold capitalize">
          <span v-if="recourse.status === 'aberto'" class="text-yellow-600">Aberto</span>
          <span v-else-if="recourse.status === 'em_analise'" class="text-blue-600">Em Análise</span>
          <span v-else-if="recourse.status === 'respondido'" class="text-green-600">Respondido</span>
          <span v-else-if="recourse.status === 'indeferido'" class="text-red-600">Indeferido</span>
        </p>
      </div>

      <div v-if="recourse.response">
        <h3 class="text-sm font-medium text-gray-700 mb-1">Resposta:</h3>
        <p class="text-gray-800 whitespace-pre-wrap">{{ recourse.response }}</p>
        <p class="text-xs text-gray-500 mt-1">Respondido em: {{ recourse.responded_at }}</p>
      </div>
    </div>
  </DashboardLayout>
</template>
