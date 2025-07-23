<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
  evaluations: Array<{
    year: string | null;
    user: string;
    final_score: number;
    calc_final: string;
    calc_auto?: string;
    calc_chefia?: string;
    calc_equipe?: string;
    id: number | null;
    is_in_aware_period?: boolean;
  }>;
}>();

// Computed só com as avaliações visíveis
const visibleEvaluations = computed(() =>
  (props.evaluations ?? []).filter(eva => eva && eva.is_in_aware_period)
);
</script>

<template>
  <Head title="Minhas Avaliações Anuais" />
  <DashboardLayout pageTitle="Minhas Avaliações Anuais">
    <div class="flex justify-between items-center mb-6">
      <Link :href="route('dashboard')" class="flex items-center px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">
        <icons.ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </Link>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
      <table class="w-full text-sm text-left text-gray-600">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
          <tr>
            <th class="px-6 py-3">Ano</th>
            <th class="px-6 py-3">Nota Final</th>
            <th class="px-6 py-3">Notas Parciais</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="visibleEvaluations.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
              Nenhuma avaliação anual disponível para visualização neste momento.
            </td>
          </tr>
          <tr
            v-for="eva in visibleEvaluations"
            :key="eva?.year"
            class="bg-white border-b hover:bg-gray-50"
          >
            <td class="px-6 py-4">{{ eva?.year }}</td>
            <td class="px-6 py-4">{{ eva?.final_score }}</td>
            <td class="px-6 py-4">
              <div v-if="eva?.calc_auto">{{ eva.calc_auto }}</div>
              <div v-if="eva?.calc_chefia">{{ eva.calc_chefia }}</div>
              <div v-if="eva?.calc_equipe">{{ eva.calc_equipe }}</div>
            </td>
            <td class="px-6 py-4 text-right">
              <Link
                v-if="eva?.id"
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
  </DashboardLayout>
</template>
