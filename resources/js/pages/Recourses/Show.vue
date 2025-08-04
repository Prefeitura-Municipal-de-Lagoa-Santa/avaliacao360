<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourse: {
    id: number;
    text: string;
    status: string;
    response?: string;
    responded_at?: string;
    attachments: Array<{ name: string; url: string }>;
    responseAttachments?: Array<{ name: string; url: string }>;
    evaluation: {
      id: number;
      year: string;
    };
    person: { name: string };
    logs: Array<{ status: string; message: string; created_at: string }>;
  };
}>();

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
    router.get(route('recourses.dashboard'));
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
  </DashboardLayout>
</template>
