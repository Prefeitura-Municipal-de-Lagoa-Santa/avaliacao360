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
    analyzed_percent: string;
  };
}>();

function formatPrazo(prazo: { term_first: string; term_end: string } | null): string {
  if (!prazo) return 'Não definido';
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  return `${inicio} - ${fim}`;
}

function goToOpenedRecourses() {
  router.get(route('recourses.index', { status: 'aberto' }));
}

function goToUnderAnalysisRecourses() {
  router.get(route('recourses.index', { status: 'em_analise' }));
}

function goToAnalysisProgress() {
  router.get(route('recourses.analysis'));
}

function goToCalendar() {
  router.get(route('calendar'));
}
</script>

<template>
  <Head title="Recursos" />
  <DashboardLayout pageTitle="Dashboard de Recursos">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
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
        :value="props.totals?.analyzed_percent ?? '0%'"
        label="Recursos Analisados"
        buttonText="Ver Progresso"
        iconBgColor="#6366f1"
        :buttonAction="goToAnalysisProgress"
      >
        <template #icon><icons.BarChart2 /></template>
      </DashboardCard>

      <DashboardCard
        :value="formatPrazo(props.recourse)"
        label="Prazo PDI"
        buttonText="Ver Calendário"
        iconBgColor="#f97316"
        :buttonAction="goToCalendar"
      >
        <template #icon><icons.CalendarClock /></template>
      </DashboardCard>
    </div>
  </DashboardLayout>
</template>
