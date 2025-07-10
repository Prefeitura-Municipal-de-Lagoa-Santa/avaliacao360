<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

// 1. Defina a nova prop 'dashboardStats' que virá do controller
const props = defineProps<{
  prazoAvaliacao: { term_first: string; term_end: string; } | null;
  prazoPdi: { term_first: string; term_end: string; } | null;
  dashboardStats: {
    completedAssessments: number;
    pendingAssessments: number;
    overallProgress: string;
  };
}>();
console.log(props);

function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo) return 'Não definido';
 
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  
  return `${inicio} - ${fim}`;
}

function goToCalendar() {
  router.get(route('calendar'));
}

const CheckCircleIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;


function showDetailsForDeadline() {
  console.log('Exibindo detalhes...');
  
}
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      
      <DashboardCard
        :value="props.dashboardStats.completedAssessments"
        label="Avaliações Concluídas"
        iconBgColor="#10b981"
        :buttonAction="showDetailsForDeadline"
        buttonText="Visualizar"
      >
        <template #icon>
          <div v-html="CheckCircleIcon"></div>
        </template>
      </DashboardCard>

      <DashboardCard
        :value="props.dashboardStats.pendingAssessments"
        label="Avaliações Pendentes"
        iconBgColor="#f97316"
        :buttonAction="showDetailsForDeadline"
        buttonText="Ver Pendências"
      >
        <template #icon>
          <icons.CircleAlert />
        </template>
      </DashboardCard>

      <DashboardCard
        :value="props.dashboardStats.overallProgress"
        label="Progresso Geral"
        iconBgColor="#6366f1"
        :buttonAction="showDetailsForDeadline"
        buttonText="Ver Progresso"
      >
        <template #icon>
          <icons.ChartNoAxesCombined />
        </template>
      </DashboardCard>
      
      <DashboardCard
        props.prazoAvaliacao
        label="Data Avaliação"
        :value="formatPrazo(props.prazoAvaliacao)"
        iconBgColor="#ef4444"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>
      
      <DashboardCard
        props.prazoPdi
        :value="formatPrazo(props.prazoPdi)"
        label="Data PDI"
        iconBgColor="#f97316"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarClock />
        </template>
      </DashboardCard>
    </div>
  </DashboardLayout>
</template>