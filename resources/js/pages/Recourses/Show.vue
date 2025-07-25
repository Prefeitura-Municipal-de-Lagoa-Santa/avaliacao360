<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
    person: {
      name: string;
    };
    attachments: Array<{
      name: string;
      url: string;
    }>;
    logs: Array<{
      status: string;
      message: string | null;
      created_at: string;
    }>;
  };
}>();

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourses.dashboard'));
  }
}
</script>

<template>
  <Head :title="`Recurso de ${recourse.person.name}`" />
  <DashboardLayout :page-title="`Recurso de ${recourse.person.name}`">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow space-y-6">
      <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">
          Recurso da Avaliação de {{ recourse.person.name }} - {{ recourse.evaluation.year }}
        </h2>
        <button @click="goBack" class="back-btn">
          <icons.ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <!-- Status -->
      <div class="space-y-2">
        <p class="text-sm text-gray-700 font-medium">Status do Recurso:</p>

        <div v-if="recourse.status === 'aberto'" class="text-yellow-700 bg-yellow-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.AlertCircleIcon class="w-5 h-5" />
          <span>Aguardando análise da comissão.</span>
        </div>

        <div v-else-if="recourse.status === 'em_analise'" class="text-blue-700 bg-blue-50 px-4 py-2 rounded flex items-center gap-2">
          <icons.LoaderIcon class="w-5 h-5 animate-spin" />
          <span>Recurso em análise. Em breve você receberá uma resposta oficial.</span>
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
            <a :href="file.url" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
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
      </div>

      <!-- Cronograma -->
      <div v-if="recourse.logs?.length" class="mt-8">
        <h3 class="font-semibold text-sm text-gray-700 mb-2">Cronograma do Recurso:</h3>
        <ul class="border-l-2 border-gray-300 pl-4 space-y-3">
          <li v-for="(log, index) in recourse.logs" :key="index" class="relative">
            <div class="absolute -left-2 top-1 w-3 h-3 bg-gray-400 rounded-full"></div>
            <div class="text-sm text-gray-800">
              <strong>{{ log.status.replace('_', ' ').toUpperCase() }}</strong> —
              {{ log.message || 'Atualização de status' }}
              <span class="block text-xs text-gray-500">{{ log.created_at }}</span>
            </div>
          </li>
        </ul>
      </div>

      <!-- Aviso se ainda não respondeu -->
      <div v-if="!recourse.response" class="mt-8 text-sm text-gray-500">
        Você será notificado nesta tela e por e-mail assim que a comissão responder seu recurso.
      </div>
    </div>
  </DashboardLayout>
</template>
