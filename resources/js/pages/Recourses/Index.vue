<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourses: {
    data: Array<{
      id: number;
      text: string;
      status: string;
      person: { name: string };
      evaluation: { id: number; form?: { year: string } };
    }>;
    links: any;
  };
  status: string;
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

  <Head :title="`Recursos - ${status}`" />
  <DashboardLayout :page-title="`Recursos - ${status}`">
    <div class="space-y-4">
      <div class="detail-page-header">
        <h2 class="text-2xl font-bold text-gray-800">Recursos Abertos</h2>
        <button @click="goBack" class="back-btn">
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
            <th class="px-4 py-2">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in recourses.data" :key="r.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-2">{{ r.person.name }}</td>
            <td class="px-4 py-2">{{ r.evaluation.form?.year ?? '—' }}</td>
            <td class="px-4 py-2 capitalize">{{ r.status }}</td>
            <td class="px-4 py-2">
              <Link :href="route('recourses.show', r.id)"
                class="inline-flex items-center text-sm text-blue-600 hover:underline">
              <icons.Eye class="w-4 h-4 mr-1" /> Ver
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </DashboardLayout>
</template>
