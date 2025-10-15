<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourses: {
    data: Array<{
      id: number;
      status: string;
      stage?: string | null;
      concluded_for_rh?: boolean;
      text: string;
      person: { name: string };
      evaluation: { year: string; id: number };
      responsiblePersons?: Array<{ name: string }>;
      last_return?: { by: string; to: 'RH' | 'Comissao' | null; at: string } | null;
    }>;
    links: any;
    meta: any;
  };
  awaiting?: Array<{ id: number; status: string; text: string; person: { name: string }; evaluation: { id: number; year: string } }>;
  status: string;
  canManageAssignees: boolean;
  userRole?: string; // 'RH' ou 'Comissão'
}>();// Mapeamento para rótulos legíveis
const statusLabels: Record<string, string> = {
  aberto: 'Abertos',
  em_analise: 'Em Análise',
  respondido: 'Deferidos',
  indeferido: 'Indeferidos',
  devolvidos: 'Devolvidos',
  todos: 'Todos',
};

const statusLabel = statusLabels[props.status] ?? '—';
const pageTitle = (() => {
  if (props.userRole === 'Comissão') return `Meus Recursos ${statusLabel}`;
  if (props.userRole === 'DGP') return `Recursos aguardando decisão (DGP)`;
  if (props.userRole === 'Secretário') return `Recursos aguardando decisão (Secretário)`;
  return `Recursos ${statusLabel}`;
})();

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourse')); // Dashboard de recursos
  }
}

function scrollToAwaiting() {
  const el = document.getElementById('awaiting-list');
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
  <Head :title="pageTitle" />
  <DashboardLayout :page-title="pageTitle">
    <div class="space-y-4">
      <!-- Bloco: Aguardando minha decisão -->
      <div v-if="props.awaiting && props.awaiting.length > 0" id="awaiting-list" class="bg-white border rounded shadow p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
            <icons.Clock class="w-4 h-4" /> Aguardando minha decisão
          </h3>
          <span class="text-xs text-gray-500">Mostrando até 10 itens</span>
        </div>
        <div class="divide-y">
          <div v-for="r in props.awaiting" :key="r.id" class="py-2 flex items-center justify-between">
            <div>
              <div class="font-medium text-gray-900">{{ r.person.name }}</div>
              <div class="text-xs text-gray-500">Recurso • Ano: {{ r.evaluation.year || '—' }}</div>
            </div>
            <Link :href="route('recourses.review', r.id)" class="inline-flex items-center text-sm text-blue-600 hover:underline">
              <icons.Eye class="w-4 h-4 mr-1" /> Ver
            </Link>
          </div>
        </div>
      </div>
      <!-- Informação do papel do usuário -->
      <div v-if="props.userRole === 'Comissão'" class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center gap-2 text-blue-800">
          <icons.Info class="w-4 h-4" />
          <span class="text-sm font-medium">
            Mostrando apenas recursos pelos quais você é responsável como membro da Comissão.
          </span>
        </div>
      </div>

      <div v-else-if="props.userRole === 'DGP'" class="p-3 bg-purple-50 border border-purple-200 rounded-lg">
        <div class="flex items-center gap-2 text-purple-800">
          <icons.Info class="w-4 h-4" />
          <span class="text-sm font-medium">
            Mostrando recursos que aguardam sua decisão (DGP). Filtros por status são ignorados.
          </span>
        </div>
      </div>

      <div v-else-if="props.userRole === 'Secretário'" class="p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
        <div class="flex items-center gap-2 text-indigo-800">
          <icons.Info class="w-4 h-4" />
          <span class="text-sm font-medium">
            Mostrando recursos que aguardam sua decisão (Secretário). Filtros por status são ignorados.
          </span>
        </div>
      </div>

      <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">{{ pageTitle }}</h2>
        <div class="flex items-center gap-2">
          <button
            v-if="props.userRole === 'DGP'"
            @click="scrollToAwaiting"
            class="inline-flex items-center text-sm text-purple-700 hover:text-purple-900 border border-purple-200 hover:border-purple-300 bg-purple-50 hover:bg-purple-100 rounded px-3 py-1"
            title="Ver recursos atribuídos a mim (aguardando minha decisão)"
          >
            <icons.UserCheck class="w-4 h-4 mr-1" /> Recursos atribuídos a mim
          </button>
          <button @click="goBack" class="back-btn inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
            <icons.ArrowLeftIcon class="size-4 mr-2" />
            Voltar
          </button>
        </div>
      </div>

      <table class="w-full bg-white shadow rounded text-sm">
        <thead>
          <tr class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <th class="px-4 py-2">Servidor</th>
            <th class="px-4 py-2">Ano</th>
            <th class="px-4 py-2">Status</th>
            <th v-if="canManageAssignees" class="px-4 py-2">Presidente</th>
            <th class="px-4 py-2">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in recourses.data" :key="r.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-2">{{ r.person.name }}</td>
            <td class="px-4 py-2">{{ r.evaluation.year }}</td>
            <td class="px-4 py-2 capitalize">
              <span v-if="r.stage === 'completed' || r.concluded_for_rh" class="text-green-700">Concluído</span>
              <span v-else>{{ r.status.replace('_', ' ') }}</span>
            </td>
            <td v-if="r.last_return" class="px-4 py-2 text-xs text-amber-700">
              <div class="inline-flex items-center gap-1 bg-amber-50 border border-amber-200 px-2 py-1 rounded">
                <icons.Reply class="w-3 h-3" /> devolvido {{ r.last_return.at }}
              </div>
            </td>
            <td v-if="canManageAssignees" class="px-4 py-2">
              <div v-if="r.responsiblePersons && r.responsiblePersons.length > 0" class="flex flex-wrap gap-1">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                  {{ r.responsiblePersons[0].name }}
                </span>
              </div>
              <span v-else class="text-gray-400 text-xs">Sem presidente</span>
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
