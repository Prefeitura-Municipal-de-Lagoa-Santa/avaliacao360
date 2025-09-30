<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps<{
  person: {
    id: number;
    name: string;
    registration_number: string | null;
  };
  evaluatedEvaluations: Array<{
    id: number;
    type: string;
    form_name: string;
    form_year: string;
    requests: Array<{
      id: number;
      status: string;
      evaluator_name: string;
      completed_at: string | null;
    }>;
  }>;
  evaluationsToMake: Array<{
    id: number;
    status: string;
    type: string;
    form_name: string;
    form_year: string;
    evaluated_person_name: string;
    completed_at: string | null;
  }>;
}>();

const getStatusBadge = (status: string) => {
  const statusMap: Record<string, { class: string; text: string }> = {
    'pending': { class: 'bg-yellow-100 text-yellow-800', text: 'Pendente' },
    'completed': { class: 'bg-green-100 text-green-800', text: 'Concluída' },
    'released': { class: 'bg-blue-100 text-blue-800', text: 'Liberada' }
  };
  return statusMap[status] || { class: 'bg-gray-100 text-gray-800', text: status };
};

const formatType = (type: string) => {
  const typeMap: Record<string, string> = {
    'autoavaliação': 'Autoavaliação',
    'autoavaliaçãoGestor': 'Autoavaliação (Gestor)',
    'autoavaliaçãoComissionado': 'Autoavaliação (Comissionado)',
    'servidor': 'Avaliação de Servidor',
    'gestor': 'Avaliação de Gestor',
    'chefia': 'Avaliação de Chefia',
    'comissionado': 'Avaliação de Comissionado'
  };
  return typeMap[type] || type;
};

const formatDate = (dateString: string | null) => {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString('pt-BR');
};
</script>

<template>
  <Head :title="`Avaliações de ${person.name}`" />
  <DashboardLayout :pageTitle="`Avaliações de ${person.name}`">
    <div class="max-w-6xl mx-auto space-y-8">
      
      <!-- Informações da Pessoa -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">{{ person.name }}</h2>
            <p class="text-sm text-gray-600">Matrícula: {{ person.registration_number || 'N/A' }}</p>
          </div>
          <Link :href="route('people.edit', person.id)" 
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Editar Pessoa
          </Link>
        </div>
      </div>

      <!-- Avaliações que a pessoa recebeu -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Avaliações Recebidas</h3>
        <div v-if="evaluatedEvaluations.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Formulário</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avaliadores</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="evaluation in evaluatedEvaluations" :key="evaluation.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatType(evaluation.type) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ evaluation.form_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ evaluation.form_year }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div class="space-y-1">
                    <div v-for="request in evaluation.requests" :key="request.id" class="flex items-center justify-between">
                      <span>{{ request.evaluator_name }}</span>
                      <div class="flex items-center space-x-2">
                        <span :class="getStatusBadge(request.status).class" 
                          class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                          {{ getStatusBadge(request.status).text }}
                        </span>
                        <span v-if="request.completed_at" class="text-xs text-gray-500">
                          {{ formatDate(request.completed_at) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          Nenhuma avaliação recebida encontrada.
        </div>
      </div>

      <!-- Avaliações que a pessoa deve fazer -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Avaliações a Fazer</h3>
        <div v-if="evaluationsToMake.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Formulário</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pessoa Avaliada</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Concluída em</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="evaluation in evaluationsToMake" :key="evaluation.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatType(evaluation.type) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ evaluation.form_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ evaluation.form_year }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ evaluation.evaluated_person_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusBadge(evaluation.status).class" 
                    class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                    {{ getStatusBadge(evaluation.status).text }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatDate(evaluation.completed_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          Nenhuma avaliação para fazer encontrada.
        </div>
      </div>

    </div>
  </DashboardLayout>
</template>
