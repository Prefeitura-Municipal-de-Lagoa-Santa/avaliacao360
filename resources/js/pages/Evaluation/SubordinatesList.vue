<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeftIcon, CheckCircle2, Edit, TriangleAlert } from 'lucide-vue-next';

const props = defineProps<{
  requests: Array<{
    id: number;
    status: 'pending' | 'completed';
    evaluation: {
      evaluated: {
        id: number;
        name: string;
        current_position?: string;
        job_function?: { name: string } | null; // <- pode vir null
        jobFunction?: { name: string } | null;  // <- dependendo do backend, use o certo!
      }
    }
  }>
}>();

function goToEvaluation(requestId: number) {
  router.get(route('evaluations.subordinate.show', { evaluationRequest: requestId }));
}

function goBack() {
  window.history.back();
}
</script>

<template>
  <Head title="Avaliar Equipe" />
  <DashboardLayout pageTitle="Avaliar Equipe">
    <div class="flex justify-between items-center border-b pb-4 mb-6">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Avaliação da Equipe</h1>
      <button @click="goBack" class="back-btn">
        <ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
      <div v-if="requests.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
        <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
        </svg>
        <p class="text-lg font-semibold">Não existem subordinados para avaliação no momento.</p>
        <p class="text-sm mt-2">Verifique mais tarde ou contate o administrador se houver um erro.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full">
          <thead>
            <tr>
              <th class="table-header text-left">Servidor</th>
              <th class="table-header text-left">Função/Cargo</th>
              <th class="table-header text-center">Status</th>
              <th class="table-header text-center">Ação</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in requests" :key="req.id" class="border-t">
              <td class="table-cell font-medium">{{ req.evaluation.evaluated.name }}</td>
              <td class="table-cell text-gray-600">
                <!-- Fallback de função/cargo -->
                {{
                  req.evaluation.evaluated.job_function?.name
                  || req.evaluation.evaluated.jobFunction?.name
                  || req.evaluation.evaluated.current_position
                  || '-'
                }}
              </td>
              <td class="table-cell text-center">
                <span v-if="req.status !== 'pending'" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                  <CheckCircle2 class="size-4 mr-1.5" />
                  Avaliado
                </span>
                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                  <TriangleAlert class="size-4 mr-1.5" />
                  Pendente
                </span>
              </td>
              <td class="table-cell text-right">
                <button v-if="req.status === 'pending'" @click="goToEvaluation(req.id)" class="flex items-center ml-auto px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-700 transition">
                  <Edit class="size-4 mr-2" />
                  Avaliar
                </button>
                <button v-else class="flex items-center ml-auto px-4 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed" disabled>
                  <CheckCircle2 class="size-4 mr-2" />
                  Avaliado
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.text-left {
  text-align: left;
}
.text-center {
  text-align: center;
}
.table-header {
  padding: 0.75rem 1rem;
  font-weight: 600;
  background: #f3f4f6;
  border-bottom: 2px solid #e5e7eb;
}
.table-cell {
  padding: 0.75rem 1rem;
  vertical-align: middle;
}
</style>
