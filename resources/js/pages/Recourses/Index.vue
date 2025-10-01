<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourses: {
    data: Array<{
      id: number;
      status: string;
      text: string;
      person: { name: string };
      evaluation: { year: string; id: number };
      responsiblePersons?: Array<{ name: string }>;
    }>;
    links: any;
    meta: any;
  };
  status: string;
  canManageAssignees: boolean;
  userRole?: string; // 'RH' ou 'Comissão'
}>();// Mapeamento para rótulos legíveis
const statusLabels: Record<string, string> = {
  aberto: 'Abertos',
  em_analise: 'Em Análise',
  respondido: 'Deferidos',
  indeferido: 'Indeferidos',
  todos: 'Todos',
};

const statusLabel = statusLabels[props.status] ?? '—';

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourse')); // Dashboard de recursos
  }
}
</script>

<template>
  <Head :title="props.userRole === 'Comissão' ? `Meus Recursos ${statusLabel}` : `Recursos ${statusLabel}`" />
  <DashboardLayout :page-title="props.userRole === 'Comissão' ? `Meus Recursos ${statusLabel}` : `Recursos ${statusLabel}`">
    <div class="space-y-4">
      <!-- Informação do papel do usuário -->
      <div v-if="props.userRole === 'Comissão'" class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center gap-2 text-blue-800">
          <icons.Info class="w-4 h-4" />
          <span class="text-sm font-medium">
            Mostrando apenas recursos pelos quais você é responsável como membro da Comissão.
          </span>
        </div>
      </div>

      <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">
          {{ props.userRole === 'Comissão' ? 'Meus Recursos' : 'Recursos' }} {{ statusLabel }}
        </h2>
        <button @click="goBack" class="back-btn inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
          <icons.ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <table class="w-full bg-white shadow rounded text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <th class="px-4 py-2">Servidor</th>
            <th class="px-4 py-2">Ano</th>
            <th class="px-4 py-2">Status</th>
            <th v-if="canManageAssignees" class="px-4 py-2">Responsáveis</th>
            <th class="px-4 py-2">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in recourses.data" :key="r.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-2">{{ r.person.name }}</td>
            <td class="px-4 py-2">{{ r.evaluation.year }}</td>
            <td class="px-4 py-2 capitalize">
              {{ r.status.replace('_', ' ') }}
            </td>
            <td v-if="canManageAssignees" class="px-4 py-2">
              <div v-if="r.responsiblePersons && r.responsiblePersons.length > 0" class="flex flex-wrap gap-1">
                <span 
                  v-for="person in r.responsiblePersons" 
                  :key="person.name"
                  class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800"
                >
                  {{ person.name }}
                </span>
              </div>
              <span v-else class="text-gray-400 text-xs">Sem responsáveis</span>
            </td>
            <td class="px-4 py-2">
              <Link
                :href="route('recourses.review', r.id)"
                class="inline-flex items-center text-sm text-blue-600 hover:underline"
              >
                <icons.Eye class="w-4 h-4 mr-1" /> Ver
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="recourses.links && recourses.links.length > 3" class="mt-6 flex justify-center">
        <div class="flex flex-wrap -mb-1">
          <template v-for="(link, key) in recourses.links" :key="key">
            <div
              v-if="link.url === null"
              class="mr-1 mb-1 px-4 py-3 text-sm text-gray-400 border rounded"
              v-html="link.label"
            />
            <Link
              v-else
              class="mr-1 mb-1 px-4 py-3 text-sm border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500"
              :class="{ 'bg-indigo-500 text-white': link.active }"
              :href="link.url"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
