<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourse: { term_first: string; term_end: string } | null;
  totals?: {
    opened: number;
    under_analysis: number;
    responded: number;
    denied: number;
  };
  userRole?: string; // 'RH' ou 'Comissão'
}>();

function formatPrazo(prazo: { term_first: string; term_end: string } | null): string {
  if (!prazo) return 'Não definido';
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  return `${inicio} - ${fim}`;
}

function goToOpenedRecourses() {
  // Para Comissão, mostra todos os recursos (sem filtro de status)
  if (props.userRole === 'Comissão') {
    router.get(route('recourses.index'));
  } else {
    // Para RH, mostra apenas recursos abertos
    router.get(route('recourses.index', { status: 'aberto' }));
  }
}

function goToUnderAnalysisRecourses() {
  router.get(route('recourses.index', { status: 'em_analise' }));
}

function goToRespondedRecourses() {
  router.get(route('recourses.index', { status: 'respondido' }));
}

function goToDeniedRecourses() {
  router.get(route('recourses.index', { status: 'indeferido' }));
}
</script>

<template>
  <Head :title="props.userRole === 'Comissão' ? 'Meus Recursos' : 'Recursos'" />
  <DashboardLayout :pageTitle="props.userRole === 'Comissão' ? 'Meus Recursos para Análise' : 'Dashboard de Recursos'">
    <!-- Informação do papel do usuário -->
    <div v-if="props.userRole === 'Comissão'" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
      <div class="flex items-center gap-2 text-blue-800">
        <icons.Info class="w-4 h-4" />
        <span class="text-sm font-medium">
          Você está visualizando apenas os recursos pelos quais é responsável como membro da Comissão.
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

      <!-- Para Comissão: apenas um card com recursos atribuídos -->
      <template v-if="props.userRole === 'Comissão'">
        <div class="col-span-full flex justify-center">
          <DashboardCard
            :value="(props.totals?.opened ?? 0) + (props.totals?.under_analysis ?? 0) + (props.totals?.responded ?? 0) + (props.totals?.denied ?? 0)"
            label="Meus Recursos para Análise"
            buttonText="Ver Recursos"
            iconBgColor="#1d82c4"
            :buttonAction="goToOpenedRecourses"
          >
            <template #icon><icons.FileSignature /></template>
          </DashboardCard>
        </div>
      </template>

      <!-- Para RH: todos os cards -->
      <template v-else-if="props.userRole === 'RH' || !props.userRole">
        <DashboardCard
          :value="props.totals?.opened ?? 0"
          label="Recursos Abertos"
          buttonText="Acessar"
          iconBgColor="#1d82c4"
          :buttonAction="goToOpenedRecourses"
        >
          <template #icon><icons.FileSignature /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.under_analysis ?? 0"
          label="Recursos em Análise"
          buttonText="Acessar"
          iconBgColor="#0ea5e9"
          :buttonAction="goToUnderAnalysisRecourses"
        >
          <template #icon><icons.Loader2 class="animate-spin" /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.responded ?? 0"
          label="Recursos Deferidos"
          buttonText="Ver Deferidos"
          iconBgColor="#10b981"
          :buttonAction="goToRespondedRecourses"
        >
          <template #icon><icons.CheckCircle /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.denied ?? 0"
          label="Recursos Indeferidos"
          buttonText="Ver Indeferidos"
          iconBgColor="#ef4444"
          :buttonAction="goToDeniedRecourses"
        >
          <template #icon><icons.XCircle /></template>
        </DashboardCard>
      </template>

    </div>
  </DashboardLayout>
</template>
